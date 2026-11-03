<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

/**
 * Install script for com_vel.
 *
 * @since 4.0.0
 */
class Com_VelInstallerScript
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    private string $minimumJoomlaVersion = '5.0';

    /**
     * @var string
     *
     * @since 4.0.0
     */
    private string $minimumPHPVersion = '8.3.0';

    /**
     * @param string           $type   The type of change (install, update or discover_install).
     * @param InstallerAdapter $parent The class calling this method.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function preflight(string $type, InstallerAdapter $parent): bool
    {
        if (version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            Log::add(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion), Log::WARNING, 'jerror');

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            Log::add(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion), Log::WARNING, 'jerror');

            return false;
        }

        return true;
    }

    /**
     * @param string           $type   The type of change.
     * @param InstallerAdapter $parent The class calling this method.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        $this->ensurePrivateFolder();
        $this->addTicketLinkedItemType();

        echo Text::_('COM_VEL_INSTALLERSCRIPT_POSTFLIGHT');

        return true;
    }

    /**
     * The attachment folder, outside the web root, with the web server kept out of it twice
     * over: a folder it is not configured to serve, and an .htaccess denying everything in case
     * it ever is.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function ensurePrivateFolder(): void
    {
        $path = \dirname(JPATH_ROOT) . '/vel_private';

        try {
            if (!is_dir($path)) {
                Folder::create($path, 0750);
            }

            if (is_dir($path) && !is_file($path . '/.htaccess')) {
                file_put_contents($path . '/.htaccess', "Require all denied\nDeny from all\n");
            }

            if (is_dir($path) && !is_file($path . '/index.html')) {
                file_put_contents($path . '/index.html', '<!DOCTYPE html><title></title>');
            }
        } catch (Throwable $e) {
            Log::add('com_vel: could not create the private attachment folder: ' . $e->getMessage(), Log::WARNING, 'jerror');
        }
    }

    /**
     * Register the VEL's ticket type in com_jed's lookup table so the ticket list can name it
     * (VEL2-PRD §6.9.2). Tolerates com_jed being absent.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function addTicketLinkedItemType(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $db->setQuery(
                'INSERT IGNORE INTO ' . $db->quoteName('#__jed_ticket_linked_item_types')
                . ' (' . $db->quoteName(['id', 'title', 'model', 'ordering', 'state', 'created_by']) . ')'
                . ' VALUES (11, ' . $db->quote('Vulnerability Advisory') . ', ' . $db->quote('Veladvisory') . ', 0, 1, 0)'
            )->execute();
        } catch (Throwable $e) {
            // No com_jed here; nothing to register.
        }
    }
}
