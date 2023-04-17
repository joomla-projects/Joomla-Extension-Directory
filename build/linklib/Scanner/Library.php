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
 * Scanner class for Joomla! libraries
 */
class Library extends AbstractScanner
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
        $this->manifestExtensionType = 'library';

        parent::__construct($extensionRoot, $languageRoot);
    }

    /**
     * Scans the extension for files and folders to link
     *
     * @return  ScanResult
     */
    public function scan(): ScanResult
    {
        // Get the XML manifest
        $xmlDoc = $this->getXMLManifest();

        if (empty($xmlDoc)) {
            throw new RuntimeException("Cannot get XML manifest for library in {$this->extensionRoot}");
        }

        // Initialize the result
        $result                = new ScanResult();
        $result->extensionType = 'library';

        // Get the extension name
        $library = strtolower($xmlDoc->getElementsByTagName('name')->item(0)->nodeValue);

        if (is_null($library)) {
            throw new RuntimeException("Cannot find the library name in the XML manifest for {$this->extensionRoot}");
        }

        $result->extension = $library;

        // Get the library <files> tags
        $result->libraryFolder = $this->extensionRoot;
        $allFilesTags          = $xmlDoc->getElementsByTagName('files');

        $nodePath0    = $allFilesTags->item(0)->getNodePath();
        $siteFilesTag = $allFilesTags->item(0);

        if ($nodePath0 != '/extension/files') {
            $siteFilesTag = $allFilesTags->item(1);
        }

        if ($siteFilesTag->hasAttribute('folder')) {
            $result->libraryFolder = $this->extensionRoot . '/' . $siteFilesTag->getAttribute('folder');
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

        // Get the <languages> tags for front and back-end
        $xpath = new \DOMXPath($xmlDoc);

        // Get frontend language files from the frontend <languages> tag
        $result->siteLangPath  = null;
        $result->siteLangFiles = [];
        $frontEndLanguageNodes = $xpath->query('/extension/languages');

        foreach ($frontEndLanguageNodes as $node) {
            list($languageRoot, $languageFiles) = $this->scanLanguageNode($node);

            if (!empty($languageFiles)) {
                $result->siteLangFiles = $languageFiles;
                $result->siteLangPath  = $languageRoot;
            }
        }

        // Get backend language files from the backend <languages> tag
        $result->adminLangPath  = null;
        $result->adminLangFiles = [];
        $backEndLanguageNodes   = $xpath->query('/extension/administration/languages');

        foreach ($backEndLanguageNodes as $node) {
            list($languageRoot, $languageFiles) = $this->scanLanguageNode($node);

            if (!empty($languageFiles)) {
                $result->adminLangFiles = $languageFiles;
                $result->adminLangPath  = $languageRoot;
            }
        }

        // Scan language files in a separate root, if one is specified
        if (!empty($this->languageRoot)) {
            $langPath  = $this->languageRoot . '/libraries/' . $library . '/frontend';
            $langFiles = $this->scanLanguageFolder($langPath);

            if (!empty($langFiles)) {
                $result->siteLangPath  = $langPath;
                $result->siteLangFiles = $langFiles;
            }

            $langPath  = $this->languageRoot . '/libraries/' . $library . '/backend';
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
    public function map(): MapResult
    {
        $scan   = $this->getScanResults();
        $result = parent::map();

        // Map the library itself
        $dirs = [
            $scan->libraryFolder => $this->siteRoot . '/libraries/' . $scan->extension,
        ];

        $result->dirs = array_merge($result->dirs, $dirs);

        // Map XML manifest
        if (!empty($this->xmlManifestPath)) {
            $manifestFrom  = $this->extensionRoot . '/' . $this->xmlManifestPath;
            $manifestTo    = $this->siteRoot . '/administrator/manifests/libraries/' . $this->xmlManifestPath;
            $result->files = array_merge($result->files, [$manifestFrom => $manifestTo]);
        }

        return $result;
    }

    /**
     * Detect extensions of type Library in the repository and return an array of ScannerInterface objects for them.
     *
     * @param   string  $repositoryRoot  The repository root to scan
     *
     * @return  ScannerInterface[]
     */
    public static function detect(string $repositoryRoot): array
    {
        $path       = $repositoryRoot . '/libraries';
        $extensions = [];

        if (!is_dir($path)) {
            return $extensions;
        }

        // Loop all libraries in the section
        $di = new \DirectoryIterator($path);

        foreach ($di as $folder) {
            if ($folder->isDot() || !$folder->isDir()) {
                continue;
            }

            $extName = $folder->getFilename();

            // Figure out the language root to use
            $languageRoot     = null;
            $translationsRoot = self::getTranslationsRoot($repositoryRoot);

            if ($translationsRoot) {
                $languageRoot = $translationsRoot . '/libraries/' . $extName;

                if (!is_dir($languageRoot)) {
                    $languageRoot = null;
                }
            }

            // Get the extension ScannerInterface object
            $extension    = new Library($folder->getRealPath(), $languageRoot);
            $extensions[] = $extension;
        }

        return $extensions;
    }
}
