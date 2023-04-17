<?php

/**
 * @package        JED
 *
 * @copyright  (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

use function defined;

/**
 * JED Router.
 *
 * @since     4.0.0
 * @package   JED
 */
class Router extends RouterView
{
    use MVCFactoryAwareTrait;
    use DatabaseAwareTrait;

    /**
     * The category cache
     *
     * @since 4.0.0
     * @var   array
     */
    private array $categoryCache = [];

    /**
     * The category factory
     *
     * @since 4.0.0
     * @var   CategoryFactoryInterface
     */
    private CategoryFactoryInterface $categoryFactory;

    /**
     * Class constructor.
     *
     * @param   CMSApplication  $app   Application-object that the router should use
     * @param   AbstractMenu    $menu  Menu-object that the router should use
     *
     * @since   3.4
     */
    public function __construct(SiteApplication $app = null, AbstractMenu $menu, DatabaseInterface $db, MVCFactory $factory, CategoryFactoryInterface $categoryFactory)
    {
        parent::__construct($app, $menu);

        $this->categoryFactory = $categoryFactory;
        $this->setDatabase($db);
        $this->setMVCFactory($factory);

        $categories = new RouterViewConfiguration('categories');
        $categories->setKey('id');
        $this->registerView($categories);

        $extensions = new RouterViewConfiguration('extensions');
        $extensions
            ->setKey('id')
            ->setParent($categories, 'catid')
            ->setNestable();
        $this->registerView($extensions);

        $extension = new RouterViewConfiguration('extension');
        $extension
            ->setKey('id')
            ->setParent($extensions, 'primary_category_id')
            ->addLayout('edit');
        $this->registerView($extension);

        // TODO

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Method to get the segment(s) for a category
     *
     * @param   string  $segment  Segment to retrieve the ID for
     * @param   array   $query    The request that is parsed right now
     *
     * @return  mixed   The id of this item or false
     * @since   4.0.0
     */
    public function getCategoriesId($segment, $query)
    {
        return $this->getCategoryId($segment, $query);
    }

    /**
     * Method to get the segment(s) for a category
     *
     * @param   string  $id     ID of the category to retrieve the segments for
     * @param   array   $query  The request that is built right now
     *
     * @return  array|string  The segments of this item
     * @since   4.0.0
     */
    public function getCategoriesSegment($id, $query)
    {
        return $this->getCategorySegment($id, $query);
    }

    /**
     * Method to get the id for a category
     *
     * @param   string  $segment  Segment to retrieve the ID for
     * @param   array   $query    The request that is parsed right now
     *
     * @return  mixed   The id of this item or false
     * @since   4.0.0
     */
    public function getCategoryId($segment, $query)
    {
        $id = $query['id'] ?? 'root';

        $category = $this->getCategories(['access' => false])->get($id);

        if (!$category) {
            return false;
        }

        foreach ($category->getChildren() as $child) {
            if ($child->alias == $segment) {
                return $child->id;
            }
        }

        return false;
    }

    /**
     * Method to get the segment(s) for a category
     *
     * @param   string  $id     ID of the category to retrieve the segments for
     * @param   array   $query  The request that is built right now
     *
     * @return  array|string  The segments of this item
     * @since   4.0.0
     */
    public function getCategorySegment($id, $query)
    {
        $category = $this->getCategories(['access' => true])->get($id);

        if ($category) {
            $path    = array_reverse($category->getPath(), true);
            $path[0] = '1:root';

            foreach ($path as &$segment) {
                [$id, $segment] = explode(':', $segment, 2);
            }

            return $path;
        }

        return [];
    }

    /**
     * @param $segment
     * @param $query
     *
     * @return int
     *
     * @since 4.0.0
     */
    public function getExtensionId($segment, $query)
    {
        return (int) $segment;
    }

    /**
     * @param $id
     * @param $query
     *
     * @return array|string[]
     *
     * @since 4.0.0
     */
    public function getExtensionSegment($id, $query)
    {
        if (strpos($id, ':')) {
            return [(int) $id => $id];
        }

        $id        = (int) $id;
        $numericId = $id;
        $db        = $this->getDatabase();
        $query     = $db->getQuery(true);
        $query->select($db->quoteName('alias'))
              ->from($db->quoteName('#__jed_extension_varied_data'))
              ->where($db->quoteName('extension_id') . ' = :id')
              ->bind(':id', $id, ParameterType::INTEGER);
        $db->setQuery($query);

        $id .= ':' . $db->loadResult();

        return [$numericId => $id];
    }

    public function getExtensionsId($segment, $query)
    {
        return $this->getCategoryId($segment, $query);
    }

    public function getExtensionsSegment($id, $query)
    {
        return $this->getCategorySegment($id, $query);
    }

    /**
     * Method to get categories from cache
     *
     * @param   array  $options  The options for retrieving categories
     *
     * @return  CategoryInterface  The object containing categories
     *
     * @since   4.0.0
     */
    private function getCategories(array $options = []): CategoryInterface
    {
        $key = serialize($options);

        if (!isset($this->categoryCache[$key])) {
            $this->categoryCache[$key] = $this->categoryFactory->createCategory($options);
        }

        return $this->categoryCache[$key];
    }
}
