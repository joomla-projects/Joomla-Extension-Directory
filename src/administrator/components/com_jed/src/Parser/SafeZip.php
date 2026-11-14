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

use RuntimeException;
use ZipArchive;

/**
 * Extracts a zip a stranger supplied, within limits.
 *
 * `ZipArchive::extractTo()` writes whatever the archive says, however large it grows: a few
 * hundred kilobytes of zeros expand to gigabytes, and any logged-in user can submit one. This
 * extractor counts what it writes (not what the archive claims), refuses archives with too many
 * entries, and skips anything that would land outside the destination.
 *
 * @since 4.0.0
 */
final class SafeZip
{
    /**
     * Most entries an extension package may hold.
     *
     * @since 4.0.0
     */
    public const MAX_ENTRIES = 10000;

    /**
     * Most bytes the extracted package may take (250 MB).
     *
     * @since 4.0.0
     */
    public const MAX_BYTES = 262144000;

    /**
     * Extract a zip into a new directory.
     *
     * @param string $zipFile     The zip file.
     * @param string $destination The directory to create and extract into.
     * @param int    $maxEntries  The most entries to accept.
     * @param int    $maxBytes    The most bytes to write in total.
     *
     * @return void
     *
     * @throws RuntimeException When the file cannot be opened or a limit is exceeded; whatever was
     *                          written is removed first.
     *
     * @since 4.0.0
     */
    public static function extract(
        string $zipFile,
        string $destination,
        int $maxEntries = self::MAX_ENTRIES,
        int $maxBytes = self::MAX_BYTES
    ): void {
        $zip     = new ZipArchive();
        $created = !is_dir($destination);

        if ($zip->open($zipFile) !== true) {
            throw new RuntimeException(sprintf('Cannot open zip file: %s', $zipFile));
        }

        try {
            if ($zip->numFiles > $maxEntries) {
                throw new RuntimeException('The archive holds too many files.');
            }

            if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
                throw new RuntimeException('Cannot create the extraction directory.');
            }

            $root    = rtrim($destination, '/\\');
            $written = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if (!self::isSafeName($name)) {
                    continue;
                }

                $target = $root . '/' . $name;

                if (str_ends_with($name, '/')) {
                    if (!is_dir($target)) {
                        mkdir($target, 0775, true);
                    }

                    continue;
                }

                if (!is_dir(\dirname($target))) {
                    mkdir(\dirname($target), 0775, true);
                }

                $in  = $zip->getStream($name);
                $out = $in === false ? false : fopen($target, 'wb');

                if ($in === false || $out === false) {
                    continue;
                }

                while (!feof($in)) {
                    $chunk = fread($in, 65536);

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    $written += \strlen($chunk);

                    if ($written > $maxBytes) {
                        fclose($in);
                        fclose($out);

                        throw new RuntimeException('The archive expands to more than the allowed size.');
                    }

                    fwrite($out, $chunk);
                }

                fclose($in);
                fclose($out);
            }
        } catch (RuntimeException $e) {
            $zip->close();
            if ($created) {
                self::remove($destination);
            }

            throw $e;
        }

        $zip->close();
    }

    /**
     * Whether an entry name stays inside the destination.
     *
     * @param string $name The entry name as stored in the archive.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private static function isSafeName(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) {
            return false;
        }

        if ($name[0] === '/' || preg_match('~^[A-Za-z]:~', $name) === 1) {
            return false;
        }

        foreach (explode('/', $name) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove a directory tree.
     *
     * @param string $dir The directory.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private static function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
