<?php

/**
 * Joomla.org site template
 *
 * @copyright   Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Installer\InstallerScriptTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the installer script with the container.
     *
     * @param   Container  $container  The DI container
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class ($container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                use DatabaseAwareTrait;
                use InstallerScriptTrait;

                /**
                 * @param   DatabaseInterface  $db  The database driver
                 */
                public function __construct(DatabaseInterface $db)
                {
                    $this->setDatabase($db);

                    $this->extension     = 'joomla';
                    $this->minimumJoomla = '6.0';
                    $this->minimumPhp    = '8.3.0';

                    // Workaround for https://github.com/joomla/joomla-cms/issues/23219 by allowing downgrades!?
                    $this->allowDowngrades = true;

                    $this->deleteFiles = [
                        '/language/en-GB/en-GB.tpl_joomla.ini',
                        '/language/en-GB/en-GB.tpl_joomla.sys.ini',
                        '/templates/joomla/images/apple-touch-icon-114-precomposed.png',
                        '/templates/joomla/images/apple-touch-icon-144-precomposed.png',
                        '/templates/joomla/images/apple-touch-icon-57-precomposed.png',
                        '/templates/joomla/images/apple-touch-icon-72-precomposed.png',
                        '/templates/joomla/html/pagination.php',
                        // Module chrome is resolved as a layout since Joomla 4, the function based chrome never runs
                        '/templates/joomla/html/modules.php',
                        '/templates/joomla/html/layouts/joomla/system/message.php',
                        '/templates/joomla/html/layouts/joomla/pagination/links.php',
                        '/templates/joomla/html/layouts/joomla/pagination/list.php',
                        '/templates/joomla/css/bs3-polyfill.css',
                        '/templates/joomla/fonts/icomoon-joomla.eot',
                        '/templates/joomla/fonts/icomoon-joomla.svg',
                        '/templates/joomla/fonts/icomoon-joomla.ttf',
                        '/templates/joomla/fonts/icomoon-joomla.woff',
                        '/templates/joomla/fonts/lte-ie7.js',
                        '/templates/joomla/img/glyphicons-halflings-white.png',
                        '/templates/joomla/img/glyphicons-halflings.png',
                        '/templates/joomla/less/buttons.less',
                        '/templates/joomla/less/icomoon-joomla.less',
                        '/templates/joomla/less/rtl/alerts.less',
                        '/templates/joomla/less/rtl/button-groups.less',
                        '/templates/joomla/less/rtl/close.less',
                        '/templates/joomla/less/rtl/dropdowns.less',
                        '/templates/joomla/less/rtl/forms.less',
                        '/templates/joomla/less/rtl/grid.less',
                        '/templates/joomla/less/rtl/media.less',
                        '/templates/joomla/less/rtl/mixins.less',
                        '/templates/joomla/less/rtl/navbar.less',
                        '/templates/joomla/less/rtl/navs.less',
                        '/templates/joomla/less/rtl/pager.less',
                        '/templates/joomla/less/rtl/pagination.less',
                        '/templates/joomla/less/rtl/popovers.less',
                        '/templates/joomla/less/rtl/responsive-1200px-min.less',
                        '/templates/joomla/less/rtl/responsive-768px-979px.less',
                        '/templates/joomla/less/rtl/responsive-navbar.less',
                        '/templates/joomla/less/rtl/tables.less',
                        '/templates/joomla/less/rtl/thumbnails.less',
                        '/templates/joomla/less/rtl/tooltip.less',
                        '/templates/joomla/less/rtl/type.less',
                        '/templates/joomla/less/rtl/utilities.less',
                        '/templates/joomla/less/template-rtl.less',
                        '/templates/joomla/less/template.less',
                        '/templates/joomla/less/variables.less',
                        '/templates/joomla/css/template.css',
                        '/templates/joomla/css/template.css.map',
                        '/templates/joomla/css/template.min.css',
                        '/templates/joomla/css/template.min.css.map',
                        '/templates/joomla/css/template-rtl.css',
                        '/templates/joomla/css/template-rtl.css.map',
                        '/templates/joomla/css/template-rtl.min.css',
                        '/templates/joomla/css/template-rtl.min.css.map',
                        '/templates/joomla/js/blockadblock.js',
                        '/templates/joomla/js/js.cookie.js',
                        '/templates/joomla/js/template.js',
                    ];

                    $this->deleteFolders = [
                        '/templates/joomla/fonts',
                        '/templates/joomla/img',
                        '/templates/joomla/layouts/joomla/system',
                        /**
                         * Whilst the CSS folder is no longer shipped as part of this template we leave it as nearly all core sites
                         * have a custom.css file which we do not want to delete! So don't delete it here
                         */
                        // '/templates/joomla/css',
                        '/templates/joomla/js',
                        '/templates/joomla/less',
                        '/templates/joomla/html/layouts/joomla/system',
                    ];
                }

                /**
                 * Ensure the template is made inheritable so CSS & JS can be found.
                 *
                 * @param   string  $name  The template name
                 *
                 * @return  void
                 */
                private function fixTemplateMode(string $name): void
                {
                    $db       = $this->getDatabase();
                    $clientId = 0;

                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__template_styles'))
                        ->set($db->quoteName('inheritable') . ' = 1')
                        ->where($db->quoteName('template') . ' = :template')
                        ->where($db->quoteName('client_id') . ' = :clientId')
                        ->bind(':template', $name)
                        ->bind(':clientId', $clientId, ParameterType::INTEGER);

                    try {
                        $db->setQuery($query)->execute();
                    } catch (\Exception $e) {
                        Log::add(
                            Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()),
                            Log::WARNING,
                            'jerror'
                        );
                    }
                }

                /**
                 * Function to perform changes during postflight
                 *
                 * @param   string            $type     The action being performed
                 * @param   InstallerAdapter  $adapter  The class calling this method
                 *
                 * @return  boolean  True on success
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    $this->removeFiles();
                    $this->fixTemplateMode('joomla');

                    return true;
                }
            }
        );
    }
};
