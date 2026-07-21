<?php

/**
 * @package JED
 *
 * @subpackage mod_jed_opentickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Module\OpenTickets\Administrator\Helper;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper for mod_jed_opentickets.
 *
 * @since 4.1.0
 */
class OpenTicketsHelper
{
    /**
     * Get the open com_tickets tickets visible to the current user, most recent first.
     *
     * "Available to the current user" mirrors com_tickets' own admin Tickets list: any
     * user authorised to manage/administer com_tickets sees every open ticket - there is
     * no per-user/allocated_to visibility restriction anywhere else in this codebase to
     * mirror, so a user without that authorisation simply sees an empty list.
     *
     * @param Registry                $params The module parameters.
     * @param CMSApplicationInterface $app    The application instance.
     *
     * @return object[] Ticket rows, each with a `link` property to its admin edit view.
     *
     * @since 4.1.0
     */
    public function getTickets(Registry $params, CMSApplicationInterface $app): array
    {
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_tickets') && !$user->authorise('core.admin', 'com_tickets')) {
            return [];
        }

        /** @var \Jed\Component\Tickets\Administrator\Model\TicketsModel $model */
        $model = $app->bootComponent('com_tickets')
            ->getMVCFactory()
            ->createModel('Tickets', 'Administrator', ['ignore_request' => true]);

        // 1 = Open (see forms/ticket.xml's "state" field) - the other four states
        // (Closed/Solved/Spam/Trashed) are deliberately excluded.
        $model->setState('filter.state', 1);
        $model->setState('list.ordering', 'a.created_on');
        $model->setState('list.direction', 'DESC');
        $model->setState('list.start', 0);
        $model->setState('list.limit', (int) $params->get('count', 20));

        $items = $model->getItems();

        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            $item->link = Route::_('index.php?option=com_tickets&task=ticket.edit&id=' . (int) $item->id);
        }

        return $items;
    }
}
