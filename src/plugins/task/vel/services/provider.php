<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.vel
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\Task\Vel\Extension\Vel;

return new class () implements ServiceProviderInterface {
    /**
     * @param Container $container The DI container.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Vel::class, function (Container $container) {
                // The services are built inside each routine rather than here: they read the
                // component options at run time, and the scheduler boots no component.
                $plugin = new Vel((array) PluginHelper::getPlugin('task', 'vel'), $container->get(DatabaseInterface::class));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
