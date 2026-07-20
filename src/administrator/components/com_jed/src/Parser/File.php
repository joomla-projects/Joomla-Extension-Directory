<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Installer\Installer;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Parser that reads a Joomla extension manifest from a local zip file.
 *
 * @since 4.0.0
 */
class File extends Parser
{
    private SimpleXMLElement $xml;

    private string $tempDir;

    /**
     * @param string $filePath Absolute path to a Joomla extension zip file
     *
     * @throws RuntimeException
     * @since  4.0.0
     */
    public function __construct(string $filePath)
    {
        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('File does not exist: %s', $filePath));
        }

        $this->tempDir = $this->extract($filePath);
        $this->loadManifest($this->tempDir);
    }

    public function __destruct()
    {
        if (!empty($this->tempDir) && is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function getOwner(): string
    {
        return (string) $this->xml->author;
    }

    public function getName(): string
    {
        return (string) $this->xml->name;
    }

    public function getChangelogUrl(): string
    {
        return (string) $this->xml->changelogurl;
    }

    public function getUpdateServerUrl(): string
    {
        return (string) ($this->xml->updateservers->server[0] ?? '');
    }

    public function getVersion(): string
    {
        return (string) $this->xml->version;
    }

    public function getAuthorUrl(): string
    {
        return (string) $this->xml->authorUrl;
    }

    public function getAuthorEmail(): string
    {
        return (string) $this->xml->authorEmail;
    }

    public function getExtensionTypes(): array
    {
        $types = [];

        if (isset($this->xml->files->file)) {
            foreach ($this->xml->files->file as $file) {
                $type = (string) ($file['type'] ?? '');

                if ($type !== '') {
                    $types[] = $type;
                }
            }
        }

        if (empty($types)) {
            $rootType = (string) ($this->xml['type'] ?? '');

            if ($rootType !== '') {
                $types[] = $rootType;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Extracts the given zip file to a temporary directory and returns its path.
     *
     * @throws RuntimeException
     */
    private function extract(string $filePath): string
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jed_file_' . uniqid();

        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new RuntimeException(sprintf('Cannot open zip file: %s', $filePath));
        }

        $zip->extractTo($tmpDir);
        $zip->close();

        return $tmpDir;
    }

    /**
     * Locates and loads the Joomla manifest XML from the given directory.
     *
     * @throws RuntimeException
     */
    private function loadManifest(string $dir): void
    {
        $installer = Installer::getInstance();
        $installer->setPath('source', $dir);

        if (!$installer->findManifest()) {
            throw new RuntimeException(sprintf('No valid Joomla manifest found in: %s', $dir));
        }

        $manifestPath = $installer->getPath('manifest');
        $xml          = simplexml_load_file($manifestPath);

        if ($xml === false) {
            throw new RuntimeException(sprintf('Cannot parse manifest XML: %s', $manifestPath));
        }

        $this->xml = $xml;
    }

    private function removeDirectory(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
    }
}
