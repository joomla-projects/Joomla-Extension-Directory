<?php

/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LinkLibrary;

/**
 * Describes a folder mapping result returned by Scanner classes
 */
final class ScanResult
{
    /**
     * Type of the extension which was scanned: component, module, plugin, template, library, package
     *
     * @var  string
     */
    public $extensionType = '';

    /**
     * The name of the extension. Depending on the type of extension this can be:
     * <ul>
     *   <li><b>Components</b></li>: com_something
     *   <li><b>Modules</b></li>: foobar (NOT mod_foobar!)
     *   <li><b>Plugins</b></li>: foobar (NOT plg_folder_foobar!)
     *   <li><b>Templates</b></li>: foobar (NOT tpl_foobar!)
     *   <li><b>Libraries</b></li>: foobar (NOT lib_foobar!)
     * </ul>
     *
     * @var  string
     */
    public $extension = '';

    /**
     * If the extension is a plugin this is the plugin's folder, e.g. system, content, user, ...
     *
     * @var  string
     */
    public $pluginFolder = '';

    /**
     * Absolute path to the folder containing the front-end files for the extension
     *
     * @var  string
     */
    public $siteFolder = '';

    /**
     * Absolute path to the folder containing the back-end files for the extension
     *
     * @var  string
     */
    public $adminFolder = '';

    /**
     * Absolute path to the folder containing the Joomla 4 API application files for the extension
     *
     * @var  string
     */
    public $apiFolder = '';

    /**
     * Absolute path to the folder containing the library files for the extensions (they go into /libraries)
     *
     * @var  string
     */
    public $libraryFolder = '';

    /**
     * Absolute path to the folder containing the media files for the extension
     *
     * @var  string
     */
    public $mediaFolder = '';

    /**
     * Name of the destination subdirectory of the media folder on the site.
     *
     * For example, if this is "foobar" then the media files will be stored on the site's "media/foobar" directory.
     *
     * @var  string
     */
    public $mediaDestination = '';

    /**
     * Absolute path to the folder containing the CLI files for the extension
     *
     * @var  string
     */
    public $cliFolder = '';

    /**
     * Absolute path to the folder containing the front-end language files for the extension. Note: this is the folder
     * containing the actual language directories such as en-GB, de-DE, fr-FR and so on.
     *
     * @var  string
     */
    public $siteLangPath = '';

    /**
     * List of the absolute paths of all of the front-end language files, listed by language.
     *
     * For example:
     * [
     *   'en-GB' => [
     *     '/path/to/en-GB/en-GB.com_foobar.ini',
     *     '/path/to/en-GB/en-GB.com_foobar.sys.ini',
     *   ],
     *   'fr-FR' => [
     *     '/path/to/fr-FR/fr-FR.com_foobar.ini',
     *     '/path/to/fr-FR/fr-FR.com_foobar.sys.ini',
     *   ]
     * ]
     *
     * @var  array
     */
    public $siteLangFiles = [];

    /**
     * Absolute path to the folder containing the back-end language files for the extension. Note: this is the folder
     * containing the actual language directories such as en-GB, de-DE, fr-FR and so on.
     *
     * @var  string
     */
    public $adminLangPath = '';

    /**
     * List of the absolute paths of all of the back-end language files, listed by language.
     *
     * For example:
     * [
     *   'en-GB' => [
     *     '/path/to/en-GB/en-GB.com_foobar.ini',
     *     '/path/to/en-GB/en-GB.com_foobar.sys.ini',
     *   ],
     *   'fr-FR' => [
     *     '/path/to/fr-FR/fr-FR.com_foobar.ini',
     *     '/path/to/fr-FR/fr-FR.com_foobar.sys.ini',
     *   ]
     * ]
     *
     * @var  array
     */
    public $adminLangFiles = [];

    /**
     * Absolute path to the folder containing the API language files for the extension. Note: this is the folder
     * containing the actual language directories such as en-GB, de-DE, fr-FR and so on.
     *
     * @var  string
     */
    public $apiLangPath = '';

    /**
     * List of the absolute paths of all of the API language files, listed by language.
     *
     * For example:
     * [
     *   'en-GB' => [
     *     '/path/to/en-GB/en-GB.com_foobar.ini',
     *     '/path/to/en-GB/en-GB.com_foobar.sys.ini',
     *   ],
     *   'fr-FR' => [
     *     '/path/to/fr-FR/fr-FR.com_foobar.ini',
     *     '/path/to/fr-FR/fr-FR.com_foobar.sys.ini',
     *   ]
     * ]
     *
     * @var  array
     */
    public $apiLangFiles = [];

    /**
     * The name of the script file of the extension.
     *
     * Only applies to extension types where the script is outside the main extension directory, i.e. file, package and
     * library extensions.
     *
     * @var string
     */
    public $scriptFileName = '';

    /**
     * List of the filesets of a Joomla file extension, keyed by target directory (relative path).
     *
     * For example, the following manifest:
     * ```xml
     * <fileset>
     *   <files target="cli">
     *     <file>foo.php</file>
     *   </files>
     *   <files target="media/foobar">
     *     <file>bar.php</file>
     *   </files>
     * </fileset>
     * ```
     * results in the array:
     * ```php
     * [
     *   'cli' => [
     *     'foo.php'
     *    ],
     *   'media/foobar' => [
     *     'bar.php'
     *   ]
     * ]
     * ```
     *
     * @var  array
     */
    public $fileSets = [];

    /**
     * Same as $fileSets but for the <folder> tags nested inside the <fileset> tags.
     *
     * @var array
     */
    public $folderSets = [];

    /**
     * Return the name of the extension as it'd be reported by Joomla
     *
     * @param   bool  $includeSiteAdmin  If enabled, modules and templates will have a site_ or admin_ indicator before
     *                                   the name to indicate if they are meant to be installed in front- or backend of
     *                                   the site respectively. This is non-canonical to how Joomla! extensions are
     *                                   typically named.
     *
     * @return  string;
     */
    public function getJoomlaExtensionName($includeSiteAdmin = false)
    {
        $prefix = '';

        switch ($this->extensionType) {
            case 'module':
                if ($includeSiteAdmin) {
                    [$prefix, $extension] = explode('_', $this->extension);

                    if (!empty($this->siteFolder)) {
                        $prefix .= '_site_';
                    } else {
                        $prefix .= '_admin_';
                    }

                    return $prefix . $extension;
                }

                break;

            case 'plugin':
                $prefix = 'plg_' . $this->pluginFolder . '_';
                break;

            case 'template':
                $prefix = 'tpl_';

                if ($includeSiteAdmin) {
                    if (!empty($this->siteFolder)) {
                        $prefix .= 'site_';
                    } else {
                        $prefix .= 'admin_';
                    }
                }

                break;

            case 'library':
                $prefix = 'lib_';
                break;

            case 'package':
                $prefix = 'pkg_';
                break;

            case 'files':
            case 'file':
                $prefix = '';
                break;
        }

        return $prefix . $this->extension;
    }
}
