<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Reviews records.
 *
 * @since 4.0.0
 */
class ReviewsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param array $config An optional associative array of configuration settings.
     *
     * @see    ListModel
     * @throws Exception
     * @since  4.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'extension_id', 'a.extension_id',
                'supply_option_id', 'a.supply_option_id',
                'title', 'a.title',
                'alias', 'a.alias',
                'body', 'a.body',
                'functionality', 'a.functionality',
                'functionality_comment', 'a.functionality_comment',
                'ease_of_use', 'a.ease_of_use',
                'ease_of_use_comment', 'a.ease_of_use_comment',
                'support', 'a.support',
                'support_comment', 'a.support_comment',
                'documentation', 'a.documentation',
                'documentation_comment', 'a.documentation_comment',
                'value_for_money', 'a.value_for_money',
                'value_for_money_comment', 'a.value_for_money_comment',
                'overall_score', 'a.overall_score',
                'used_for', 'a.used_for',
                'flagged', 'a.flagged',
                'ip_address', 'a.ip_address',
                'published', 'a.published',
                'created_on', 'a.created_on',
                'created_by', 'a.created_by',
                'ordering', 'a.ordering',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     *
     * @since 4.0.0
     */
    public function getItems(): mixed
    {
        $items = parent::getItems();

        $db    = $this->getDatabase();

        foreach ($items as $oneItem) {
            if (isset($oneItem->extension_id)) {
                $values    = explode(',', $oneItem->extension_id);
                $textValue = [];

                foreach ($values as $value) {
                    $query = $db->getQuery(true);
                    $query
                        ->select('`#__jed_extensions_3715042`.`title`')
                        ->from($db->quoteName('#__jed_extensions', '#__jed_extensions_3715042'))
                        ->where($db->quoteName('#__jed_extensions_3715042.id') . ' = ' . $db->quote($db->escape($value)));

                    $db->setQuery($query);
                    $results = $db->loadObject();

                    if ($results) {
                        $textValue[] = $results->title;
                    }
                }

                $oneItem->extension_id = !empty($textValue) ? implode(', ', $textValue) : $oneItem->extension_id;
            }

            if (isset($oneItem->supply_option_id)) {
                $values    = explode(',', $oneItem->supply_option_id);
                $textValue = [];

                foreach ($values as $value) {
                    $query = $db->getQuery(true);
                    $query
                        ->select('`#__jed_extension_supply_options_3727708`.`title`')
                        ->from($db->quoteName('#__jed_extension_supply_options', '#__jed_extension_supply_options_3727708'))
                        ->where($db->quoteName('#__jed_extension_supply_options_3727708.id') . ' = ' . $db->quote($db->escape($value)));

                    $db->setQuery($query);
                    $results = $db->loadObject();

                    if ($results) {
                        $textValue[] = $results->title;
                    }
                }

                $oneItem->supply_option_id = !empty($textValue) ? implode(', ', $textValue) : $oneItem->supply_option_id;
            }
        }

        return $items;
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return QueryInterface
     *
     * @since 4.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        // Create a new query object.
        $db = $this->getDatabase();

        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'DISTINCT a.*'
            )
        );
        $query->from('`#__jed_reviews` AS a');

        // Join over the users for the checked out user
        $query->select("uc.name AS uEditor");
        $query->join("LEFT", "#__users AS uc ON uc.id=a.checked_out");

        // Join over the foreign key 'extension_id'
        $query->select('`#__jed_extensions_3715042`.`title` AS extensions_fk_value_3715042');
        $query->join('LEFT', '#__jed_extensions AS #__jed_extensions_3715042 ON #__jed_extensions_3715042.`id` = a.`extension_id`');
        // Join over the foreign key 'supply_option_id'
        $query->select('`#__jed_extension_supply_options_3727708`.`title` AS extensionsupplyoptions_fk_value_3727708');
        $query->join('LEFT', '#__jed_extension_supply_options AS #__jed_extension_supply_options_3727708 ON #__jed_extension_supply_options_3727708.`id` = a.`supply_option_id`');

        // Join over the user field 'created_by'
        $query->select('`created_by`.name AS `created_by`');
        $query->join('LEFT', '#__users AS `created_by` ON `created_by`.id = a.`created_by`');


        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                $query->where('( a.title LIKE ' . $search . '  OR  a.functionality_comment LIKE ' . $search . ' )');
            }
        }

        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering', 'id');
        $orderDirn = $this->state->get('list.direction', 'DESC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param string $id A prefix for the store id.
     *
     * @return string A store id.
     *
     * @since 4.0.0
     */
    protected function getStoreId($id = ''): string
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');


        return parent::getStoreId($id);
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
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function populateState($ordering = null, $direction = null): void
    {
        // List state information.
        parent::populateState('id', 'DESC');

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
