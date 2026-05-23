<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_jed (Site)
 *
 * @since 4.0.0
 */
class SiteComponent extends MVCComponent implements
    BootableExtensionInterface,
    CategoryServiceInterface,
    RouterServiceInterface
{
    use HTMLRegistryAwareTrait;
    use RouterServiceTrait;
    use CategoryServiceTrait;

    /**
     * Booting the extension.
     *
     * @param ContainerInterface $container The container
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function boot(ContainerInterface $container): void
    {
    }

    /**
     * Returns the dispatcher for the given application.
     *
     * @param CMSApplicationInterface $application The application
     *
     * @return DispatcherInterface
     *
     * @since 4.0.0
     */
    public function getDispatcher(CMSApplicationInterface $application): DispatcherInterface
    {
        return parent::getDispatcher($application);
    }
}
