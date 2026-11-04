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

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\CveState;
use Jed\Component\Vel\Administrator\Enum\RetiredReason;
use Jed\Component\Vel\Administrator\Service\JedBridge;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * The single-advisory controller, and the workflow actions.
 *
 * Each action is a thin wrapper: check the permission, take the input, call {@see VelService},
 * report what happened. None of them decides anything.
 *
 * @since 4.0.0
 */
class AdvisoryController extends FormController
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $view_list = 'advisories';

    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_VEL_ADVISORY';

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
     * `draft -> ready`.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function ready(): void
    {
        $this->move(AdvisoryState::READY, 'core.edit.state', 'COM_VEL_MSG_READY');
    }

    /**
     * `ready -> draft`.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function draft(): void
    {
        $this->move(AdvisoryState::DRAFT, 'core.edit.state', 'COM_VEL_MSG_DRAFT');
    }

    /**
     * `ready -> published`, or `retired -> published`.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function publish(): void
    {
        $this->move(AdvisoryState::PUBLISHED, 'vel.publish', 'COM_VEL_MSG_PUBLISHED', [
            'force' => (bool) $this->input->getInt('force', 0),
        ]);
    }

    /**
     * `published -> retired`, with a reason.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function retire(): void
    {
        $this->move(AdvisoryState::RETIRED, 'vel.publish', 'COM_VEL_MSG_RETIRED', [
            'reason' => (string) $this->input->getCmd('retired_reason', ''),
            'note'   => (string) $this->input->getString('retire_note', ''),
        ]);
    }

    /**
     * @param AdvisoryState $next       The state.
     * @param string        $permission The ACL action it needs.
     * @param string        $message    Language key on success.
     * @param array         $options    Passed to the service.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function move(AdvisoryState $next, string $permission, string $message, array $options = []): void
    {
        $this->checkToken();

        $id = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise($permission, 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        try {
            $this->service()->transition($id, $next, (int) $this->app->getIdentity()->id, $options);
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        if ($next === AdvisoryState::PUBLISHED) {
            $this->afterPublish($id);
        }

        // Leave the checkout behind: the move happened from the edit screen.
        $this->releaseEditId($this->option . '.edit.' . $this->context, $id);
        $this->app->setUserState('com_vel.edit.advisory.data', null);

        $this->setRedirect(
            Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $id, false),
            Text::_($message)
        );
    }

    /**
     * The JED bridge after a publish (VEL2-PRD §6.9.2): open the JED team's ticket when the
     * option says `always`, or when it says `ask` and the publish form ticked it.
     *
     * @param int $id The advisory.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function afterPublish(int $id): void
    {
        $service  = $this->service();
        $advisory = $service->repository()->find($id);

        if ($advisory === null || (int) ($advisory->jed_ticket_id ?? 0) > 0) {
            return;
        }

        $mode = (string) $service->option('jed_open_ticket_on_publish', 'ask');
        $want = $mode === 'always' || ($mode === 'ask' && (int) $this->input->getInt('open_jed_ticket', 0) === 1);

        if (!$want || !$this->app->getIdentity()->authorise('vel.jed', 'com_vel')) {
            return;
        }

        $bridge    = new JedBridge(Factory::getContainer()->get(DatabaseInterface::class));
        $publicUrl = rtrim(Uri::root(), '/') . rtrim((string) $service->option('public_list_path', '/vulnerable-extensions'), '/') . '/' . (int) $advisory->id . '-' . (string) $advisory->alias;

        foreach ((array) $advisory->products as $product) {
            $extensionId = (int) ($product->extension_id ?? 0);

            if ($extensionId <= 0) {
                continue;
            }

            $ticketId = $bridge->openTicket($advisory, $extensionId, $publicUrl);

            if ($ticketId > 0) {
                $service->setJedTicket($id, $ticketId);
                $this->app->enqueueMessage(Text::sprintf('COM_VEL_MSG_JED_TICKET_OPENED', $ticketId), 'message');

                // One ticket per advisory, about the first listed product.
                break;
            }
        }
    }

    /**
     * Pin or unpin the public status.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function setstatus(): void
    {
        $this->checkToken();

        $id = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise('core.edit.state', 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        $status   = AdvisoryStatus::tryFrom((string) $this->input->getCmd('status', ''));
        $override = (bool) $this->input->getInt('status_override', 0);

        try {
            $this->service()->setStatus($id, $status ?? AdvisoryStatus::LIVE, $override && $status !== null, (int) $this->app->getIdentity()->id);
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $id, false), Text::_('COM_VEL_MSG_STATUS_SAVED'));
    }

    /**
     * Record a CVE id and state by hand (Phase 3 adds reserving through CVE Services).
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function setcve(): void
    {
        $this->checkToken();

        $id = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise('vel.cve', 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        $cveId    = trim((string) $this->input->getString('cve_id', ''));
        $state    = CveState::tryFrom((string) $this->input->getCmd('cve_state', '')) ?? ($cveId === '' ? CveState::NONE : CveState::RESERVED);
        $assigner = trim((string) $this->input->getString('cve_assigner', ''));
        $reserved = trim((string) $this->input->getString('date_reserved', ''));

        if ($assigner === '') {
            $assigner = (string) $this->service()->option('cna_short_name', 'Joomla');
        }

        try {
            $this->service()->setCve($id, $cveId === '' ? null : $cveId, $state, $assigner, $reserved === '' ? null : Factory::getDate($reserved)->toSql(), (int) $this->app->getIdentity()->id);
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $id, false), Text::_('COM_VEL_MSG_CVE_SAVED'));
    }

    /**
     * Open the JED ticket by hand.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function jedticket(): void
    {
        $this->checkToken();

        $id = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise('vel.jed', 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        $this->input->set('open_jed_ticket', 1);
        $this->afterPublish($id);

        $this->setRedirect(Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $id, false));
    }

    /**
     * Show one stored revision as JSON.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function revision(): void
    {
        $this->checkToken('get');

        $id       = (int) $this->input->getInt('id', 0);
        $revision = (int) $this->input->getInt('revision', 0);

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $json = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('snapshot'))
                ->from($db->quoteName('#__vel_advisory_history'))
                ->where($db->quoteName('id') . ' = :rid')
                ->where($db->quoteName('advisory_id') . ' = :id')
                ->bind(':rid', $revision, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':id', $id, \Joomla\Database\ParameterType::INTEGER)
        )->loadResult();

        if ($json === null) {
            $this->fail($id, Text::_('COM_VEL_ERROR_REVISION_NOT_FOUND'));

            return;
        }

        $decoded = json_decode((string) $json);

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Content-Disposition', 'inline; filename="VEL-' . \sprintf('%04d', $id) . '-r' . $revision . '.json"', true);
        $this->app->sendHeaders();

        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->app->close();
    }

    /**
     * Report a refusal and go back to the advisory.
     *
     * @param int    $id      The advisory.
     * @param string $message What went wrong.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function fail(int $id, string $message): void
    {
        $this->setRedirect(
            Route::_(
                $id > 0
                    ? 'index.php?option=com_vel&task=advisory.edit&id=' . $id
                    : 'index.php?option=com_vel&view=advisories',
                false
            ),
            $message,
            'error'
        );
    }
}
