<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Service;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Component\Router\RouterInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

class RouterFactory implements RouterFactoryInterface
{
    /**
     * The category factory object for ATS
     *
     * @var   CategoryFactoryInterface
     * @since 4.0.0
     */
    private CategoryFactoryInterface $categoryFactory;

    /**
     * The database factory object
     *
     * @var   DatabaseInterface
     * @since 4.0.0
     */
    private DatabaseInterface $db;

    /**
     * THe MVC factory object
     *
     * @var   MVCFactoryInterface
     * @since 4.0.0
     */
    private MVCFactoryInterface $factory;

    /**
     * The extension's namespace
     *
     * @var   string
     * @since 4.0.0
     */
    private string $namespace;

    public function __construct(string $namespace, DatabaseInterface $db = null, MVCFactoryInterface $factory, CategoryFactoryInterface $categoryFactory)
    {
        $this->namespace       = $namespace;
        $this->factory         = $factory;
        $this->db              = $db;
        $this->categoryFactory = $categoryFactory;
    }

    /**
     * @inheritdoc
     * @since      4.0.0
     */
    public function createRouter(CMSApplicationInterface $application, AbstractMenu $menu): RouterInterface
    {
        $className = trim($this->namespace, '\\') . '\\' . ucfirst($application->getName()) . '\\Service\\Router';

        if (!class_exists($className)) {
            throw new RuntimeException('No router available for this application.');
        }

        return new $className($application, $menu, $this->db, $this->factory, $this->categoryFactory);
    }
}
