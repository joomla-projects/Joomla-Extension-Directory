<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\ParameterType;

/**
 * Methods supporting a list of Jed records.
 *
 * @since 4.0.0
 */
class TicketsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param array $config An optional associative array of configuration settings.
     *
     * @see    ListModel
     * @since  4.0.0
     * @throws \Exception
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'ticket_origin', 'a.ticket_origin',
                'ticket_category_type', 'a.ticket_category_type',
                'ticket_subject', 'a.ticket_subject',
                'ticket_text', 'a.ticket_text',
                'internal_notes', 'a.internal_notes',
                'uploaded_files_preview', 'a.uploaded_files_preview',
                'uploaded_files_location', 'a.uploaded_files_location',
                'allocated_group', 'a.allocated_group',
                'allocated_to', 'a.allocated_to',
                'linked_item_type', 'a.linked_item_type',
                'linked_item_id', 'a.linked_item_id',
                'ticket_status', 'a.ticket_status',
                'parent_id', 'a.parent_id',
                'state', 'a.state',
                'ordering', 'a.ordering',
                'created_by', 'a.created_by',
                'created_on', 'a.created_on',
                'modified_by', 'a.modified_by',
                'modified_on', 'a.modified_on',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to get an array of data items
     *
     * @return mixed An array of data on success, false on failure.
     *
     * @since 4.0.0
     */
    public function getItems(): mixed
    {
        $items = parent::getItems();

        foreach ($items as $oneItem) {
            $oneItem->ticket_origin = Text::_('COM_TICKETS_TICKETS_TICKET_ORIGIN_OPTION_' . strtoupper((string) $oneItem->ticket_origin));


            $oneItem->ticket_status = Text::_('COM_TICKETS_TICKETS_TICKET_STATUS_OPTION_' . strtoupper((string) $oneItem->ticket_status));

            $linkedItemType                        = TicketType::tryFrom((int) $oneItem->linked_item_type);
            $oneItem->ticketlinkeditemtypes_string = $linkedItemType !== null
                ? Text::_('COM_TICKETS_TICKETS_LINKED_ITEM_TYPE_OPTION_' . strtoupper($linkedItemType->name))
                : Text::_('COM_TICKETS_TICKETS_LINKED_ITEM_TYPE_OPTION_NONE');
        }

        return $items;
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return object  A \JDatabaseQuery object to retrieve the data set.
     *
     * @since  4.0.0
     * @throws \Exception
     */
    protected function getListQuery(): object
    {
        $user  = $this->getCurrentUser();
        // Create a new query object.
        $db    =  $this->getDatabase();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'a.*'
            )
        );

        $query->from('`#__jed_tickets` AS a');

        // Join over the users for the checked out user.
        $query->select('uc.name AS uEditor');
        $query->join('LEFT', '#__users AS uc ON uc.id=a.checked_out');
        // Join over the foreign key 'ticket_category_type'
        $query->select('`jtc`.`categorytype` AS categorytype_string');
        $query->join('LEFT', '#__jed_ticket_categories AS jtc ON jtc.`id` = a.`ticket_category_type`');
        // Join over the foreign key 'allocated_group'
        $query->select('`jtg`.`name` AS ticketallocatedgroup_string');
        $query->join('LEFT', '#__jed_ticket_groups AS jtg ON jtg.`id` = a.`allocated_group`');

        // Join over the user field 'allocated_to'
        $query->select('`allocated_to`.name AS `allocated_to`');
        $query->join('LEFT', '#__users AS `allocated_to` ON `allocated_to`.id = a.`allocated_to`');

        // Join over the created by field 'created_by'
        $query->leftJoin($db->qn('#__users', 'created_by'), 'created_by.id = a.created_by');

        // Join over the created by field 'modified_by'
        $query->join('LEFT', '#__users AS modified_by ON modified_by.id = a.modified_by');

        if ($user->authorise('core.manage', 'com_tickets')) {
            $published = (string) $this->getState('filter.state');

            if ($published !== '' && is_numeric($published)) {
                $state = (int) $published;
                $query->where($db->quoteName('a.state') . ' = :state')
                    ->bind(':state', $state, ParameterType::INTEGER);
            } else {
                $query->whereIn($db->quoteName('a.state'), [1,2]);
            }
        } else {
            $query->where('a.created_by = ' . $user->id);

            $published = (string) $this->getState('filter.state');
            $states = [0,1,2];

            if ($published !== '' && is_numeric($published) && in_array($published, $states)) {
                $state = (int) $published;
                $query->where($db->quoteName('a.state') . ' = :state')
                    ->bind(':state', $state, ParameterType::INTEGER);
            } else {
                $query->whereIn($db->quoteName('a.state'), [1,2]);
            }
        }

        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos((string) $search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr((string) $search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                $query->where('(jtc.categorytype LIKE ' . $search . '  OR  a.ticket_subject LIKE ' . $search . ' )');
            }
        }


        // Filtering ticket_origin
        $filter_ticket_origin = $this->state->get("filter.ticket_origin");

        if ((is_numeric($filter_ticket_origin) || !empty($filter_ticket_origin))) {
            $query->where("a.`ticket_origin` = '" . $db->escape($filter_ticket_origin) . "'");
        }

        // Filtering ticket_category_type
        $filter_ticket_category_type = $this->state->get("filter.ticket_category_type");

        if (!empty($filter_ticket_category_type)) {
            $query->where("a.`ticket_category_type` = '" . $db->escape($filter_ticket_category_type) . "'");
        }


        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering', 'allocated_group');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    /**
     * Method to autopopulate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param string $ordering  Elements order
     * @param string $direction Order direction
     *
     * @return void
     *
     * @since  4.0.0
     * @throws \Exception
     */
    protected function populateState($ordering = null, $direction = null): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        $list = $app->getUserState($this->context . '.list');

        $ordering  = $list['filter_order'] ?? null;
        $direction = $list['filter_order_Dir'] ?? null;
        if (empty($ordering)) {
            $ordering = $app->getUserStateFromRequest($this->context . '.filter_order', 'filter_order', $app->get('filter_order'));
            if (!in_array($ordering, $this->filter_fields)) {
                $ordering = 'allocated_group';
            }
            $this->setState('list.ordering', $ordering);
        }
        if (empty($direction)) {
            $direction = $app->getUserStateFromRequest($this->context . '.filter_order_Dir', 'filter_order_Dir', $app->get('filter_order_Dir', ''));
            if (!in_array(strtoupper((string) $direction), ['ASC', 'DESC', ''])) {
                $direction = 'ASC';
            }
            $this->setState('list.direction', $direction);
        }

        $list['limit']     = $app->getUserStateFromRequest($this->context . '.list.limit', 'limit', $app->get('list_limit'), 'uint');
        $list['start']     = $app->getInput()->getInt('start', 0);
        $list['ordering']  = $ordering;
        $list['direction'] = $direction;

        $app->setUserState($this->context . '.list', $list);
        $app->getInput()->set('list', null);


        // List state information.

        parent::populateState($ordering, $direction);

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);


        // Split context into component and optional section
        $parts = FieldsHelper::extract($context);

        if ($parts) {
            $this->setState('filter.component', $parts[0]);
            $this->setState('filter.section', $parts[1]);
        }
    }
}
