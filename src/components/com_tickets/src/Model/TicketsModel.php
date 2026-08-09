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
     * The column the list falls back to when nothing else has been asked for: newest first.
     *
     * @var string
     *
     * @since 4.0.0
     */
    private const DEFAULT_ORDERING = 'a.`created_on`';

    /**
     * The default ordering direction that goes with {@see self::DEFAULT_ORDERING}.
     *
     * @var string
     *
     * @since 4.0.0
     */
    private const DEFAULT_DIRECTION = 'DESC';

    /**
     * The `ticket_status` value that means "Resolved".
     *
     * Kept as a string because `#__jed_tickets.ticket_status` is a varchar. See
     * forms/filter_tickets.xml for the full vocabulary.
     *
     * @var string
     *
     * @since 4.0.0
     */
    private const STATUS_RESOLVED = '3';

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
            /*
             * Both spellings are listed on purpose. ListModel::populateState() validates the
             * submitted ordering with a plain in_array() against this list, and the sort links in
             * tmpl/tickets/default.php as well as the fullordering options in
             * forms/filter_tickets.xml use the quoted form. Listing only the bare names made every
             * sort request fail that check and fall back to the default, which is what made
             * ordering look broken.
             */
            $config['filter_fields'] = [
                'id', 'a.id', 'a.`id`',
                'ticket_origin', 'a.ticket_origin', 'a.`ticket_origin`',
                'ticket_category_type', 'a.ticket_category_type', 'a.`ticket_category_type`',
                'ticket_subject', 'a.ticket_subject', 'a.`ticket_subject`',
                'ticket_text', 'a.ticket_text', 'a.`ticket_text`',
                'internal_notes', 'a.internal_notes', 'a.`internal_notes`',
                'uploaded_files_preview', 'a.uploaded_files_preview', 'a.`uploaded_files_preview`',
                'uploaded_files_location', 'a.uploaded_files_location', 'a.`uploaded_files_location`',
                'allocated_group', 'a.allocated_group', 'a.`allocated_group`',
                'allocated_to', 'a.allocated_to', 'a.`allocated_to`',
                'linked_item_type', 'a.linked_item_type', 'a.`linked_item_type`',
                'linked_item_id', 'a.linked_item_id', 'a.`linked_item_id`',
                'ticket_status', 'a.ticket_status', 'a.`ticket_status`',
                'parent_id', 'a.parent_id', 'a.`parent_id`',
                'state', 'a.state', 'a.`state`',
                'ordering', 'a.ordering', 'a.`ordering`',
                'created_by', 'a.created_by', 'a.`created_by`',
                'created_on', 'a.created_on', 'a.`created_on`',
                'modified_by', 'a.modified_by', 'a.`modified_by`',
                'modified_on', 'a.modified_on', 'a.`modified_on`',
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

        $query->from($db->quoteName('#__jed_tickets', 'a'));

        // Join over the users for the checked out user.
        $query->select('uc.name AS uEditor');
        $query->leftJoin($db->quoteName('#__users', 'uc'), 'uc.id=a.checked_out');
        // Join over the foreign key 'ticket_category_type'
        $query->select('`jtc`.`categorytype` AS categorytype_string');
        $query->leftJoin($db->quoteName('#__jed_ticket_categories', 'jtc'), 'jtc.`id` = a.`ticket_category_type`');
        // Join over the foreign key 'allocated_group'
        $query->select('`jtg`.`name` AS ticketallocatedgroup_string');
        $query->leftJoin($db->quoteName('#__jed_ticket_groups', 'jtg'), 'jtg.`id` = a.`allocated_group`');

        // Join over the user field 'allocated_to'
        $query->select('`allocated_to`.name AS `allocated_to`');
        $query->leftJoin($db->quoteName('#__users', 'allocated_to'), '`allocated_to`.id = a.`allocated_to`');

        // Join over the created by field 'created_by'
        $query->leftJoin($db->quoteName('#__users', 'created_by'), 'created_by.id = a.created_by');

        // Join over the created by field 'modified_by'
        $query->leftJoin($db->quoteName('#__users', 'modified_by'), 'modified_by.id = a.modified_by');

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
            $states    = [0,1,2];

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
        $filter_ticket_category_type = (int) $this->getState('filter.ticket_category_type');

        if ($filter_ticket_category_type > 0) {
            $query->where($db->quoteName('a.ticket_category_type') . ' = :categoryType')
                ->bind(':categoryType', $filter_ticket_category_type, ParameterType::INTEGER);
        }

        /*
         * Filtering ticket_status.
         *
         * With no status picked the list leaves out Resolved tickets. This is a queue of things
         * still wanting attention, and two thirds of the table is resolved, so including them by
         * default buries everything that is not. Picking "All" or "Resolved" brings them back.
         */
        $filter_ticket_status = (string) $this->getState('filter.ticket_status', '');

        if ($filter_ticket_status === '') {
            $resolved = self::STATUS_RESOLVED;
            $query->where($db->quoteName('a.ticket_status') . ' <> :notResolved')
                ->bind(':notResolved', $resolved);
        } elseif ($filter_ticket_status !== '*') {
            $query->where($db->quoteName('a.ticket_status') . ' = :ticketStatus')
                ->bind(':ticketStatus', $filter_ticket_status);
        }

        /*
         * Add the list ordering clause.
         *
         * The values come out of filter_fields, so they are already known-good column names; the
         * fallbacks are here only so that an empty state cannot leave the query without an ORDER
         * BY, which is what used to happen and left the row order up to MySQL.
         */
        $orderCol  = (string) $this->getState('list.ordering', self::DEFAULT_ORDERING);
        $orderDirn = strtoupper((string) $this->getState('list.direction', self::DEFAULT_DIRECTION));

        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = self::DEFAULT_ORDERING;
        }

        if (!in_array($orderDirn, ['ASC', 'DESC'], true)) {
            $orderDirn = self::DEFAULT_DIRECTION;
        }

        $query->order($db->escape($orderCol) . ' ' . $orderDirn);

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
        /*
         * Ordering is left entirely to ListModel. What used to stand here read `filter_order` out
         * of the user state, wrote its own `list` array back, and then cleared the `list` input
         * with $app->getInput()->set('list', null) *before* calling the parent - so the
         * list[fullordering] value that both the sort dropdown and the column headers submit was
         * thrown away before anything could read it, and the list never reordered.
         */
        parent::populateState(self::DEFAULT_ORDERING, self::DEFAULT_DIRECTION);

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);

        // Split context into component and optional section
        $parts = FieldsHelper::extract($context);

        if ($parts) {
            $this->setState('filter.component', $parts[0]);
            $this->setState('filter.section', $parts[1]);
        }
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * The filters have to be part of the id, otherwise two different filter combinations asked for
     * in the same request would be served the same cached result set.
     *
     * @param string $id A prefix for the store id.
     *
     * @return string A store id.
     *
     * @since 4.0.0
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.ticket_status');
        $id .= ':' . $this->getState('filter.ticket_category_type');
        $id .= ':' . $this->getState('filter.ticket_origin');

        return parent::getStoreId($id);
    }
}
