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

use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
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
     * Extension types in descending order of preference when multiple manifests are found.
     *
     * @var array<string, int>
     */
    private const TYPE_PRIORITY = [
        'package'   => 0,
        'component' => 1,
        'plugin'    => 2,
        'module'    => 3,
        'template'  => 4,
        'file'      => 5,
    ];

    /**
     * Locates and loads the Joomla manifest XML from the given directory.
     *
     * All XML files in the directory tree are searched for valid Joomla manifests. When more
     * than one is found, the manifest is picked according to self::TYPE_PRIORITY, i.e. a package
     * manifest is preferred over a component manifest, a component manifest over a plugin
     * manifest, and so on down to module, template and file manifests.
     *
     * @throws RuntimeException
     */
    private function loadManifest(string $dir): void
    {
        $dir = Path::clean($dir);

        if (!is_dir($dir)) {
            throw new RuntimeException(sprintf('Extracted source directory not found: %s', $dir));
        }

        $xmlFiles = Folder::files($dir, '.xml$', true, true);

        $bestManifest = null;
        $bestPriority = null;

        foreach ($xmlFiles as $file) {
            $manifest = $this->isManifest($file);

            if ($manifest === null) {
                continue;
            }

            $type     = (string) $manifest['type'];
            $priority = self::TYPE_PRIORITY[$type] ?? count(self::TYPE_PRIORITY);

            if ($bestPriority === null || $priority < $bestPriority) {
                $bestManifest = $manifest;
                $bestPriority = $priority;

                if ($priority === 0) {
                    break;
                }
            }
        }

        if ($bestManifest === null) {
            throw new RuntimeException(sprintf('No valid Joomla manifest found in: %s', $dir));
        }

        $this->xml = $bestManifest;
    }

    /**
     * Determines whether the given XML file is a valid Joomla installation manifest file.
     */
    private function isManifest(string $file): ?SimpleXMLElement
    {
        $xml = simplexml_load_file($file);

        if ($xml === false || $xml->getName() !== 'extension') {
            return null;
        }

        return $xml;
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
