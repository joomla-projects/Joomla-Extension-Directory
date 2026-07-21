<?php

/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Table\VulnerableitemTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Handles the `AbandonedreportTicketHandler` ticket actions ("Confirm -> Vulnerable Item" and
 * "Close") for a `#__vel_abandoned` report. This is the safe, parameter-bound replacement for
 * the string-concatenated SQL in the legacy `TicketController::copyAbandonedReporttoVEL()` /
 * `AbandonedreportController::copyReporttoVEL()` methods, which those ticket actions no longer
 * call.
 *
 * @since 4.1.0
 */
class AbandoneditemController extends BaseController
{
    /**
     * "Confirm -> Vulnerable Item": creates a `#__vel_vulnerable_item` row from the abandoned
     * report (or reuses one already linked) and links the report to it.
     *
     * @return void
     *
     * @since 4.1.0
     * @throws Exception
     */
    public function publish(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.edit', 'com_vel')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $reportId = $this->input->getInt('linked_item_id');

        if (!$reportId) {
            throw new Exception(Text::_('COM_VEL_GENERAL_ERROR_MESSAGE_NOT_FOUND'), 400);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__vel_abandoned'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $reportId, ParameterType::INTEGER);
        $report = $db->setQuery($query)->loadObject();

        if (!$report) {
            throw new Exception(Text::_('COM_VEL_GENERAL_ERROR_MESSAGE_NOT_FOUND'), 404);
        }

        $velItemId = (int) $report->vel_item_id;

        if (!$velItemId) {
            $table = new VulnerableitemTable($db);
            $table->bind([
                'id'                      => 0,
                'vulnerable_item_name'    => $report->extension_name,
                'vulnerable_item_version' => $report->extension_version,
                'title'                   => trim($report->extension_name . ', ' . $report->extension_version . ', Abandoned', ', '),
                'status'                  => 3, // COM_VEL_GENERAL_STATUS_OPTION_3 = Abandoned
                'report_id'               => $reportId,
                'exploit_type'            => 5,
                'discovered_by'           => $report->reporter_fullname,
            ]);
            $table->store();
            $velItemId = (int) $table->id;

            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__vel_abandoned'))
                ->set($db->quoteName('passed_to_vel') . ' = 1')
                ->set($db->quoteName('vel_item_id') . ' = :velItemId')
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':velItemId', $velItemId, ParameterType::INTEGER)
                ->bind(':id', $reportId, ParameterType::INTEGER);
            $db->setQuery($updateQuery)->execute();
        }

        $app->enqueueMessage(Text::_('COM_VEL_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));
        $this->setRedirect(
            Route::_('index.php?option=com_vel&view=vulnerableitem&task=vulnerableitem.edit&id=' . $velItemId, false)
        );
    }

    /**
     * "Close": marks the report as closed without escalating it to a vulnerable item -
     * unpublishes it and flags it as processed, the only two status-like columns
     * `#__vel_abandoned` has.
     *
     * @return void
     *
     * @since 4.1.0
     * @throws Exception
     */
    public function closeReport(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.edit', 'com_vel')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $reportId = $this->input->getInt('linked_item_id');

        if (!$reportId) {
            throw new Exception(Text::_('COM_VEL_GENERAL_ERROR_MESSAGE_NOT_FOUND'), 400);
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__vel_abandoned'))
            ->set($db->quoteName('state') . ' = 0')
            ->set($db->quoteName('passed_to_vel') . ' = 1')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $reportId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        $app->enqueueMessage(Text::_('COM_VEL_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));
        $this->setRedirect(Route::_('index.php?option=com_tickets&view=tickets', false));
    }
}
