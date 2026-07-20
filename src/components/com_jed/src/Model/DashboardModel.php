<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;

/**
 * Dashboard model.
 *
 * @since 4.0.0
 */
class DashboardModel extends ItemModel
{
    /**
     * @since 4.0.0
     * @throws \Exception
     */
    protected function populateState(): void
    {
        $app    = Factory::getApplication();
        $params = $app->getParams('com_jed');
        $this->setState('params', $params);
    }

    /**
     * @since 4.0.0
     */
    public function getItem($pk = null): array
    {
        return [];
    }

    /**
     * Returns the reviews written by the current user.
     *
     * @return array
     * @since  4.0.0
     * @throws \Exception
     */
    public function getReviews(): array
    {
        $userId = Factory::getApplication()->getIdentity()->id;
        $db     = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('r.id, r.title, r.overall_score, r.published, r.created_on, r.extension_id')
            ->from($db->quoteName('#__jed_reviews', 'r'))
            ->where($db->quoteName('r.created_by') . ' = ' . $db->quote($userId))
            ->order($db->quoteName('r.created_on') . ' DESC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Returns the extensions owned by the current user (owner field).
     *
     * @return array
     * @since  4.0.0
     * @throws \Exception
     */
    public function getExtensions(): array
    {
        $userId = Factory::getApplication()->getIdentity()->id;
        $db     = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('a.id, a.extension_version, a.state, a.created, a.owner')
            ->select('a.name')
            ->select('cat.title AS category_title')
            ->from($db->quoteName('#__jed_extensions', 'a'))
            ->leftJoin(
                $db->quoteName('#__categories', 'cat')
                . ' ON ' . $db->quoteName('cat.id') . ' = ' . $db->quoteName('a.catid')
            )
            ->where($db->quoteName('a.owner') . ' = ' . $db->quote($userId))
            ->order($db->quoteName('a.id') . ' DESC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Returns the tickets created by the current user.
     *
     * @return array
     * @since  4.0.0
     * @throws \Exception
     */
    public function getTickets(): array
    {
        $userId = Factory::getApplication()->getIdentity()->id;
        $db     = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('a.id, a.ticket_subject, a.ticket_status, a.ticket_origin, a.created_on')
            ->select('jtc.categorytype AS categorytype_string')
            ->from($db->quoteName('#__jed_tickets', 'a'))
            ->leftJoin(
                $db->quoteName('#__jed_ticket_categories', 'jtc')
                . ' ON ' . $db->quoteName('jtc.id') . ' = ' . $db->quoteName('a.ticket_category_type')
            )
            ->where($db->quoteName('a.created_by') . ' = ' . $db->quote($userId))
            ->order($db->quoteName('a.created_on') . ' DESC');

        $db->setQuery($query);

        $items = $db->loadObjectList() ?: [];

        foreach ($items as $item) {
            $item->ticket_status = Text::_('COM_JED_TICKETS_TICKET_STATUS_OPTION_' . strtoupper((string) $item->ticket_status));
        }

        return $items;
    }
}
