<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

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

/**
 * JED Router.
 *
 * @package JED
 * @since   4.0.0
 */
class Router extends RouterView
{
    use MVCFactoryAwareTrait;
    use DatabaseAwareTrait;

    /**
     * The category cache
     *
     * @var   array
     * @since 4.0.0
     */
    private array $categoryCache = [];

    protected $categoryFactory;

    /**
     * Class constructor.
     *
     * @param SiteApplication          $app             Application-object that the router should use
     * @param AbstractMenu             $menu            Menu-object that the router should use
     * @param DatabaseInterface        $db
     * @param CategoryFactoryInterface $categoryFactory
     *
     * @since 4.0.0
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu, CategoryFactoryInterface $categoryFactory, DatabaseInterface $db)
    {
        parent::__construct($app, $menu);
        $this->setDatabase($db);
        $this->categoryFactory = $categoryFactory;
        //$this->setMVCFactory($factory);

        // Extensions
        $categories = new RouterViewConfiguration('categories');
        $categories->setKey('id');
        $this->registerView($categories);

        $category = new RouterViewConfiguration('category');
        $category
            ->setKey('id')
            ->setParent($categories)
            ->setNestable();
        $this->registerView($category);

        $extension = new RouterViewConfiguration('extension');
        $extension
            ->setKey('id')
            ->setParent($category, 'catid')
            ->addLayout('edit');
        $this->registerView($extension);

        $extensions = new RouterViewConfiguration('extensions');
        $this->registerView($extensions);

        $extensionform = new RouterViewConfiguration('extensionform');
        $this->registerView($extensionform);

        $newextension = new RouterViewConfiguration('newextension');
        $this->registerView($newextension);

        $dashboard = new RouterViewConfiguration('dashboard');
        $this->registerView($dashboard);

        // Reviews
        $reviews = new RouterViewConfiguration('reviews');
        $this->registerView($reviews);
        $review = new RouterViewConfiguration('review');
        $review->setKey('id')->setParent($reviews);
        $this->registerView($review);
        $reviewform = new RouterViewConfiguration('reviewform');
        $reviewform->setParent($extension);
        $this->registerView($reviewform);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Method to get the segment(s) for a category
     *
     * @param string $segment Segment to retrieve the ID for
     * @param array  $query   The request that is parsed right now
     *
     * @return int|bool   The id of this item or false
     * @since  4.0.0
     */
    public function getCategoriesId(string $segment, array $query): int|bool
    {
        return $this->getCategoryId($segment, $query);
    }

    /**
     * Method to get the segment(s) for a category
     *
     * @param string $id    ID of the category to retrieve the segments for
     * @param array  $query The request that is built right now
     *
     * @return array|string  The segments of this item
     * @since  4.0.0
     */
    public function getCategoriesSegment(string $id, array $query): array|string
    {
        return $this->getCategorySegment($id, $query);
    }

    /**
     * Method to get the id for a category
     *
     * @param string $segment Segment to retrieve the ID for
     * @param array  $query   The request that is parsed right now
     *
     * @return int|bool   The id of this item or false
     * @since  4.0.0
     */
    public function getCategoryId(string $segment, array $query): int|bool
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
     * @param string $id    ID of the category to retrieve the segments for
     * @param array  $query The request that is built right now
     *
     * @return array|string  The segments of this item
     * @since  4.0.0
     */
    public function getCategorySegment(string $id, array $query): array|string
    {
        $category = $this->getCategories(['access' => true])->get($id);

        if ($category) {
            $path    = array_reverse($category->getPath(), true);
            $path[0] = '1:root';

            foreach ($path as &$segment) {
                [$id, $segment] = explode(':', (string) $segment, 2);
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
    public function getExtensionId($segment, $query): int
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
    public function getExtensionSegment($id, $query): array
    {
        if (strpos((string) $id, ':')) {
            return [(int) $id => $id];
        }

        $id        = (int) $id;
        $numericId = $id;
        $db        = $this->getDatabase();
        $query     = $db->getQuery(true);
        $query->select($db->quoteName('alias'))
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
        $db->setQuery($query);

        $id .= ':' . $db->loadResult();

        return [$numericId => $id];
    }

    /**
     * @param $segment
     * @param $query
     *
     * @return bool|int|null
     *
     * @since 4.0.0
     */
    public function getExtensionsId($segment, $query): bool|int|null
    {
        return $this->getCategoryId($segment, $query);
    }

    /**
     * @param $id
     * @param $query
     *
     * @return array|string
     *
     * @since 4.0.0
     */
    public function getExtensionsSegment($id, $query): array|string
    {
        return $this->getCategorySegment($id, $query);
    }

    /**
     * Method to get categories from cache
     *
     * @param array $options The options for retrieving categories
     *
     * @return CategoryInterface  The object containing categories
     *
     * @since 4.0.0
     */
    private function getCategories(array $options = []): CategoryInterface
    {
        $key = serialize($options);

        if (!isset($this->categoryCache[$key])) {
            $this->categoryCache[$key] = $this->categoryFactory->createCategory($options);
        }

        return $this->categoryCache[$key];
    }


    public function getReviewId($segment, $query)
    {
        return $segment;
    }


    public function getReviewSegment($id, $query)
    {
        return [$id];
    }
}
