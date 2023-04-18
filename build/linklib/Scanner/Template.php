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
 * Scanner class for Joomla! templates
 */
class Template extends AbstractScanner
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
        $this->manifestExtensionType = 'template';

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
            throw new RuntimeException("Cannot get XML manifest for template in {$this->extensionRoot}");
        }

        // Intiialize the result
        $result                = new ScanResult();
        $result->extensionType = 'template';

        // Get the extension name
        $template = strtolower($xmlDoc->getElementsByTagName('name')->item(0)->nodeValue);

        if (is_null($template)) {
            throw new RuntimeException("Cannot find the template name in the XML manifest for {$this->extensionRoot}");
        }

        $result->extension = $template;

        // Is this is a site or administrator template?
        $isSite = $xmlDoc->documentElement->getAttribute('client') == 'site';

        // Get the main folder to link
        if ($isSite) {
            $result->siteFolder = $this->extensionRoot;
        } else {
            $result->adminFolder = $this->extensionRoot;
        }

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

            if ($isSite) {
                $result->siteLangFiles = $languageFiles;
                $result->siteLangPath  = $languageRoot;

                continue;
            }

            $result->adminLangFiles = $languageFiles;
            $result->adminLangPath  = $languageRoot;
        }

        // Scan language files in a separate root, if one is specified
        if (!empty($this->languageRoot)) {
            $langPath  = $this->languageRoot . '/templates/';
            $langPath .= $isSite ? 'site/' : 'admin/';
            $langPath .= $template;
            $langFiles = $this->scanLanguageFolder($langPath);

            if (!empty($langFiles)) {
                if ($isSite) {
                    $result->siteLangPath  = $langPath;
                    $result->siteLangFiles = $langFiles;
                } else {
                    $result->adminLangPath  = $langPath;
                    $result->adminLangFiles = $langFiles;
                }
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

        $source   = $scan->siteFolder;
        $basePath = $this->siteRoot . '/';

        if (!empty($scan->adminFolder)) {
            $basePath .= 'administrator/';
            $source = $scan->adminFolder;
        }

        $basePath .= 'templates/' . $scan->extension;

        // Frontend and backend directories
        $dirs = [
            $source => $basePath,
        ];

        $result->dirs = array_merge($result->dirs, $dirs);

        return $result;
    }

    /**
     * Detect extensions of type Template in the repository and return an array of ScannerInterface objects for them.
     *
     * @param   string  $repositoryRoot  The repository root to scan
     *
     * @return  ScannerInterface[]
     */
    public static function detect($repositoryRoot): array
    {
        $path       = $repositoryRoot . '/templates';
        $sections   = ['site', 'admin'];
        $extensions = [];

        if (!is_dir($path)) {
            return $extensions;
        }

        // Loop both sections (site and admin)
        foreach ($sections as $section) {
            $sectionPath = $path . '/' . $section;

            if (!is_dir($sectionPath)) {
                continue;
            }

            // Loop all templates in the section
            $di = new \DirectoryIterator($sectionPath);

            foreach ($di as $folder) {
                if ($folder->isDot() || !$folder->isDir()) {
                    continue;
                }

                $extName = $folder->getFilename();

                // Figure out the language root to use
                $languageRoot     = null;
                $translationsRoot = self::getTranslationsRoot($repositoryRoot);

                if ($translationsRoot) {
                    $languageRoot = $translationsRoot . '/templates/' . $section . '/' . $extName;

                    if (!is_dir($languageRoot)) {
                        $languageRoot = null;
                    }
                }

                // Get the extension ScannerInterface object
                $extension    = new Template($folder->getRealPath(), $languageRoot);
                $extensions[] = $extension;
            }
        }

        return $extensions;
    }
}
