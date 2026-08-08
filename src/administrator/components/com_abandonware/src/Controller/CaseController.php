<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Abandonware\Administrator\Enum\CaseStatus;
use Jed\Component\Abandonware\Administrator\Enum\Resolution;
use Jed\Component\Abandonware\Administrator\Service\CaseService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use Throwable;

/**
 * The single-case controller, and the four workflow actions.
 *
 * Each action is a thin wrapper: check the permission, take the input, call {@see CaseService},
 * report what happened. None of them decides anything - the rules about which move is legal and
 * what has to be true first belong to the service, so that the scheduled pass and the public form
 * are bound by exactly the same ones.
 *
 * @since 4.1.0
 */
class CaseController extends FormController
{
    /**
     * @var string
     *
     * @since 4.1.0
     */
    protected $view_list = 'cases';

    /**
     * @var string
     *
     * @since 4.1.0
     */
    protected $text_prefix = 'COM_ABANDONWARE_CASE';

    /**
     * @return CaseService
     *
     * @since 4.1.0
     */
    private function service(): CaseService
    {
        return new CaseService(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * Step 3: write to the owner and start the grace period.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function contact(): void
    {
        $this->checkToken();

        $caseId = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise('abandonware.contact', 'com_abandonware')) {
            $this->fail($caseId, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        $note      = (string) $this->input->getString('contact_note', '');
        $graceDays = (int) $this->input->getInt('grace_days', 0);

        try {
            $sent = $this->service()->recordContact($caseId, (int) $this->app->getIdentity()->id, $note, $graceDays);
        } catch (Throwable $e) {
            $this->fail($caseId, $e->getMessage());

            return;
        }

        // Whether the mail went out is not the same fact as whether the attempt was recorded, and
        // the team has to be able to tell them apart: a case with a recorded attempt that nobody
        // received is the one where the grace period is running against a developer who was never
        // told anything.
        $this->setRedirect(
            Route::_('index.php?option=com_abandonware&task=case.edit&id=' . $caseId, false),
            $sent ? Text::_('COM_ABANDONWARE_MSG_CONTACT_SENT') : Text::_('COM_ABANDONWARE_MSG_CONTACT_RECORDED_NO_MAIL'),
            $sent ? 'message' : 'warning'
        );
    }

    /**
     * Conclude that the extension is abandoned.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function markabandoned(): void
    {
        $this->checkToken();

        $caseId = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise('abandonware.mark', 'com_abandonware')) {
            $this->fail($caseId, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        $publish = $this->input->exists('publish') ? (bool) $this->input->getInt('publish', 0) : null;

        try {
            $this->service()->markAbandoned($caseId, (int) $this->app->getIdentity()->id, $publish);
        } catch (Throwable $e) {
            $this->fail($caseId, $e->getMessage());

            return;
        }

        $this->setRedirect(
            Route::_('index.php?option=com_abandonware&task=case.edit&id=' . $caseId, false),
            Text::_('COM_ABANDONWARE_MSG_MARKED_ABANDONED')
        );
    }

    /**
     * Close the case with an outcome.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function resolve(): void
    {
        $this->checkToken();

        $caseId = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise('abandonware.resolve', 'com_abandonware')) {
            $this->fail($caseId, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        $resolution = Resolution::tryFrom((string) $this->input->getCmd('resolution', ''));

        if ($resolution === null) {
            $this->fail($caseId, Text::_('COM_ABANDONWARE_ERROR_NO_RESOLUTION'));

            return;
        }

        try {
            $this->service()->resolve(
                $caseId,
                $resolution,
                (int) $this->app->getIdentity()->id,
                (string) $this->input->getString('resolve_note', '')
            );
        } catch (Throwable $e) {
            $this->fail($caseId, $e->getMessage());

            return;
        }

        $this->setRedirect(
            Route::_('index.php?option=com_abandonware&view=cases', false),
            Text::sprintf('COM_ABANDONWARE_MSG_RESOLVED', Text::_($resolution->label()))
        );
    }

    /**
     * Pick a case up - the `received` → `reviewing` move, plus the assignment.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function claim(): void
    {
        $this->checkToken();

        $caseId = (int) $this->input->getInt('id', 0);
        $me     = (int) $this->app->getIdentity()->id;

        if (!$this->app->getIdentity()->authorise('core.edit', 'com_abandonware')) {
            $this->fail($caseId, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        try {
            $service = $this->service();
            $case    = $service->load($caseId);

            if ($case === null) {
                throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_CASE_NOT_FOUND'));
            }

            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__jed_abandonware_cases'))
                    ->set($db->quoteName('assigned_to') . ' = :me')
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':me', $me)
                    ->bind(':id', $caseId)
            )->execute();

            if ((CaseStatus::tryFrom((string) $case->status) ?? CaseStatus::RECEIVED) === CaseStatus::RECEIVED) {
                $service->transition($caseId, CaseStatus::REVIEWING, $me);
            }
        } catch (Throwable $e) {
            $this->fail($caseId, $e->getMessage());

            return;
        }

        $this->setRedirect(
            Route::_('index.php?option=com_abandonware&task=case.edit&id=' . $caseId, false),
            Text::_('COM_ABANDONWARE_MSG_CLAIMED')
        );
    }

    /**
     * Report a refusal and go back to the case.
     *
     * @param int    $caseId  The case.
     * @param string $message What went wrong.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function fail(int $caseId, string $message): void
    {
        $this->setRedirect(
            Route::_(
                $caseId > 0
                    ? 'index.php?option=com_abandonware&task=case.edit&id=' . $caseId
                    : 'index.php?option=com_abandonware&view=cases',
                false
            ),
            $message,
            'error'
        );
    }
}
