<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Extensions records.
 *
 * @since 4.0.0
 */
class ExtensionsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param array $config An optional associative array of configuration settings.
     *
     * @see    ListModel
     * @since  4.0.0
     * @throws Exception
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'name', 'a.name',
                'alias', 'a.alias',
                'state', 'a.state',
                'catid', 'a.catid',
                'owner', 'a.owner',
                'created_by', 'a.created_by',
                'created', 'a.created',
                'modified_by', 'a.modified_by',
                'modified', 'a.modified',
                'joomla_versions', 'a.joomla_versions',
                'popular', 'a.popular',
                'requires_registration', 'a.requires_registration',
                'license', 'a.license',
                'video', 'a.video',
                'extension_version', 'a.extension_version',
                'uses_updater', 'a.uses_updater',
                'type', 'a.type',
                'extension_types', 'a.extension_types',
                'approved', 'a.approved',
                'approved_time', 'a.approved_time',
                'approved_notes', 'a.approved_notes',
                'approved_reason', 'a.approved_reason',
            ];
        }

        parent::__construct($config);
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

        $query = $db->getQuery(true)
            ->select(
                $db->quoteName(
                    [
                    'a.id',
                    'a.name',
                    'a.alias',
                    'a.type',
                    'a.created_by',
                    'a.modified',
                    'a.created',
                    'a.checked_out',
                    'a.checked_out_time',
                    'a.approved',
                    'a.state',
                    'a.entry_version',
                    'categories.title',
                    'users.name',
                    'staff.name',
                    ],
                    [
                    'id',
                    'name',
                    'alias',
                    'type',
                    'created_by',
                    'modified',
                    'created',
                    'checked_out',
                    'checked_out_time',
                    'approved',
                    'state',
                    'entry_version',
                    'category',
                    'developer',
                    'editor',
                    ]
                )
            )
            ->from($db->quoteName('#__jed_extensions', 'a'))
            ->leftJoin(
                $db->quoteName('#__categories', 'categories')
                . ' ON ' . $db->quoteName('categories.id') . ' = ' . $db->quoteName('a.catid')
            )
            ->leftJoin(
                $db->quoteName('#__users', 'users')
                . ' ON ' . $db->quoteName('users.id') . ' = ' . $db->quoteName('a.created_by')
            )
            ->leftJoin(
                $db->quoteName('#__users', 'staff')
                . ' ON ' . $db->quoteName('staff.id') . ' = ' . $db->quoteName('a.checked_out')
            )
            ->select(
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__jed_extensions_history') . ' h'
                . ' WHERE ' . $db->quoteName('h.extension_id') . ' = ' . $db->quoteName('a.id') . ') AS '
                . $db->quoteName('versions')
            )
            ->select(
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__jed_reviews') . ' r'
                . ' WHERE ' . $db->quoteName('r.extension_id') . ' = ' . $db->quoteName('a.id') . ') AS '
                . $db->quoteName('reviewCount')
            )
            ->select(
                '(SELECT MAX(h2.id) FROM ' . $db->quoteName('#__jed_extensions_history') . ' h2'
                . ' WHERE ' . $db->quoteName('h2.extension_id') . ' = ' . $db->quoteName('a.id') . ') AS '
                . $db->quoteName('latest_history_id')
            );

        // Filter by published state
        $published = $this->getState('filter.state');

        if (is_numeric($published)) {
            $query->where($db->quoteName('a.state') . ' = ' . (int) $published);
        } elseif ($published === '') {
            $query->where('(' . $db->quoteName('a.state') . ' IN (0, 1))');
        }

        // Filter by search in name
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos((string) $search, 'id:') === 0) {
                $query->where($db->quoteName('a.id') . ' = ' . (int) substr((string) $search, 3));
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where($db->quoteName('a.name') . ' LIKE ' . $search);
            }
        }

        $categoryIds = $this->getState('filter.category_id');

        if ($categoryIds) {
            $query->where($db->quoteName('a.catid') . ' IN (' . implode(',', array_map('intval', (array) $categoryIds)) . ')');
        }

        $approved = $this->getState('filter.approved', '');

        if ($approved !== '') {
            $query->where($db->quoteName('a.approved') . ' = ' . (int) $approved);
        }

        $developerId = $this->getState('filter.developer_id');

        if (is_numeric($developerId)) {
            $query->where($db->quoteName('a.created_by') . ' = ' . (int) $developerId);
        }

        $extensionTypes = $this->getState('filter.extension_types');

        if ($extensionTypes && $extensionTypes[0] !== '') {
            $conditions = [];

            foreach ($extensionTypes as $type) {
                $conditions[] = $db->quoteName('a.extension_types') . ' LIKE ' . $db->quote('%' . $type . '%');
            }

            $query->extendWhere('AND', $conditions, 'OR');
        }

        $type = $this->getState('filter.type', '');

        if ($type !== '') {
            $query->where($db->quoteName('a.type') . ' = ' . $db->quote($type));
        }

        $developer = $this->getState('filter.developer', '');

        if ($developer !== '') {
            $query->where($db->quoteName('users.name') . ' LIKE ' . $db->quote('%' . trim((string) $developer) . '%'));
        }
        $query->group($db->quoteName('a.id'));

        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering', 'a.id');
        $orderDirn = strtoupper((string) $this->state->get('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if ($orderCol && in_array($orderCol, $this->filter_fields, true)) {
            $query->order($db->quoteName($orderCol) . ' ' . $orderDirn);
        } else {
            $query->order($db->quoteName('a.id') . ' DESC');
        }
        //      echo($query->__toString());
        //      exit();
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
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param string $ordering  Elements order
     * @param string $direction Order direction
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null): void
    {
        // List state information.
        parent::populateState('a.id', 'asc');

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
