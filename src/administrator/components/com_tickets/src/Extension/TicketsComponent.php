<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Tag\TagServiceInterface;
use Joomla\CMS\Tag\TagServiceTrait;
use Psr\Container\ContainerInterface;
use stdClass;

/**
 * Component class for com_jed
 *
 * @since 4.0.0
 */
class TicketsComponent extends MVCComponent implements
    BootableExtensionInterface,
    RouterServiceInterface
{
    use HTMLRegistryAwareTrait;
    use RouterServiceTrait;

    /**
     * Booting the extension. This is the function to set up the environment of the extension like
     * registering new class loaders, etc.
     *
     * If required, some initial set up can be done from services of the container, e.g.
     * registering HTML services.
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
}
