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
use Joomla\Github\Github as GithubClient;
use Joomla\Registry\Registry;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Parser that reads a Joomla extension manifest from the latest GitHub release.
 *
 * @since 4.0.0
 */
class Github extends Parser
{
    private SimpleXMLElement $xml;

    private string $tempDir;

    /**
     * @param string $url      GitHub repository URL (e.g. https://github.com/owner/repo)
     * @param Registry|null $options Optional options passed to the GitHub client (e.g. token)
     *
     * @throws RuntimeException
     * @since  4.0.0
     */
    public function __construct(string $url, ?Registry $options = null)
    {
        ['owner' => $owner, 'repo' => $repo] = $this->parseGithubUrl($url);

        $client  = new GithubClient($options ?? new Registry());
        $release = $client->repositories->releases->getLatest($owner, $repo);

        if (empty($release->zipball_url)) {
            throw new RuntimeException(sprintf('No release found for %s/%s.', $owner, $repo));
        }

        $zipFile       = $this->download($release->zipball_url);
        $this->tempDir = $this->extract($zipFile);
        unlink($zipFile);

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
     * @return array{owner: string, repo: string}
     * @throws RuntimeException
     */
    private function parseGithubUrl(string $url): array
    {
        $path  = parse_url($url, PHP_URL_PATH) ?? '';
        $parts = array_values(array_filter(explode('/', $path)));

        if (\count($parts) < 2) {
            throw new RuntimeException(sprintf('Cannot parse GitHub URL: %s', $url));
        }

        return ['owner' => $parts[0], 'repo' => $parts[1]];
    }

    /**
     * Downloads a URL to a temporary zip file and returns the file path.
     *
     * @throws RuntimeException
     */
    private function download(string $zipUrl): string
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'max_redirects'   => 10,
                'user_agent'      => 'Joomla-JED/1.0',
            ],
        ]);

        $content = file_get_contents($zipUrl, false, $context);

        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to download release zip from: %s', $zipUrl));
        }

        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jed_github_' . uniqid() . '.zip';
        file_put_contents($tmpFile, $content);

        return $tmpFile;
    }

    /**
     * Extracts a zip file to a temporary directory and returns the directory path.
     *
     * @throws RuntimeException
     */
    private function extract(string $zipFile): string
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jed_github_' . uniqid();

        $zip = new ZipArchive();

        if ($zip->open($zipFile) !== true) {
            throw new RuntimeException(sprintf('Cannot open zip file: %s', $zipFile));
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
