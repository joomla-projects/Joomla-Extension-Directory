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
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Site\Helper\JedcategoryHelper;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\Exception\DatabaseNotFoundException;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Extensions and Category records.
 *
 * @since 4.0.0
 */
class CategoryModel extends ListModel
{
    use ExtensionUtilities;

    /**
     * Category items data
     *
     * @var   array
     * @since 4.0.0
     */
    protected array $l_category_item = [];

    /**
     * Array of child-categories
     *
     * @var   CategoryNode[]|null|bool
     * @since 4.0.0
     */
    protected ?array $l_category_children = null;

    /**
     * Parent category of the current one
     *
     * @var   bool|CategoryNode|null
     * @since 4.0.0
     */
    protected CategoryNode|bool|null $l_category_parent = null;

    /**
     * Array of checked categories -- used to save values when _nodes are null
     *
     * @var   array
     * @since 1.6
     */
    protected array $l_checkedCategories = [];

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
     * @since  4.0.0
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null): void
    {

        /* @var $app \Joomla\CMS\Application\SiteApplication */
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        $pk  = $app->getInput()->getInt('id');
        $this->setState($this->context . 'category.id', $pk);

        // Load the parameters. Merge Global and Menu Item params into new object
        $params = $app->getParams();
        $this->setState('params', $params);

        $user  = $this->getCurrentUser();
        $asset = 'com_content';

        if ($pk) {
            $asset .= '.category.' . $pk;
        }

        if ((!$user->authorise('core.edit.state', $asset)) && (!$user->authorise('core.edit', $asset))) {
            // Limit to published for people who can't edit or edit.state.
            $this->setState('filter.published', 1);
        } else {
            $this->setState('filter.published', [0, 1]);
        }

        // Process show_noauth parameter
        if (!$params->get('show_noauth')) {
            $this->setState('filter.access', true);
        } else {
            $this->setState('filter.access', false);
        }

        $itemid = $app->getInput()->get('id', 0, 'int') . ':' . $app->getInput()->get('Itemid', 0, 'int');

        $value = $this->getUserStateFromRequest('com_content.category.filter.' . $itemid . '.tag', 'filter_tag', 0, 'int', false);
        $this->setState('filter.tag', $value);

        // Optional filter text
        $search = $app->getUserStateFromRequest('com_content.category.list.' . $itemid . '.filter-search', 'filter-search', '', 'string');
        $this->setState('list.filter', $search);

        // Filter.order
        $orderCol = $app->getUserStateFromRequest('com_content.category.list.' . $itemid . '.filter_order', 'filter_order', '', 'string');

        if (!\in_array($orderCol, $this->filter_fields)) {
            $orderCol = 'a.ordering';
        }

        $this->setState('list.ordering', $orderCol);

        $listOrder = $app->getUserStateFromRequest('com_content.category.list.' . $itemid . '.filter_order_Dir', 'filter_order_Dir', '', 'cmd');

        if (!\in_array(strtoupper((string) $listOrder), ['ASC', 'DESC', ''])) {
            $listOrder = 'ASC';
        }

        $this->setState('list.direction', $listOrder);

        $this->setState('list.start', $app->getInput()->get('limitstart', 0, 'uint'));

        // Set limit for query. If list, use parameter. If blog, add blog parameters for limit.
        if (($app->getInput()->get('layout') === 'blog') || $params->get('layout_type') === 'blog') {
            $limit = $params->get('num_leading_articles') + $params->get('num_intro_articles') + $params->get('num_links');
            $this->setState('list.links', $params->get('num_links'));
        } else {
            $limit = $app->getUserStateFromRequest('com_content.category.list.' . $itemid . '.limit', 'limit', $params->get('display_num'), 'uint');
        }

        $this->setState('list.limit', $limit);

        // Set the depth of the category query based on parameter
        $showSubcategories = $params->get('show_subcategory_content', '0');

        if ($showSubcategories) {
            $this->setState('filter.max_category_levels', $params->get('show_subcategory_content', '1'));
            $this->setState('filter.subcategories', true);
        }

        $this->setState('filter.language', Multilanguage::isEnabled());

        $this->setState('layout', $app->getInput()->getString('layout'));

        // Set the featured articles state
        $this->setState('filter.featured', $params->get('show_featured'));
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

        $query->from($db->quoteName('#__jed_extensions', 'a'));

        $query->select('cat.title AS category_title');
        $query->innerJoin($db->quoteName('#__categories', 'cat'), 'cat.id=a.catid');
        // Join over the users for the checked out user.
        $query->select('uc.name AS uEditor');
        $query->leftJoin($db->quoteName('#__users', 'uc'), 'uc.id=a.checked_out');

        // Join over the created by field 'created_by'
        $query->select('created_by.name AS developer');
        $query->leftJoin($db->quoteName('#__users', 'created_by'), 'created_by.id = a.created_by');

        // Join over the created by field 'modified_by'
        $query->leftJoin($db->quoteName('#__users', 'modified_by'), 'modified_by.id = a.modified_by');

        // Flag whether the current user has bookmarked each extension, for the card's favorite icon.
        $favUserId = (int) (Factory::getApplication()->getIdentity()->id ?? 0);
        $query->select('(fav.id IS NOT NULL) AS is_favorited');
        $query->leftJoin($db->quoteName('#__jed_favorites', 'fav'), 'fav.extension_id = a.id AND fav.user_id =' . $db->quote($favUserId));

        // Approved by the JED team AND online per the developer, plus the current user's own
        // listings. Backend permissions do not widen this - see JedHelper for the rule.
        $query->where(JedHelper::getExtensionVisibilityCondition($db));

        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos((string) $search, 'id:') === 0) {
                $query->where('a.id = ' . (int)substr((string) $search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                $query->where('(a.name LIKE ' . $search . ')');
            }
        }


        $category = $this->state->get($this->context . 'category.id');
        if (!empty($category)) {
            $query->where('a.catid =' . $category);
        }

        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
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
            $item->category_hierarchy = $this->getCategoryHierarchy($item->catid);

            if (!empty($item->logo)) {
                $item->logo = JedHelper::formatImage($item->logo, ImageSize::SMALL);
            }

            $item->number_of_reviews = (int) $item->score_count;
            $item->score             = (float) $item->score_overall;
            // score_overall is a 0-5 value (decimal(3,2))
            $item->score_string = JedscoreHelper::getStars($item->score);

            if ($item->number_of_reviews == 0) {
                $item->review_string = '';
            } elseif ($item->number_of_reviews == 1) {
                $item->review_string = '<span>' . $item->number_of_reviews . ' review</span>';
            } else {
                $item->review_string = '<span>' . $item->number_of_reviews . ' reviews</span>';
            }

            if (!empty($item->uses_updater)) {
                $item->uses_updater = Text::_('COM_JED_EXTENSION_USES_UPDATER_OPTION_' . strtoupper((string) $item->uses_updater));
            }
            $item->version = JedtrophyHelper::getTrophyVersionsString($item->joomla_versions);
        }

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
        /* @var $app \Joomla\CMS\Application\SiteApplication */

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
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)->select($db->quoteName('filename'))->from($db->quoteName('#__jed_extensions_images'))->where($db->quoteName('extension_id') . ' = ' . $extensionId)->order($db->quoteName('ordering'));
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

    /* Below code modified from com_content_category_model */
    /**
     * Method to get category data for the current category
     *
     * @return array
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getCategory(): array
    {
        $db         = $this->getDatabase();
        $id         = $this->getRequestedCategoryKey();
        $extension  = 'com_jed';
        $categories = [];

        if ($id !== null) {

            // Record that has this $id has been checked
            $this->l_checkedCategories[$id] = true;

            $query = $db->getQuery(true)->select(
                [
                        $db->quoteName('c.id'),
                        $db->quoteName('c.asset_id'),
                        $db->quoteName('c.access'),
                        $db->quoteName('c.alias'),
                        $db->quoteName('c.checked_out'),
                        $db->quoteName('c.checked_out_time'),
                        $db->quoteName('c.created_time'),
                        $db->quoteName('c.created_user_id'),
                        $db->quoteName('c.description'),
                        $db->quoteName('c.extension'),
                        $db->quoteName('c.hits'),
                        $db->quoteName('c.language'),
                        $db->quoteName('c.level'),
                        $db->quoteName('c.lft'),
                        $db->quoteName('c.metadata'),
                        $db->quoteName('c.metadesc'),
                        $db->quoteName('c.metakey'),
                        $db->quoteName('c.modified_time'),
                        $db->quoteName('c.note'),
                        $db->quoteName('c.params'),
                        $db->quoteName('c.parent_id'),
                        $db->quoteName('c.path'),
                        $db->quoteName('c.published'),
                        $db->quoteName('c.rgt'),
                        $db->quoteName('c.title'),
                        $db->quoteName('c.modified_user_id'),
                        $db->quoteName('c.version'),
                    ]
            );

            $case_when = ' CASE WHEN ';
            $case_when .= $query->charLength($db->quoteName('c.alias'), '!=', '0');
            $case_when .= ' THEN ';
            $c_id      = $query->castAs('CHAR', $db->quoteName('c.id'));
            $case_when .= $query->concatenate([$c_id, $db->quoteName('c.alias')], ':');
            $case_when .= ' ELSE ';
            $case_when .= $c_id . ' END as ' . $db->quoteName('slug');

            $query->select($case_when)->where('(' . $db->quoteName('c.extension') . ' = :extension OR ' . $db->quoteName('c.extension') . ' = ' . $db->quote('system') . ')')->bind(':extension', $extension);


            $query->where($db->quoteName('c.published') . ' = 1');


            $query->order($db->quoteName('c.lft'));

            // Note: s for selected id
            if ($id !== 'root') {
                // Get the selected category
                $query->from($db->quoteName('#__categories', 's'))->where($db->quoteName('s.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);


                $query->innerJoin(
                    $db->quoteName('#__categories', 'c'),
                    '(' . $db->quoteName('s.lft') . ' <= ' . $db->quoteName('c.lft') . ' AND ' . $db->quoteName('c.lft') . ' < ' . $db->quoteName('s.rgt') . ')' . ' OR (' . $db->quoteName('c.lft') . ' < ' . $db->quoteName('s.lft') . ' AND ' . $db->quoteName('s.rgt') . ' < ' . $db->quoteName('c.rgt') . ')'
                );
            } else {
                $query->from($db->quoteName('#__categories', 'c'));
            }

            $db->setQuery($query);
            $results = $db->loadObjectList('id');

            // Listing counts come from JedcategoryHelper, not from a subquery here: it applies
            // the visibility rule (4.8) and counts the whole subtree, so a badge on this page
            // means the same as the badge for the same category on the overview.
            $counts = JedcategoryHelper::getCounts();

            foreach ($results as $result) {
                $result->numitems = $counts[(int) $result->id] ?? 0;
            }

            $childrenLoaded = false;

            if (\count($results)) {
                // Foreach categories
                foreach ($results as $result) {
                    // Deal with root category
                    if ($result->id == 1) {
                        $result->id = 'root';
                    }

                    // Deal with parent_id
                    if ($result->parent_id == 1) {
                        $result->parent_id = 'root';
                    }

                    // Create the node
                    if (!isset($categories[$result->id])) {
                        // Create the CategoryNode and add to _nodes
                        $categories[$result->id] = new CategoryNode($result, $this);

                        // If this is not root and if the current node's parent is in the list, or the current node parent is 0
                        if ($result->id !== 'root' && (isset($categories[$result->parent_id]) || $result->parent_id == 1)) {
                            // Compute relationship between node and its parent - set the parent in the _nodes field
                            $categories[$result->id]->setParent($categories[$result->parent_id]);
                        }

                        // If the node's parent id is not in the _nodes list and the node is not root (doesn't have parent_id == 0),
                        // then remove the node from the list
                        //  if (!(isset($categories[$result->parent_id]) || $result->parent_id == 0)) {
                        //     unset($categories[$result->id]);
                        //     continue;
                        //   }

                        if ($result->id == $id || $childrenLoaded) {
                            $categories[$result->id]->setAllLoaded();
                            $childrenLoaded = true;
                        }
                    } elseif ($result->id == $id || $childrenLoaded) {
                        // Create the CategoryNode
                        $categories[$result->id] = new CategoryNode($result, $this);

                        if ($result->id !== 'root' && (isset($categories[$result->parent_id]) || $result->parent_id)) {
                            // Compute relationship between node and its parent
                            $categories[$result->id]->setParent($categories[$result->parent_id]);
                        }

                        // If the node's parent id is not in the _nodes list and the node is not root (doesn't have parent_id == 0),
                        // then remove the node from the list
                        //    if (!(isset($categories[$result->parent_id]) || $result->parent_id == 0)) {
                        //        unset($categories[$result->id]);
                        //       continue;
                        //   }

                        if ($result->id == $id || $childrenLoaded) {
                            $categories[$result->id]->setAllLoaded();
                            $childrenLoaded = true;
                        }
                    }
                }
            } else {
                $categories[$id] = null;
            }
        }
        $this->l_category_item = $categories;

        return $categories;
    }

    /**
     * The key the current request's category is stored under in {@see $l_category_item}.
     *
     * The tree is keyed by category id with one exception: Joomla's root is stored as the string
     * 'root'. Reading the raw request id therefore missed the entry for `id=0`, and missed it
     * entirely when no id was given at all - which is how the four accessors below ended up
     * dereferencing null and taking the page down with a 500.
     *
     * @return int|string|null  The array key, or null when the request names no category.
     *
     * @since  4.0.0
     * @throws Exception
     */
    private function getRequestedCategoryKey(): int|string|null
    {
        $id = Factory::getApplication()->getInput()->getInt('id', -1);

        if ($id === -1) {
            return null;
        }

        return $id === 0 ? 'root' : $id;
    }

    /**
     * The loaded node for the current request, or null when there is none.
     *
     * @return CategoryNode|null
     *
     * @since  4.0.0
     * @throws Exception
     */
    private function getCurrentNode(): ?CategoryNode
    {
        // The old guard here was `!is_object($this->l_category_item)`, and $l_category_item is
        // typed array - so it was always true and the tree was reloaded on every accessor call.
        if ($this->l_category_item === []) {
            $this->getCategory();
        }

        $key = $this->getRequestedCategoryKey();

        if ($key === null) {
            return null;
        }

        $node = $this->l_category_item[$key] ?? null;

        return $node instanceof CategoryNode ? $node : null;
    }

    /**
     * The category being viewed, or null when the request names no reachable one.
     *
     * @return CategoryNode|null
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getCurrentCategory(): ?CategoryNode
    {
        return $this->getCurrentNode();
    }

    /**
     * Get the child categories.
     *
     * @return array  The child categories, empty when the request names no reachable category.
     *
     * @since  1.6
     * @throws Exception
     */
    public function &getChildren(): array
    {
        $node                      = $this->getCurrentNode();
        $this->l_category_children = $node ? $node->getChildren() : [];

        return $this->l_category_children;
    }

    /**
     * Get the parent category.
     *
     * @return CategoryNode|null  The parent, or null when the request names no reachable category.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getParent(): ?CategoryNode
    {
        $node                    = $this->getCurrentNode();
        $this->l_category_parent = $node?->getParent();

        return $this->l_category_parent instanceof CategoryNode ? $this->l_category_parent : null;
    }
}
