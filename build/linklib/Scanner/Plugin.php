<?php

/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LinkLibrary\Scanner;

use Akeeba\LinkLibrary\MapResult;
use Akeeba\LinkLibrary\ScannerInterface;
use Akeeba\LinkLibrary\ScanResult;
use RuntimeException;

/**
 * Scanner class for Joomla! plugins
 */
class Plugin extends AbstractScanner
{
    /**
     * Constructor.
     *
     * The languageRoot is optional and applies only if the languages are stored in a directory other than the one
     * specified in the extension's XML file.
     *
     * @param   string  $extensionRoot  The absolute path to the extension's root folder
     * @param   string  $languageRoot   The absolute path to the extension's language folder (optional)
     */
    public function __construct($extensionRoot, $languageRoot = null)
    {
        $this->manifestExtensionType = 'plugin';

        parent::__construct($extensionRoot, $languageRoot);
    }

    /**
     * Scans the extension for files and folders to link
     *
     * @return  ScanResult
     */
    public function scan()
    {
        // Get the XML manifest
        $xmlDoc = $this->getXMLManifest();

        if (empty($xmlDoc)) {
            throw new RuntimeException("Cannot get XML manifest for plugin in {$this->extensionRoot}");
        }

        // Intiialize the result
        $result                = new ScanResult();
        $result->extensionType = 'plugin';

        // Get the extension name
        $files  = $xmlDoc->getElementsByTagName('files')->item(0)->childNodes;
        $plugin = null;

        /** @var \DOMElement $file */
        foreach ($files as $file) {
            if ($file->hasAttributes()) {
                $plugin = $file->getAttribute('plugin');

                break;
            }
        }

        /**
         * Native Joomla 4 plugins do not have the plugin attribute in a file entry. They have a namespace element under
         * the root and the plugin name is the name of the folder.
         */
        if (is_null($plugin)) {
            $hasNamespace = $xmlDoc->getElementsByTagName('namespace')->count();

            if ($hasNamespace) {
                $plugin = basename($this->extensionRoot);
            }
        }

        if (is_null($plugin)) {
            throw new RuntimeException("Cannot find the plugin name in the XML manifest for {$this->extensionRoot}");
        }

        $result->extension = $plugin;

        // Is this is a site or administrator module?
        $result->pluginFolder = $xmlDoc->documentElement->getAttribute('group');

        // Get the main folder to link
        $result->siteFolder = $this->extensionRoot;

        // Get the media folder
        $result->mediaFolder      = null;
        $result->mediaDestination = null;
        $allMediaTags             = $xmlDoc->getElementsByTagName('media');

        if ($allMediaTags->length >= 1) {
            $result->mediaFolder      = $this->extensionRoot . '/' . (string) $allMediaTags->item(0)
                                                                                           ->getAttribute('folder');
            $result->mediaDestination = $allMediaTags->item(0)->getAttribute('destination');
        }

        // Get the <languages> tag
        $xpath          = new \DOMXPath($xmlDoc);
        $languagesNodes = $xpath->query('/extension/languages');

        foreach ($languagesNodes as $node) {
            list($languageRoot, $languageFiles) = $this->scanLanguageNode($node);

            if (empty($languageFiles)) {
                continue;
            }

            // Plugin language files always go to the backend language folder
            $result->adminLangFiles = $languageFiles;
            $result->adminLangPath  = $languageRoot;
        }

        // Scan language files in a separate root, if one is specified
        if (!empty($this->languageRoot)) {
            $langPath  = $this->languageRoot . '/plugins/' . $result->pluginFolder . '/' . $result->extension;
            $langFiles = $this->scanLanguageFolder($langPath);

            if (!empty($langFiles)) {
                $result->adminLangPath  = $langPath;
                $result->adminLangFiles = $langFiles;
            }
        }


        return $result;
    }

    /**
     * Parses the last scan and generates a link map
     *
     * @return  MapResult
     */
    public function map()
    {
        $scan   = $this->getScanResults();
        $result = parent::map();

        $basePath = $this->siteRoot . '/plugins/' . $scan->pluginFolder . '/' . $scan->extension;

        // Frontend and backend directories
        $dirs = [
            $scan->siteFolder => $basePath,
        ];

        $result->dirs = array_merge($result->dirs, $dirs);

        return $result;
    }

    /**
     * Detect extensions of type Plugin in the repository and return an array of ScannerInterface objects for them.
     *
     * @param   string  $repositoryRoot  The repository root to scan
     *
     * @return  ScannerInterface[]
     */
    public static function detect($repositoryRoot): array
    {
        $path       = $repositoryRoot . '/plugins';
        $extensions = [];

        if (!is_dir($path)) {
            return $extensions;
        }

        // Scan the "plugins" repo folder for the sections (user, system, content, quickicon, somethingCustom, ...)
        $outerDi = new \DirectoryIterator($path);

        foreach ($outerDi as $sectionFolder) {
            if ($sectionFolder->isDot() || !$sectionFolder->isDir()) {
                continue;
            }

            $sectionPath = $sectionFolder->getRealPath();
            $section     = $sectionFolder->getFilename();

            // Scan all plugin folders inside that section
            $allPluginFolders = new \DirectoryIterator($sectionPath);

            foreach ($allPluginFolders as $pluginFolder) {
                if ($pluginFolder->isDot() || !$pluginFolder->isDir()) {
                    continue;
                }

                $extName = $pluginFolder->getFilename();

                // Figure out the language root to use
                $languageRoot     = null;
                $translationsRoot = self::getTranslationsRoot($repositoryRoot);

                if ($translationsRoot) {
                    $languageRoot = $translationsRoot . '/plugins/' . $section . '/' . $extName;

                    if (!is_dir($languageRoot)) {
                        $languageRoot = null;
                    }
                }

                // Get the extension ScannerInterface object
                $extension    = new Plugin($pluginFolder->getRealPath(), $languageRoot);
                $extensions[] = $extension;
            }
        }

        return $extensions;
    }
}
