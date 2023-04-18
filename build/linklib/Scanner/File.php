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
 * Scanner class for Joomla! file extensions
 */
class File extends AbstractScanner
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
        $this->manifestExtensionType = 'file';

        parent::__construct($extensionRoot, $languageRoot);
    }

    /**
     * Detect extensions of type File in the repository and return an array of ScannerInterface objects for them.
     *
     * @param   string  $repositoryRoot  The repository root to scan
     *
     * @return  ScannerInterface[]
     */
    public static function detect(string $repositoryRoot): array
    {
        $possiblePaths = [
            $repositoryRoot . '/component/cli',
            $repositoryRoot . '/fof',
            $repositoryRoot . '/joomla',
        ];

        $rootPath   = $repositoryRoot . '/file';
        $extensions = [];

        if (is_dir($rootPath)) {
            $di = new \DirectoryIterator($rootPath);

            foreach ($di as $folder) {
                if ($folder->isDot() || !$folder->isDir()) {
                    continue;
                }

                $extName = $folder->getFilename();

                // Figure out the language root to use
                $languageRoot     = null;
                $translationsRoot = self::getTranslationsRoot($repositoryRoot);

                if ($translationsRoot) {
                    $languageRoot = $translationsRoot . '/file/' . $extName;

                    if (!is_dir($languageRoot)) {
                        $languageRoot = null;
                    }
                }

                // Get the extension ScannerInterface object
                $possiblePaths[] = $folder->getRealPath();
            }
        }

        foreach ($possiblePaths as $path) {
            if (!file_exists($path) || !is_dir($path)) {
                continue;
            }

            $extName = basename($path);

            // Figure out the language root to use
            $languageRoot     = null;
            $translationsRoot = self::getTranslationsRoot($repositoryRoot);

            if ($translationsRoot) {
                $languageRoot = $translationsRoot . '/file/' . $extName;

                if (!is_dir($languageRoot)) {
                    $languageRoot = null;
                }
            }

            $extension    = new File($path, $languageRoot);
            $extensions[] = $extension;
        }

        return $extensions;
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
            throw new RuntimeException("Cannot get XML manifest for file extension in {$this->extensionRoot}");
        }

        // Initialize the result
        $result                = new ScanResult();
        $result->extensionType = 'file';

        // Get the extension name
        $fileExtension = strtolower($xmlDoc->getElementsByTagName('name')->item(0)->nodeValue);

        if (is_null($fileExtension)) {
            throw new RuntimeException("Cannot find the file extension name in the XML manifest for {$this->extensionRoot}");
        }

        $result->extension = $fileExtension;

        // Get the extension's <files> tags nested inside <fileset> tags
        $xpathOuter = new \DOMXPath($xmlDoc);

        /** @var \DOMElement $filesNode */
        foreach ($xpathOuter->query('/extension/fileset/files') as $filesNode) {
            if (!$filesNode->hasChildNodes() || !$filesNode->hasAttribute('target')) {
                continue;
            }

            $target = $filesNode->getAttribute('target');

            /** @var \DOMNode $fileNode */
            foreach ($filesNode->getElementsByTagName('file') as $fileNode) {
                $result->fileSets[$target]   = $result->fileSets[$target] ?? [];
                $result->fileSets[$target][] = $fileNode->textContent;
            }

            /** @var \DOMNode $fileNode */
            foreach ($filesNode->getElementsByTagName('folder') as $fileNode) {
                $result->folderSets[$target]   = $result->folderSets[$target] ?? [];
                $result->folderSets[$target][] = $fileNode->textContent;
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

        // Map the package files
        $files = [];

        foreach ($scan->fileSets as $relativeTarget => $sourceFiles) {
            $absoluteTarget = $this->siteRoot . '/' . trim($relativeTarget, '/');

            foreach ($sourceFiles as $sourceFile) {
                $basename                                 = basename($sourceFile);
                $files[$absoluteTarget . '/' . $basename] = $this->extensionRoot . '/' . $sourceFile;
            }
        }

        $result->hardfiles = array_merge($result->hardfiles, $files);

        // Map the package folders
        $folders = [];

        foreach ($scan->folderSets as $relativeTarget => $sourceFolders) {
            $absoluteTarget = $this->siteRoot . '/' . trim($relativeTarget, '/');

            foreach ($sourceFolders as $sourceFolder) {
                $basename                                   = basename($sourceFolder);
                $folders[$absoluteTarget . '/' . $basename] = $this->extensionRoot . '/' . $sourceFolder;
            }
        }

        $result->dirs = array_merge($result->dirs, $folders);

        // Map XML manifest
        if (!empty($this->xmlManifestPath)) {
            $result->files = array_merge($result->files, [
                $this->xmlManifestPath => $this->siteRoot . '/administrator/manifests/files/' . basename($this->xmlManifestPath),
            ]);
        }

        // Map script file
        if ($scriptPath = $this->getScriptAbsolutePath($scan)) {
            $result->files = array_merge($result->files, [
                $scriptPath => $this->siteRoot . '/administrator/manifests/files/' . $scan->extensionType . '/' . basename($scriptPath),
            ]);
        }

        return $result;
    }
}
