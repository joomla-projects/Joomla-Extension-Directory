<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Feeds\Support;

/**
 * Fake cvelistV5 (raw.githubusercontent.com) and NVD servers, built from tests/fixtures/vel.
 *
 * The delta log is served the way GitHub serves it: Range requests get 206 slices, a matching
 * If-None-Match gets 304, a range past the end gets 416. A test can change the log between
 * requests ($log / $etag, or onRead) and make any request fail ($fail).
 *
 * Record ids decide what the record is: CVE-2026-9xxxx cycle through kindOf(), anything else is
 * one of the Joomla CNA's own records.
 */
final class FakeFeeds
{
    public const RAW = 'https://raw.githubusercontent.com/CVEProject/cvelistV5/main/';
    public const NVD = 'https://services.nvd.nist.gov/rest/json/cves/2.0';

    /** @var array|string The delta log as served now (array of entries, or raw text). */
    public static array|string $log = [];

    public static string $etag = '"e1"';

    /** @var array<int, array{0:int,1:string,2?:array}> Answer the n-th log request with this instead. */
    public static array $fail = [];

    /** @var callable|null fn (int $n): void, before the n-th log request is answered. */
    public static $onRead = null;

    public static int $reads = 0;

    public static bool $honorRange = true;

    /** @var list<array> NVD search results, all pages. */
    public static array $nvd = [];

    /** @var array<int, int> NVD startIndex => HTTP status to fail with. */
    public static array $nvdFail = [];

    /** @var list<string> Seed: ids the NVD lists for the Joomla CNA and for the keyword. */
    public static array $seedOurs    = [];
    public static array $seedKeyword = [];

    public static function reset(): void
    {
        self::$log        = [];
        self::$etag       = '"e1"';
        self::$fail       = [];
        self::$onRead     = null;
        self::$reads      = 0;
        self::$honorRange = true;
        self::$nvd        = [];
        self::$nvdFail    = [];
        self::$seedOurs   = [];
        self::$seedKeyword = [];
    }

    public static function install(): void
    {
        Cms::$http = static fn (string $url, array $headers, ?int $timeout) => self::answer($url, $headers);
    }

    public static function iso(int|float $ts): string
    {
        return gmdate('Y-m-d\TH:i:s', (int) $ts) . '.000Z';
    }

    public static function fixture(string $path): array
    {
        return json_decode((string) file_get_contents(JPATH_ROOT . '/tests/fixtures/vel/' . $path), true);
    }

    /** What a CVE-2026-9xxxx record is, by its number. */
    public static function kindOf(int $n): string
    {
        return ['ours', 'other', 'unrelated', 'unrelated', 'core', 'other', 'unrelated', 'missing', 'ours', 'broken', 'unrelated', 'rejected'][$n % 12];
    }

    /** A CVE 5 record of a kind, under another id. */
    public static function cveRecord(string $id, string $kind): array
    {
        $base = match ($kind) {
            'core'     => self::fixture('cve/cve-joomla-cna-core.json'),
            'other'    => self::fixture('cve/cve-other-cna-extension.json'),
            'rejected' => self::fixture('cve/cve-rejected.json'),
            'unrelated' => self::fixture('cve/cve-other-cna-extension.json'),
            default    => self::fixture('cve/cve-joomla-cna-extension.json'),
        };

        $base['cveMetadata']['cveId'] = $id;

        if ($kind === 'unrelated') {
            $base['cveMetadata']['assignerShortName'] = 'Wordfence';
            $base['cveMetadata']['assignerOrgId']     = 'b15e7b5b-3da4-40ae-a43c-f7aa60e62599';
            $base['containers']['cna']['affected']    = [['vendor' => 'acme', 'product' => 'Acme Forms', 'platforms' => ['WordPress'], 'defaultStatus' => 'unaffected', 'versions' => [['version' => '0', 'lessThan' => '2.0', 'status' => 'affected', 'versionType' => 'semver']]]];
            $base['containers']['cna']['descriptions'] = [['lang' => 'en', 'value' => 'Stored XSS in the Acme Forms plugin for WordPress before 2.0.']];
            $base['containers']['cna']['references']   = [['url' => 'https://example.com/acme']];
            unset($base['containers']['adp']);
        }

        $base['containers']['cna']['title'] = 'Test record ' . $id . ' (' . $kind . ')';

        return $base;
    }

    /**
     * A delta log: `$entries` entries 11 minutes apart, newest (`$newest`) first, `$perEntry` ids
     * each from CVE-2026-(90000 + `$base` offset). Entry 3 lists the first id again (a record
     * updated twice in the window) when `$repeat`.
     */
    public static function deltaLog(int $newest, int $entries = 8, int $perEntry = 4, int $first = 90000, bool $repeat = true): array
    {
        $log = [];
        $n   = 0;

        for ($e = 0; $e < $entries; $e++) {
            $entry = ['fetchTime' => self::iso($newest - $e * 660), 'numberOfChanges' => 0, 'new' => [], 'updated' => [], 'error' => []];

            for ($i = 0; $i < $perEntry; $i++, $n++) {
                $id = 'CVE-2026-' . ($first + $n);

                $entry[$i % 2 === 0 ? 'new' : 'updated'][] = [
                    'cveId'       => $id,
                    'cveOrgLink'  => 'https://www.cve.org/CVERecord?id=' . $id,
                    'githubLink'  => self::RAW . 'cves/2026/' . intdiv($first + $n, 1000) . 'xxx/' . $id . '.json',
                    'dateUpdated' => self::iso($newest - $e * 660),
                ];
            }

            if ($repeat && $e === 3) {
                $entry['updated'][] = ['cveId' => 'CVE-2026-' . $first, 'githubLink' => self::RAW . 'cves/2026/' . intdiv($first, 1000) . 'xxx/CVE-2026-' . $first . '.json'];
            }

            $entry['numberOfChanges'] = \count($entry['new']) + \count($entry['updated']);
            $log[]                    = $entry;
        }

        return $log;
    }

    /** One entry listing one id. */
    public static function entry(int|float $at, string $id): array
    {
        return ['fetchTime' => self::iso($at), 'numberOfChanges' => 1, 'new' => [['cveId' => $id, 'githubLink' => self::RAW . 'cves/2026/' . intdiv((int) substr($id, 9), 1000) . 'xxx/' . $id . '.json']], 'updated' => []];
    }

    /** The ids a log lists, sorted. */
    public static function ids(array $log): array
    {
        $ids = [];

        foreach ($log as $entry) {
            foreach (['new', 'updated'] as $bucket) {
                foreach ($entry[$bucket] as $item) {
                    $ids[$item['cveId']] = true;
                }
            }
        }

        $ids = array_keys($ids);
        sort($ids);

        return $ids;
    }

    /** NVD vulnerabilities: `$count` records cycling through the NVD fixtures. */
    public static function nvdResults(int $count): array
    {
        $kinds = ['nvd/nvd-extension-with-cpe.json', 'nvd/nvd-core.json', 'nvd/nvd-unrelated-keyword-hit.json', 'nvd/nvd-extension-no-cpe.json', 'nvd/nvd-rejected.json'];
        $out   = [];

        for ($i = 0; $i < $count; $i++) {
            $v = self::fixture($kinds[$i % \count($kinds)])['vulnerabilities'][0];

            $v['cve']['id']                        = 'CVE-2026-' . (70000 + $i);
            $v['cve']['descriptions'][0]['value'] .= ' #' . $i;
            $out[]                                 = $v;
        }

        return $out;
    }

    public static function logText(): string
    {
        return \is_string(self::$log) ? self::$log : json_encode(self::$log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private static function answer(string $url, array $headers): array
    {
        if ($url === self::RAW . 'cves/deltaLog.json') {
            self::$reads++;

            if (self::$onRead !== null) {
                (self::$onRead)(self::$reads);
            }

            if (isset(self::$fail[self::$reads])) {
                return self::$fail[self::$reads];
            }

            if (isset($headers['If-None-Match']) && $headers['If-None-Match'] === self::$etag) {
                return [304, ''];
            }

            $body = self::logText();

            if (!self::$honorRange || !isset($headers['Range'])) {
                return [200, $body, ['ETag' => [self::$etag]]];
            }

            preg_match('/bytes=(\d+)-(\d+)/', $headers['Range'], $m);

            if ((int) $m[1] >= \strlen($body)) {
                return [416, '', ['ETag' => [self::$etag]]];
            }

            return [206, substr($body, (int) $m[1], (int) $m[2] - (int) $m[1] + 1), ['ETag' => [self::$etag]]];
        }

        if (str_starts_with($url, self::RAW . 'cves/')) {
            $id   = basename($url, '.json');
            $kind = preg_match('/^CVE-2026-9(\d{4})$/', $id, $m) ? self::kindOf((int) $m[1]) : 'ours';

            return match ($kind) {
                'missing' => [404, 'Not Found'],
                'broken'  => [500, 'Internal Server Error'],
                default   => [200, json_encode(self::cveRecord($id, $kind))],
            };
        }

        if (str_starts_with($url, self::NVD)) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

            if (isset($q['cveId'])) {
                $v              = self::fixture('nvd/nvd-extension-no-cpe.json')['vulnerabilities'][0];
                $v['cve']['id'] = $q['cveId'];

                return [200, json_encode(['totalResults' => 1, 'vulnerabilities' => [$v]])];
            }

            $list = static fn (array $ids): array => [200, json_encode(['totalResults' => \count($ids), 'vulnerabilities' => array_map(static fn ($id) => ['cve' => ['id' => $id]], $ids)])];

            if (isset($q['sourceIdentifier'])) {
                return $list(self::$seedOurs);
            }

            if (!isset($q['lastModStartDate']) && !isset($q['pubStartDate'])) {
                return $list(self::$seedKeyword);
            }

            $start = (int) ($q['startIndex'] ?? 0);

            if (isset(self::$nvdFail[$start])) {
                return [self::$nvdFail[$start], ''];
            }

            return [200, json_encode([
                'resultsPerPage'  => (int) $q['resultsPerPage'],
                'startIndex'      => $start,
                'totalResults'    => \count(self::$nvd),
                'vulnerabilities' => \array_slice(self::$nvd, $start, (int) $q['resultsPerPage']),
            ])];
        }

        return [404, ''];
    }
}
