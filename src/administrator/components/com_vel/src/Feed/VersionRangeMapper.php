<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Feed;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Version\Version;

/**
 * Turns the ways CVE records and NVD configurations spell a version range into the VEL's
 * inclusive from/to plus a fixed version.
 *
 * CVE 5.x `versions[]`:  {version, lessThan | lessThanOrEqual, status}; "version" alone is one
 * version; the older single-string "1.0.0-15.0.0" is read too.
 * NVD `cpeMatch`:        versionStartIncluding / versionStartExcluding / versionEndIncluding /
 *                        versionEndExcluding, or the version inside the CPE name.
 *
 * An exclusive upper bound (lessThan, versionEndExcluding) cannot be stored as an inclusive
 * "to", so it becomes the product's fixed version and "to" is the same value: the check API
 * treats anything at or above the fixed version as unaffected, which makes the two agree.
 *
 * @since 4.0.0
 */
final class VersionRangeMapper
{
    /**
     * @param array $entries CVE 5.x `versions[]`.
     *
     * @return array{ranges: array<int, array{from:string,to:string}>, fixed: string}
     *
     * @since 4.0.0
     */
    public static function fromCve(array $entries): array
    {
        $ranges = [];
        $fixed  = '';

        foreach ($entries as $entry) {
            if (!\is_array($entry) || (string) ($entry['status'] ?? 'affected') !== 'affected') {
                if (\is_array($entry) && (string) ($entry['status'] ?? '') === 'unaffected' && $fixed === '' && !empty($entry['version']) && self::looksLikeVersion((string) $entry['version'])) {
                    $fixed = trim((string) $entry['version']);
                }

                continue;
            }

            $version = trim((string) ($entry['version'] ?? ''));
            $from    = $version === '' || $version === '0' || $version === '*' ? Version::ANY : $version;

            if (isset($entry['lessThanOrEqual'])) {
                $to       = trim((string) $entry['lessThanOrEqual']);
                $ranges[] = ['from' => $from, 'to' => $to === '*' || $to === '' ? Version::ANY : $to];

                continue;
            }

            if (isset($entry['lessThan'])) {
                $to = trim((string) $entry['lessThan']);

                if ($to === '*' || $to === '') {
                    $ranges[] = ['from' => $from, 'to' => Version::ANY];
                } else {
                    $ranges[] = ['from' => $from, 'to' => $to];
                    $fixed    = $fixed !== '' ? $fixed : $to;
                }

                continue;
            }

            // "1.0.0-15.0.0": two versions with a dash between them.
            if (preg_match('/^v?(\d[\w.]*)\s*[-–]\s*v?(\d[\w.]*|\*)$/', $version, $m)) {
                $ranges[] = ['from' => $m[1], 'to' => $m[2] === '*' ? Version::ANY : $m[2]];

                continue;
            }

            if (preg_match('/^(?:<=|through|up to)\s*v?(\d[\w.]*)$/i', $version, $m)) {
                $ranges[] = ['from' => Version::ANY, 'to' => $m[1]];

                continue;
            }

            if (preg_match('/^<\s*v?(\d[\w.]*)$/', $version, $m)) {
                $ranges[] = ['from' => Version::ANY, 'to' => $m[1]];
                $fixed    = $fixed !== '' ? $fixed : $m[1];

                continue;
            }

            if ($from === Version::ANY) {
                $ranges[] = ['from' => Version::ANY, 'to' => Version::ANY];

                continue;
            }

            // One version.
            $ranges[] = ['from' => $version, 'to' => $version];
        }

        return ['ranges' => self::dedupe($ranges), 'fixed' => $fixed];
    }

    /**
     * @param array $match One NVD `cpeMatch` entry.
     *
     * @return array{ranges: array<int, array{from:string,to:string}>, fixed: string}|null  Null when not vulnerable.
     *
     * @since 4.0.0
     */
    public static function fromCpeMatch(array $match): ?array
    {
        if (empty($match['vulnerable'])) {
            return null;
        }

        $from  = Version::ANY;
        $to    = Version::ANY;
        $fixed = '';

        if (!empty($match['versionStartIncluding'])) {
            $from = (string) $match['versionStartIncluding'];
        } elseif (!empty($match['versionStartExcluding'])) {
            // Exclusive lower bound: the next version up is unknown; keep the bound, note it.
            $from = (string) $match['versionStartExcluding'];
        }

        if (!empty($match['versionEndIncluding'])) {
            $to = (string) $match['versionEndIncluding'];
        } elseif (!empty($match['versionEndExcluding'])) {
            $to    = (string) $match['versionEndExcluding'];
            $fixed = $to;
        }

        // The version in the CPE name itself, when it is a specific one.
        $parts = self::cpeParts((string) ($match['criteria'] ?? ''));

        if ($from === Version::ANY && $to === Version::ANY && $parts !== null && $parts['version'] !== '*' && $parts['version'] !== '-' && $parts['version'] !== '') {
            $from = $parts['version'];
            $to   = $parts['version'];
        }

        return ['ranges' => [['from' => $from, 'to' => $to]], 'fixed' => $fixed];
    }

    /**
     * @param string $cpe A CPE 2.3 formatted string.
     *
     * @return array{part:string,vendor:string,product:string,version:string,target_sw:string}|null
     *
     * @since 4.0.0
     */
    public static function cpeParts(string $cpe): ?array
    {
        if (!str_starts_with($cpe, 'cpe:2.3:')) {
            return null;
        }

        // Unescape "\:" inside components before splitting.
        $placeholder = "\0";
        $parts       = explode(':', str_replace('\\:', $placeholder, substr($cpe, 8)));
        $parts       = array_map(static fn (string $p): string => str_replace($placeholder, ':', $p), $parts);

        if (\count($parts) < 11) {
            return null;
        }

        return [
            'part'      => $parts[0],
            'vendor'    => str_replace('\\', '', $parts[1]),
            'product'   => str_replace('\\', '', $parts[2]),
            'version'   => str_replace('\\', '', $parts[3]),
            'target_sw' => $parts[8],
        ];
    }

    /**
     * @param string $value A candidate.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private static function looksLikeVersion(string $value): bool
    {
        return (bool) preg_match('/^v?\d/', trim($value));
    }

    /**
     * @param array $ranges Ranges.
     *
     * @return array
     *
     * @since 4.0.0
     */
    private static function dedupe(array $ranges): array
    {
        $seen = [];
        $out  = [];

        foreach ($ranges as $range) {
            $key = $range['from'] . '|' . $range['to'];

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[]      = $range;
            }
        }

        return $out;
    }
}
