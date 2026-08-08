<?php

/**
 * @package JED
 *
 * @subpackage mod_jed_extensions
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * The browse-list module service provider.
 *
 * @since 4.0.0
 */
return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param Container $container The DI container.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function register(Container $container)
    {
        $container->registerServiceProvider(new ModuleDispatcherFactory('\Jed\Module\Extensions'));
        $container->registerServiceProvider(new HelperFactory('\Jed\Module\Extensions\Site\Helper'));

        $container->registerServiceProvider(new Module());
    }
};
