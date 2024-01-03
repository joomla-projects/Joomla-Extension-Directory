<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
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
     * Parent category of the current one
     *
     * @var   bool|CategoryNode|null
     * @since 4.0.0
     */
    protected CategoryNode|bool|null $l_category_leftsibling = null;
    /**
     * Parent category of the current one
     *
     * @var   CategoryNode|null
     * @since 4.0.0
     */
    protected ?CategoryNode $l_category_rightsibling = null;

    /**
     * Array of checked categories -- used to save values when _nodes are null
     *
     * @var   boolean[]
     * @since 1.6
     */
    protected array $l_checkedCategories;

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
                'id',
                'a.id',
                'title',
                'a.title',
                'alias',
                'a.alias',
                'published',
                'a.published',
                'created_by',
                'a.created_by',
                'modified_by',
                'a.modified_by',
                'created_on',
                'a.created_on',
                'modified_on',
                'a.modified_on',
                'joomla_versions',
                'a.joomla_versions',
                'popular',
                'a.popular',
                'requires_registration',
                'a.requires_registration',
                'gpl_license_type',
                'a.gpl_license_type',
                'jed_internal_note',
                'a.jed_internal_note',
                'can_update',
                'a.can_update',
                'video',
                'a.video',
                'version',
                'a.version',
                'uses_updater',
                'a.uses_updater',
                'includes',
                'a.includes',
                'approved',
                'a.approved',
                'approved_time',
                'a.approved_time',
                'second_contact_email',
                'a.second_contact_email',
                'jed_checked',
                'a.jed_checked',
                'uses_third_party',
                'a.uses_third_party',
                'primary_category_id',
                'a.primary_category_id',
                'logo',
                'a.logo',
                'approved_notes',
                'a.approved_notes',
                'approved_reason',
                'a.approved_reason',
                'published_notes',
                'a.published_notes',
                'published_reason',
                'a.published_reason',
                'state',
                'a.state',
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

        if (!\in_array(strtoupper($listOrder), ['ASC', 'DESC', ''])) {
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

        $query->from('#__jed_extensions AS a');

        $query->select('cat.title AS category_title');
        $query->join('INNER', '#__categories AS cat ON cat.id=a.primary_category_id');
        // Join over the users for the checked out user.
        $query->select('uc.name AS uEditor');
        $query->join('LEFT', '#__users AS uc ON uc.id=a.checked_out');

        // Join over the created by field 'created_by'
        $query->select('created_by.name AS developer');
        $query->join('LEFT', '#__users AS created_by ON created_by.id = a.created_by');

        // Join over the created by field 'modified_by'
        $query->join('LEFT', '#__users AS modified_by ON modified_by.id = a.modified_by');


        if (!Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_jed')) {
            $query->where('a.state = 1');
        } else {
            $query->where('(a.state IN (0, 1))'); //Published 0=unpublished, 1=published, 2=unpublished by author
        }

        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int)substr($search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                $query->where('(title LIKE ' . $search);
            }
        }


        $category = $this->state->get($this->context . 'category.id');
        if (!empty($category)) {
            $query->where('a.primary_category_id =' . $category);
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
     * Get array of review scores for extension
     *
     * @param int $extension_id
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getScores(int $extension_id): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('*')->from($db->quoteName('#__jed_extension_scores'))->where($db->quoteName('extension_id') . ' = ' . $db->quote($extension_id));

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

            $item->category_hierarchy = $this->getCategoryHierarchy($item->primary_category_id);

            if (!empty($item->logo)) {
                $item->logo = JedHelper::formatImage($item->logo, ImageSize::SMALL);
            }

            $item->scores            = $this->getScores($item->id);
            $item->number_of_reviews = 0;
            $score                   = 0;
            $supplycounter           = 0;
            $supplytype              = '';
            foreach ($item->scores as $s) {
                $supplycounter = $supplycounter + 1;
                if ($s->supply_option_id == 1) {
                    $supplytype .= 'Free';
                }
                if ($s->supply_option_id == 2) {
                    $comma = '';
                    if ($supplytype <> '') {
                        $comma = ', ';
                    }

                    $supplytype .= $comma . 'Paid';
                }
                $score                   = $score + $s->functionality_score;
                $score                   = $score + $s->ease_of_use_score;
                $score                   = $score + $s->support_score;
                $score                   = $score + $s->value_for_money_score;
                $score                   = $score + $s->documentation_score;
                $item->number_of_reviews = $item->number_of_reviews + $s->number_of_reviews;
            }
            $item->type  = $supplytype;
            $score       = $score / $supplycounter;
            $item->score = floor($score / 5);
            //echo "<pre>";print_r($item);echo "</pre>";exit();
            $item->score_string = JedscoreHelper::getStars($item->score);
            if ($item->number_of_reviews == 0) {
                $item->review_string = '';
            } elseif ($item->number_of_reviews == 1) {
                $item->review_string = '<span>' . $item->number_of_reviews . ' review</span>';
            } elseif ($item->number_of_reviews > 1) {
                $item->review_string = '<span>' . $item->number_of_reviews . ' reviews</span>';
            }
            //echo "<pre>";print_r($item);echo "</pre>";exit();

            // https://extensions.joomla.org/cache/fab_image/27824_resizeDown400px175px16.png

            if (!empty($item->uses_updater)) {
                $item->uses_updater = Text::_('COM_JED_EXTENSIONS_USES_UPDATER_OPTION_' . strtoupper($item->uses_updater));
            }
            $item->version = JedtrophyHelper::getTrophyVersionsString($item->joomla_versions);
        }
        $items = array_values($items);
        array_multisort(array_column($items, "number_of_reviews"), SORT_DESC, $items);

        //echo "<pre>";print_r($items);echo "</pre>";exit();
        return $items;
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
            if (strpos($key, '_dateformat') && !empty($value) && JedHelper::isValidDate($value) == null) {
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
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)->select(
            $db->quoteName(
                [
                        'users.id',
                        'users.name',
                    ]
            )
        )->from($db->quoteName('#__users', 'users'))->leftJoin(
            $db->quoteName('#__jed_extensions', 'extensions') . ' ON ' . $db->quoteName('extensions.created_by') . ' = ' . $db->quoteName('users.id')
        )->where($db->quoteName('users.name') . ' LIKE ' . $db->quote('%' . $search . '%'))->group($db->quoteName('users.id'))->order($db->quoteName('users.name'));
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
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)->select($db->quoteName('type'))->from($db->quoteName('#__jed_extensions_types'))->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query);

        return $db->loadColumn();
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
        $query = $db->getQuery(true)->select($db->quoteName('filename'))->from($db->quoteName('#__jed_extensions_images'))->where($db->quoteName('extension_id') . ' = ' . $extensionId)->order($db->quoteName('order'));
        $db->setQuery($query);

        $items  = $db->loadObjectList();
        $images = [];

        array_walk(
            $items,
            static function ($item, $key) use (&$images) {
                $images['images' . $key]['image'] = $item->filename;
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
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)->select($db->quoteName('category_id'))->from($db->quoteName('#__jed_extensions_categories'))->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query);

        return $db->loadColumn();
    }

    /* Below code modified from com_content_category_model */
    /**
     * Method to get category data for the current category
     *
     * @return array
     *
     * @since  1.5
     * @throws Exception
     */
    public function getCategory(): array
    {
        try {
            $db = $this->getDatabase();
        } catch (DatabaseNotFoundException $e) {
            @trigger_error('Database must be set, this will not be caught anymore in 5.0. - ' . $e->getMessage(), E_USER_DEPRECATED);
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        }
        $id         = Factory::getApplication()->getInput()->getInt('id', -1);
        $extension  = 'com_jed';
        $categories = [];

        if ($id <> -1) {
            if ($id === 0) {
                $id = 'root';
            }

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
            $c_id      = $query->castAsChar($db->quoteName('c.id'));
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


                $query->join(
                    'INNER',
                    $db->quoteName('#__categories', 'c'),
                    '(' . $db->quoteName('s.lft') . ' <= ' . $db->quoteName('c.lft') . ' AND ' . $db->quoteName('c.lft') . ' < ' . $db->quoteName('s.rgt') . ')' . ' OR (' . $db->quoteName('c.lft') . ' < ' . $db->quoteName('s.lft') . ' AND ' . $db->quoteName('s.rgt') . ' < ' . $db->quoteName('c.rgt') . ')'
                );
            } else {
                $query->from($db->quoteName('#__categories', 'c'));
            }

            // Note: i for item

            $subQuery = $db->getQuery(true)->select('COUNT(' . $db->quoteName($db->escape('i.id')) . ')')->from($db->quoteName($db->escape('#__jed_extensions'), 'i'))->where($db->quoteName($db->escape('i.primary_category_id')) . ' = ' . $db->quoteName('c.id'));


            $subQuery->where($db->quoteName($db->escape('i.state')) . ' = 1');



            $query->select('(' . $subQuery . ') AS ' . $db->quoteName('numitems'));

            $db->setQuery($query);
            $results = $db->loadObjectList('id');

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

                        // If this is not root and if the current node's parent is in the list or the current node parent is 0
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
        $this->ll_category_item = $categories;

        return $categories;
    }

    /**
     * Get the left sibling (adjacent) categories.
     *
     * @return CategoryNode|bool|null  An array of categories or false if an error occurs.
     *
     * @since  1.6
     * @throws Exception
     */
    public function &getLeftSibling(): CategoryNode|bool|null
    {
        if (!\is_object($this->ll_category_item)) {
            $this->getCategory();
        }
        $id                           = Factory::getApplication()->getInput()->getInt('id', -1);
        $this->l_category_leftsibling = $this->ll_category_item[$id]->lft;
        return $this->l_category_leftsibling;
    }

    /**
     * Get the right sibling (adjacent) categories.
     *
     * @return CategoryNode|bool|null  An array of categories or false if an error occurs.
     *
     * @since  1.6
     * @throws Exception
     */
    public function &getRightSibling(): CategoryNode|bool|null
    {
        if (!\is_object($this->l_category_item)) {
            $this->getCategory();
        }
        $id                            = Factory::getApplication()->getInput()->getInt('id', -1);
        $this->l_category_rightsibling = $this->l_category_item[$id]->rgt;
        return $this->l_category_rightsibling ;
    }

    /**
     * Get the child categories.
     *
     * @return array  An array of categories or false if an error occurs.
     *
     * @since  1.6
     * @throws Exception
     */
    public function &getChildren(): array
    {
        if (!\is_object($this->l_category_item)) {
            $this->getCategory();
        }
        $id                        = Factory::getApplication()->getInput()->getInt('id', -1);
        $this->l_category_children = $this->l_category_item[$id]->getChildren();

        return $this->l_category_children;
    }

    /**
     * Get the parent category.
     *
     * @return CategoryNode An array of categories or false if an error occurs.
     *
     * @since  1.6
     * @throws Exception
     */
    public function getParent(): CategoryNode
    {
        if (!\is_object($this->l_category_item)) {
            $this->getCategory();
        }
        $id                      = Factory::getApplication()->getInput()->getInt('id', -1);
        $category_parent_id      = $this->l_category_item[$id]->parent_id;
        $this->l_category_parent = $this->l_category_item[$category_parent_id];
        return $this->l_category_parent;
    }
}
