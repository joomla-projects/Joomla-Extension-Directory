<?php

/**
 * @package JED
 *
 * @subpackage mod_jed_opentickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Database\ParameterType;

/**
 * Script file of the "JED - Open Tickets" dashboard module.
 *
 * @since 4.1.0
 */
class Mod_Jed_OpenTicketsInstallerScript
{
    /**
     * The module's element name, as stored in `#__modules.module`.
     *
     * @var   string
     * @since 4.1.0
     */
    private const MODULE_ELEMENT = 'mod_jed_opentickets';

    /**
     * The admin Dashboard's module position (Joomla core's own dashboard modules,
     * e.g. mod_popular/mod_latest, ship published to this same position).
     *
     * @var   string
     * @since 4.1.0
     */
    private const DASHBOARD_POSITION = 'cpanel';

    /**
     * Method to install the extension.
     *
     * Publishes the module to the admin home Dashboard, since Joomla's installer
     * always creates new modules unpublished with no position - there is no manifest
     * tag for this, so it has to be done here, the same way core's own dashboard
     * modules are seeded (administrator/sql/base.sql inserts into #__modules and
     * #__modules_menu directly).
     *
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool True on success
     *
     * @since 4.1.0
     */
    public function install(InstallerAdapter $parent): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $moduleElement = self::MODULE_ELEMENT;

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = :module')
            ->where($db->quoteName('client_id') . ' = 1')
            ->bind(':module', $moduleElement, ParameterType::STRING);

        $moduleId = $db->setQuery($query)->loadResult();

        if (!$moduleId) {
            return true;
        }

        $moduleId = (int) $moduleId;
        $position = self::DASHBOARD_POSITION;

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__modules'))
            ->set($db->quoteName('position') . ' = :position')
            ->set($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':position', $position, ParameterType::STRING)
            ->bind(':id', $moduleId, ParameterType::INTEGER);
        $db->setQuery($update)->execute();

        // Assign it to every admin page (menuid = 0), matching how core's own
        // dashboard modules are assigned - a module can be published to a position
        // and still never render without this.
        $exists = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__modules_menu'))
            ->where($db->quoteName('moduleid') . ' = :moduleid')
            ->where($db->quoteName('menuid') . ' = 0')
            ->bind(':moduleid', $moduleId, ParameterType::INTEGER);

        if ((int) $db->setQuery($exists)->loadResult() === 0) {
            $row           = new stdClass();
            $row->moduleid = $moduleId;
            $row->menuid   = 0;

            $db->insertObject('#__modules_menu', $row);
        }

        return true;
    }
}
