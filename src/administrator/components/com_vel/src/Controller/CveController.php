<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Cve\CveRecordBuilder;
use Jed\Component\Vel\Administrator\Cve\CveRecordValidator;
use Jed\Component\Vel\Administrator\Cve\CveServicesClient;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\CveState;
use Jed\Component\Vel\Administrator\Service\AdvisorySerializer;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * Generate CVE JSON (brief §4.3) and, behind the same permission, the CVE Services calls.
 *
 * @since 4.0.0
 */
class CveController extends BaseController
{
    /**
     * @return VelService
     *
     * @since 4.0.0
     */
    private function service(): VelService
    {
        return new VelService(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * @return CveRecordBuilder
     *
     * @since 4.0.0
     */
    private function builder(): CveRecordBuilder
    {
        return CveRecordBuilder::fromOptions(new AdvisorySerializer(rtrim(Uri::root(), '/'), (string) $this->service()->option('public_list_path', '/vulnerable-extensions')));
    }

    /**
     * @param int $id The advisory.
     *
     * @return object|null  The advisory with children, after the permission check.
     *
     * @since 4.0.0
     */
    private function load(int $id): ?object
    {
        if (!$this->app->getIdentity()->authorise('vel.cve', 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return null;
        }

        $advisory = $this->service()->repository()->find($id);

        if ($advisory === null) {
            $this->fail(0, Text::_('COM_VEL_ERROR_ADVISORY_NOT_FOUND'));
        }

        return $advisory;
    }

    /**
     * Show the record with its validation result.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function preview(): void
    {
        $this->checkToken('get');

        $id       = (int) $this->input->getInt('id', 0);
        $advisory = $this->load($id);

        if ($advisory === null) {
            return;
        }

        $record   = $this->builder()->record($advisory);
        $problems = (new CveRecordValidator(JPATH_ADMINISTRATOR . '/components/com_vel/schema/cve-record.json'))->validateRecord($record);

        $this->app->setUserState('com_vel.cve.preview', [
            'id'       => $id,
            'json'     => CveRecordBuilder::encode($record),
            'problems' => $problems,
        ]);

        $this->setRedirect(Route::_('index.php?option=com_vel&view=cvepreview&id=' . $id, false));
    }

    /**
     * Download the record (shape=record) or the CNA container (shape=cna).
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function generate(): void
    {
        $this->checkToken('get');

        $id       = (int) $this->input->getInt('id', 0);
        $advisory = $this->load($id);

        if ($advisory === null) {
            return;
        }

        $shape    = $this->input->getCmd('shape', 'record') === 'cna' ? 'cna' : 'record';
        $builder  = $this->builder();
        $document = $shape === 'cna' ? ['cnaContainer' => $builder->cnaContainer($advisory)] : $builder->record($advisory);
        $json     = CveRecordBuilder::encode($document);
        $name     = ((string) ($advisory->cve_id ?? '') !== '' ? (string) $advisory->cve_id : \sprintf('VEL-%04d', $id)) . ($shape === 'cna' ? '-cna' : '') . '.json';

        $this->service()->storePublishedJson($id, $json);

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();

        echo $json;

        $this->app->close();
    }

    /**
     * Reserve a CVE id through CVE Services and record it.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function reserve(): void
    {
        $this->checkToken();

        $id       = (int) $this->input->getInt('id', 0);
        $advisory = $this->load($id);

        if ($advisory === null) {
            return;
        }

        if ((string) ($advisory->cve_id ?? '') !== '') {
            $this->fail($id, Text::sprintf('COM_VEL_ERROR_CVE_ALREADY', (string) $advisory->cve_id));

            return;
        }

        try {
            $client = CveServicesClient::fromOptions();
            $cveId  = $client->reserve();

            $this->service()->setCve($id, $cveId, CveState::RESERVED, (string) $this->service()->option('cna_short_name', 'Joomla'), null, (int) $this->app->getIdentity()->id);
            $this->service()->appendNote($id, Text::sprintf('COM_VEL_NOTE_CVE_RESERVED', $cveId, $client->isProduction() ? 'production' : 'test'));
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $id, false), Text::sprintf('COM_VEL_MSG_CVE_RESERVED', $cveId));
    }

    /**
     * Publish or update the CNA container through CVE Services.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function submit(): void
    {
        $this->checkToken();

        $id       = (int) $this->input->getInt('id', 0);
        $advisory = $this->load($id);

        if ($advisory === null) {
            return;
        }

        if ((string) ($advisory->cve_id ?? '') === '') {
            $this->fail($id, Text::_('COM_VEL_ERROR_CVE_NONE'));

            return;
        }

        if ((string) $advisory->state !== AdvisoryState::PUBLISHED->value) {
            $this->fail($id, Text::_('COM_VEL_ERROR_CVE_NOT_PUBLISHED'));

            return;
        }

        $builder   = $this->builder();
        $container = $builder->cnaContainer($advisory);
        $problems  = (new CveRecordValidator())->validateCna($container);

        if ($problems !== []) {
            $this->fail($id, Text::_('COM_VEL_ERROR_CVE_INVALID') . ' ' . implode(' ', $problems));

            return;
        }

        try {
            $client   = CveServicesClient::fromOptions();
            $state    = CveState::tryFrom((string) $advisory->cve_state) ?? CveState::RESERVED;
            $response = $client->submitCna((string) $advisory->cve_id, $container, $state === CveState::PUBLISHED);

            $this->service()->storePublishedJson($id, CveRecordBuilder::encode($response));
            $this->service()->setCve($id, (string) $advisory->cve_id, CveState::PUBLISHED, (string) $advisory->cve_assigner ?: (string) $this->service()->option('cna_short_name', 'Joomla'), $advisory->date_reserved ?: null, (int) $this->app->getIdentity()->id);
            $this->service()->appendNote($id, Text::sprintf('COM_VEL_NOTE_CVE_SUBMITTED', (string) $advisory->cve_id, $client->isProduction() ? 'production' : 'test'));
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $id, false), Text::sprintf('COM_VEL_MSG_CVE_SUBMITTED', (string) $advisory->cve_id));
    }

    /**
     * Fetch the record as the CVE Program has it, and store it for comparison.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function fetch(): void
    {
        $this->checkToken();

        $id       = (int) $this->input->getInt('id', 0);
        $advisory = $this->load($id);

        if ($advisory === null) {
            return;
        }

        if ((string) ($advisory->cve_id ?? '') === '') {
            $this->fail($id, Text::_('COM_VEL_ERROR_CVE_NONE'));

            return;
        }

        try {
            $record = CveServicesClient::fromOptions()->getRecord((string) $advisory->cve_id);
            $this->service()->storePublishedJson($id, CveRecordBuilder::encode($record));

            $state = strtoupper((string) ($record['cveMetadata']['state'] ?? ''));
            $enum  = CveState::tryFrom(strtolower($state));

            if ($enum !== null && $enum !== CveState::NONE) {
                $reserved = isset($record['cveMetadata']['dateReserved']) ? Factory::getDate((string) $record['cveMetadata']['dateReserved'])->toSql() : null;
                $this->service()->setCve($id, (string) $advisory->cve_id, $enum, (string) ($record['cveMetadata']['assignerShortName'] ?? $advisory->cve_assigner), $reserved, (int) $this->app->getIdentity()->id);
            }
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $id, false), Text::_('COM_VEL_MSG_CVE_FETCHED'));
    }

    /**
     * @param int    $id      The advisory.
     * @param string $message The refusal.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function fail(int $id, string $message): void
    {
        $this->setRedirect(
            Route::_($id > 0 ? 'index.php?option=com_vel&task=advisory.edit&id=' . $id : 'index.php?option=com_vel&view=advisories', false),
            $message,
            'error'
        );
    }
}
