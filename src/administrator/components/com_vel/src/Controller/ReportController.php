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

use Jed\Component\Vel\Administrator\Enum\ReportState;
use Jed\Component\Vel\Administrator\Service\ReportService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * One report and the decisions about it: create an advisory from it, attach it to one, mark it
 * triaged, duplicate, rejected or spam.
 *
 * @since 4.0.0
 */
class ReportController extends FormController
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $view_list = 'reports';

    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_VEL_REPORT';

    /**
     * @return ReportService
     *
     * @since 4.0.0
     */
    private function service(): ReportService
    {
        return new ReportService(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function createadvisory(): void
    {
        $this->checkToken();

        $id = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise('core.create', 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        try {
            $advisoryId = $this->service()->createAdvisory($id, (int) $this->app->getIdentity()->id);
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->releaseEditId($this->option . '.edit.' . $this->context, $id);

        $this->setRedirect(
            Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $advisoryId, false),
            Text::sprintf('COM_VEL_MSG_ADVISORY_CREATED_FROM_REPORT', \sprintf('VEL-%04d', $advisoryId))
        );
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function attach(): void
    {
        $this->checkToken();

        $id = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise('core.edit', 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        $reference  = trim((string) $this->input->getString('advisory_reference', ''));
        $advisoryId = (int) $this->input->getInt('advisory_id', 0);

        if ($advisoryId <= 0 && $reference !== '') {
            $advisoryId = $this->service()->load($id) === null ? 0 : (new \Jed\Component\Vel\Administrator\Service\AdvisoryRepository(Factory::getContainer()->get(DatabaseInterface::class)))->resolveReference($reference);
        }

        if ($advisoryId <= 0) {
            $this->fail($id, Text::_('COM_VEL_ERROR_ATTACH_MISSING'));

            return;
        }

        try {
            $this->service()->attach($id, $advisoryId, (int) $this->app->getIdentity()->id);
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->setRedirect(
            Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $advisoryId, false),
            Text::sprintf('COM_VEL_MSG_REPORT_ATTACHED_TO', \sprintf('VEL-%04d', $advisoryId))
        );
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function triage(): void
    {
        $this->decide(ReportState::TRIAGED, 'COM_VEL_MSG_REPORT_TRIAGED');
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function duplicate(): void
    {
        $this->decide(ReportState::DUPLICATE, 'COM_VEL_MSG_REPORT_DUPLICATE');
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function reject(): void
    {
        $this->decide(ReportState::REJECTED, 'COM_VEL_MSG_REPORT_REJECTED');
    }

    /**
     * Spam: the decision, plus a ban through the JED's abuse controls when the reporter has an
     * account and the user may ban.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function spam(): void
    {
        $id     = (int) $this->input->getInt('id', 0);
        $report = $this->service()->load($id);

        $this->decide(ReportState::SPAM, 'COM_VEL_MSG_REPORT_SPAM');

        if ($report !== null && (int) $report->reporter_user_id > 0 && $this->app->getIdentity()->authorise('jed.user.ban', 'com_jed')) {
            try {
                $db   = Factory::getContainer()->get(DatabaseInterface::class);
                $uid  = (int) $report->reporter_user_id;
                $zero = 0;
                $now  = Factory::getDate()->toSql();
                $me   = (int) $this->app->getIdentity()->id;

                // Withdraw the report privilege; the JED's own helper reads this table.
                $db->setQuery(
                    'INSERT INTO ' . $db->quoteName('#__jed_user_access')
                    . ' (' . $db->quoteName(['user_id', 'report', 'set_by', 'set_time']) . ') VALUES (:uid, :zero, :me, :now)'
                    . ' ON DUPLICATE KEY UPDATE ' . $db->quoteName('report') . ' = :zero2, ' . $db->quoteName('set_by') . ' = :me2, ' . $db->quoteName('set_time') . ' = :now2'
                )->bind(':uid', $uid, \Joomla\Database\ParameterType::INTEGER)
                    ->bind(':zero', $zero, \Joomla\Database\ParameterType::INTEGER)
                    ->bind(':zero2', $zero, \Joomla\Database\ParameterType::INTEGER)
                    ->bind(':me', $me, \Joomla\Database\ParameterType::INTEGER)
                    ->bind(':me2', $me, \Joomla\Database\ParameterType::INTEGER)
                    ->bind(':now', $now)
                    ->bind(':now2', $now)
                    ->execute();

                $this->app->enqueueMessage(Text::_('COM_VEL_MSG_REPORT_PRIVILEGE_WITHDRAWN'), 'warning');
            } catch (Throwable $e) {
                // No com_jed access table here.
            }
        }
    }

    /**
     * @param ReportState $state   The decision.
     * @param string      $message Language key.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function decide(ReportState $state, string $message): void
    {
        $this->checkToken();

        $id = (int) $this->input->getInt('id', 0);

        if (!$this->app->getIdentity()->authorise('core.edit.state', 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return;
        }

        try {
            $this->service()->setState($id, $state, (int) $this->app->getIdentity()->id);
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_vel&task=report.edit&id=' . $id, false), Text::_($message));
    }

    /**
     * @param int    $id      The report.
     * @param string $message The refusal.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function fail(int $id, string $message): void
    {
        $this->setRedirect(
            Route::_($id > 0 ? 'index.php?option=com_vel&task=report.edit&id=' . $id : 'index.php?option=com_vel&view=reports', false),
            $message,
            'error'
        );
    }
}
