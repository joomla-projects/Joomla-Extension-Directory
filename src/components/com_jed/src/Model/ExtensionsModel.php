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

use Exception;
use Jed\Component\Jed\Administrator\Browse\BrowseList;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Jed records.
 *
 * @since 4.0.0
 */
class ExtensionsModel extends ListModel
{
    use ExtensionUtilities;

    /**
     * Constructor.
     *
     * @param array $config An optional associative array of configuration settings.
     *
     * @see    JController
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
                'catid', 'a.catid',
                'state', 'a.state',
                'created_by', 'a.created_by',
                'modified_by', 'a.modified_by',
                'created', 'a.created',
                'modified', 'a.modified',
                'joomla_versions', 'a.joomla_versions',
                'popular', 'a.popular',
                'requires_registration', 'a.requires_registration',
                'video', 'a.video',
                'extension_version', 'a.extension_version',
                'uses_updater', 'a.uses_updater',
                'extension_types', 'a.extension_types',
                'approved', 'a.approved',
                'approved_time', 'a.approved_time',
                'logo', 'a.logo',
                'approved_notes', 'a.approved_notes',
                'approved_reason', 'a.approved_reason',
                'score_overall',
                'score_count',
            ];
        }

        parent::__construct($config);
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
    protected function populateState($ordering = 'a.id', $direction = 'DESC'): void
    {
        $app = Factory::getApplication();

        $this->setState($this->context . 'catid', $app->getInput()->getInt('id', 0));

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);

        // "Compatible with J4/J5/J6": filters on the joomla_versions column against the ids in
        // #__jed_joomla_versions (40/50/51 = J4/J5/J5 b-c, 60/61 = J6/J6 b-c). See getListQuery().
        $joomlaVersion = $this->getUserStateFromRequest($this->context . '.filter.joomla_version', 'filter_joomla_version', '');
        $this->setState('filter.joomla_version', $joomlaVersion);

        // Split context into component and optional section
        $parts = FieldsHelper::extract($context);

        if ($parts) {
            $this->setState('filter.component', $parts[0]);
            $this->setState('filter.section', $parts[1]);
        }

        // List state information: reads the standard "list_fullordering" request var (e.g.
        // "score_overall DESC") and validates it against $this->filter_fields, falling back to
        // the $ordering/$direction defaults above.
        parent::populateState($ordering, $direction);

        // A browse list is a property of the *menu item*, not of the URL (P1-13). Read after
        // parent::populateState() so it wins: the whole point of these being menu items rather
        // than list_fullordering query strings is that "Top Rated" is a stable, routable,
        // cacheable address instead of a sort somebody can be linked into halfway.
        // 18 / 36 / 54 / 72, and 36 by default (P1-13 item 6). Joomla's global `list_limit` is 20,
        // which is not one of the offered sizes and leaves the last row of the card grid ragged
        // at every breakpoint - and because the offered sizes come from the filter form while the
        // *applied* limit comes from global configuration, the two silently disagreed.
        //
        // Read straight off the registry, not through getState(): this method's own docblock says
        // getState() here recurses, and it does - it re-enters populateState(), reads a limit that
        // is not set yet, and the list ends up unbounded. The first attempt at this rendered all
        // 5,583 listings into a 172 MB page.
        if (!\in_array((int) $this->state->get('list.limit'), [18, 36, 54, 72], true)) {
            $this->state->set('list.limit', 36);
        }

        $browse = BrowseList::fromKey((string) $app->getParams()->get('browse_list', ''));

        if ($browse !== null) {
            [$column, $dirn] = array_pad(explode(' ', $browse->ordering(), 2), 2, 'DESC');

            $this->setState('list.browse', $browse->value);
            $this->setState('list.ordering', $column);
            $this->setState('list.direction', strtoupper($dirn) === 'ASC' ? 'ASC' : 'DESC');
        }
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return QueryInterface
     *
     * @since  4.0.0
     * @throws Exception
     */
    protected function getListQuery(): QueryInterface
    {
        // Create a new query object.
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'DISTINCT a.*'
            )
        );

        $query->from('#__jed_extensions AS a');

        $query->select('cat.title AS category_title');
        $query->join('INNER', '#__categories AS cat ON cat.id=a.catid');
        // Join over the users for the checked out user.
        $query->select('uc.name AS uEditor');
        $query->join('LEFT', '#__users AS uc ON uc.id=a.checked_out');

        // Join over the created by field 'created_by'
        $query->select('created_by.name AS developer');
        $query->join('LEFT', '#__users AS created_by ON created_by.id = a.created_by');

        // Join over the modified by field 'modified_by'
        $query->join('LEFT', '#__users AS modified_by ON modified_by.id = a.modified_by');

        /*
         * The bookmark flag is deliberately NOT joined here any more (P1-13).
         *
         * It was the only thing in this query that depended on who was asking, and that one
         * column made every browse page a different document per visitor - so Joomla's page cache
         * could never serve any of them, on the busiest pages of the site, at roughly a second
         * each over 5,583 listings. The list is now identical for everyone and therefore
         * cacheable; `com_jed.favoritestate` fetches the visitor's own bookmarks afterwards and
         * fills the icons in.
         *
         * The cost is one small request for signed-in visitors, who are a small minority of the
         * traffic. The gain applies to everybody else.
         */

        // Approved by the JED team AND online per the developer, plus the current user's own
        // listings. Backend permissions do not widen this - see JedHelper for the rule.
        $query->where(JedHelper::getExtensionVisibilityCondition($db));

        // Filter by search in name
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos((string) $search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr((string) $search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                $query->where('(a.name LIKE ' . $search . ')');
            }
        }

        $category = $this->state->get($this->context . 'catid');
        if (!empty($category)) {
            $query->where('a.catid = ' . (int) $category);
        }

        // "Compatible with J4/J5/J6": a.joomla_versions stores a JSON-ish array of
        // #__jed_joomla_versions ids, e.g. ["40","50"], so a quoted LIKE reliably matches a
        // single id regardless of how many other versions are listed alongside it.
        $joomlaVersion = $this->getState('filter.joomla_version');
        if (!empty($joomlaVersion)) {
            $query->where('a.joomla_versions LIKE ' . $db->quote('%"' . $db->escape($joomlaVersion, true) . '"%'));
        }

        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'DESC');

        // "New & Noteworthy" is not a sort of this table at all - it is what people have been
        // looking at in the last fortnight, so it joins P1-12's aggregate. An INNER JOIN, because
        // a listing nobody has viewed is not noteworthy; that is the definition, not an omission.
        if ($orderCol === 'noteworthy') {
            $since = Factory::getDate('-' . BrowseList::NOTEWORTHY_DAYS . ' days')->format('Y-m-d');

            $query->select('hits.views AS noteworthy')
                ->join(
                    'INNER',
                    '(SELECT ' . $db->quoteName('extension_id') . ', SUM(' . $db->quoteName('views') . ') AS '
                    . $db->quoteName('views') . ' FROM ' . $db->quoteName('#__jed_hit_stats')
                    . ' WHERE ' . $db->quoteName('period') . ' >= ' . $db->quote($since)
                    . ' GROUP BY ' . $db->quoteName('extension_id')
                    . ' HAVING SUM(' . $db->quoteName('views') . ') > 0) AS hits ON hits.extension_id = a.id'
                );

            $query->order($db->quoteName('noteworthy') . ' ' . ($orderDirn === 'ASC' ? 'ASC' : 'DESC'))
                ->order($db->quoteName('a.id') . ' ASC');

            return $query;
        }

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        // A browse list has to answer the same way twice, and the same way as the module of the
        // same name. Several hundred listings share a score_overall of 5.00, and without this the
        // page and the sidebar showed different "Top Rated" extensions.
        $browse = BrowseList::fromKey((string) $this->getState('list.browse', ''));

        if ($browse !== null) {
            $query->order($browse->tieBreak());
        }

        return $query;
    }

    /**
     * getMyItems
     *
     * Returns list of extensions created by the current user
     *
     * @return mixed
     * @since  1.0
     * @throws Exception
     */
    public function getMyItems(): mixed
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.id as ext_id,a.*,cat.title AS category_title')
            ->from('#__jed_extensions AS a')
            ->innerJoin('#__categories AS cat ON cat.id = a.catid')
            // "Mine" is what I own or maintain (8.8), not what I once created. Filtering on
            // created_by missed every extension the user maintains, and kept listing the ones
            // they had transferred away.
            ->where(JedHelper::getOwnedOrMaintainedCondition($db))
            ->where($db->quoteName('a.deleted') . ' = 0');
        $db->setQuery($query);

        return $db->loadObjectList();
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

        foreach ($items as $item) {
            //echo "<pre>";print_r($item);echo "</pre>";exit();

            $item->category_hierarchy = $this->getCategoryHierarchy($item->catid);

            if (!empty($item->logo)) {
                $item->logo = JedHelper::formatImage($item->logo, ImageSize::SMALL);
            }

            $item->number_of_reviews = (int) $item->score_count;
            $item->score             = (float) $item->score_overall;
            // score_overall is a 0-5 value (decimal(3,2))
            $item->score_string = JedscoreHelper::getStars((float) $item->score_overall);

            if ($item->number_of_reviews == 0) {
                $item->review_string = '';
            } elseif ($item->number_of_reviews == 1) {
                $item->review_string = '<span>' . $item->number_of_reviews . ' review</span>';
            } else {
                $item->review_string = '<span>' . $item->number_of_reviews . ' reviews</span>';
            }

            // https://extensions.joomla.org/cache/fab_image/27824_resizeDown400px175px16.png

            if (!empty($item->uses_updater)) {
                $item->uses_updater = Text::_('COM_JED_EXTENSION_USES_UPDATER_OPTION_' . strtoupper((string) $item->uses_updater));
            }
            $item->version = JedtrophyHelper::getTrophyVersionsString($item->joomla_versions);
        }

        // Ordering is now applied in SQL (see getListQuery()) according to list.ordering/
        // list.direction, so the page's items are already in the requested order; no PHP-side
        // re-sort here.
        return array_values($items);
    }

    /**
     * Overrides the default function to check Date fields format, identified by
     * "_dateformat" suffix, and erases the field if it's not correct.
     *
     * @return mixed
     *
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData(): mixed
    {
        $app              = Factory::getApplication();
        $filters          = $app->getUserState($this->context . '.filter', []);
        $error_dateformat = false;

        foreach ($filters as $key => $value) {
            if (strpos((string) $key, '_dateformat') && !empty($value) && JedHelper::isValidDate($value) == null) {
                $filters[$key]    = '';
                $error_dateformat = true;
            }
        }

        if ($error_dateformat) {
            $app->enqueueMessage(Text::_("COM_JED_SEARCH_FILTER_DATE_FORMAT"), "warning");
            $app->setUserState($this->context . '.filter', $filters);
        }

        return parent::loadFormData();
    }


    /**
     * Retrieve a list of developers matching a search query.
     *
     * @param string $search The string to filter on
     *
     * @return array List of developers.
     *
     * @since 4.0.0
     */
    public function getDevelopers(string $search): array
    {
        $db    =  Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(
                $db->quoteName(
                    [
                        'users.id',
                        'users.name',
                    ]
                )
            )
            ->from($db->quoteName('#__users', 'users'))
            ->leftJoin(
                $db->quoteName('#__jed_extensions', 'extensions')
                . ' ON ' . $db->quoteName('extensions.created_by') . ' = ' . $db->quoteName('users.id')
            )
            ->where($db->quoteName('users.name') . ' LIKE ' . $db->quote('%' . $search . '%'))
            ->group($db->quoteName('users.id'))
            ->order($db->quoteName('users.name'));
        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Get the used extension types.
     *
     * @param int $extensionId The extension ID to get the types for
     *
     * @return array  List of used extension types.
     *
     * @since 4.0.0
     */
    public function getExtensionTypes(int $extensionId): array
    {
        $db    =  Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_types'))
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = ' . $extensionId);
        $db->setQuery($query);

        $types = json_decode((string) $db->loadResult(), true);

        return is_array($types) ? $types : [];
    }

    /**
     * Get the images.
     *
     * @param int $extensionId The extension ID to get the images for
     *
     * @return array  List of used images.
     *
     * @since 4.0.0
     */
    public function getImages(int $extensionId): array
    {
        $db    =  Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('filename'))
            ->from($db->quoteName('#__jed_extensions_images'))
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId)
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);

        $items  = $db->loadObjectList();
        $images = [];

        array_walk(
            $items,
            static function ($item, $key) use (&$images) {
                $images['images' . $key]['image'] = JedHelper::formatImage($item->filename, ImageSize::SMALL);
            }
        );

        return $images;
    }


    /**
     * Get the related categories.
     *
     * @param int $extensionId The extension ID to get the categories for
     *
     * @return array  List of related categories.
     *
     * @since 4.0.0
     */
    public function getRelatedCategories(int $extensionId): array
    {
        $db    =  Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__jed_extensions_category_map'))
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query);

        return $db->loadColumn();
    }
}
