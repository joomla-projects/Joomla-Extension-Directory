<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Listener\AdvisoryListener;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\Event\DispatcherInterface;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_vel.
 *
 * @since 4.0.0
 */
class VelComponent extends MVCComponent implements
    BootableExtensionInterface,
    RouterServiceInterface
{
    use HTMLRegistryAwareTrait;
    use RouterServiceTrait;

    /**
     * @param ContainerInterface $container The container.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function boot(ContainerInterface $container): void
    {
        // The listeners that react to an advisory moving (notifications, JED ticket notes, cache
        // drops). Subscribed once per request, wherever the component is booted.
        static $subscribed = false;

        if (!$subscribed) {
            $subscribed = true;

            try {
                $container->get(DispatcherInterface::class)->addSubscriber(new AdvisoryListener());
            } catch (\Throwable $e) {
                // No dispatcher in this context (CLI without an application); nothing to hear.
            }
        }
    }
}
