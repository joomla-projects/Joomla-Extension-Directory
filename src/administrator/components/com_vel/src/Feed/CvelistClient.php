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

use Joomla\CMS\Language\Text;
use Joomla\Http\Http;
use RuntimeException;

/**
 * The public `CVEProject/cvelistV5` mirror on raw GitHub, as measured on 2026-11-05 (R-02).
 *
 * `cves/deltaLog.json` is **37 MB**, updated every ~11 minutes, a rolling 30 days, and -
 * the fact this class is built on - **newest-first and pretty-printed**, served with
 * `Accept-Ranges: bytes` and an `ETag`. So the newest entries are the first bytes of the file,
 * and a run reads only as much of it as the time since the last run needs:
 *
 *  1. `If-None-Match` with the stored ETag: a 304 is a clean no-op.
 *  2. `Range: bytes=0-<n>`, n sized from the gap at ~13.6 KB per entry and ~5 entries an hour,
 *     clamped between 64 KB and `cna_range_max_kb`. Expect 206.
 *  3. Parse forward to the last complete top-level entry; drop the partial tail.
 *  4. If the oldest entry read is still newer than the last run's window end, the range was
 *     too small: double and retry up to the cap, then report `partial` rather than skip.
 *  5. If the server ignores the Range (answers 200), read the whole body only when it is under
 *     `cna_full_fetch_max_mb`.
 *
 * `delta.json` is the single newest ~11-minute window and must not be polled as the feed - an
 * hourly poll of it would lose ~80% of changes. It serves as the *Run now* liveness probe.
 *
 * No credentials and no token: raw.githubusercontent.com is served by Fastly outside the
 * GitHub API quota and returns no rate-limit headers. Volume control - the Range, a per-run
 * fetch cap and a courtesy pause between record fetches - is the mitigation.
 *
 * Every delta item carries `githubLink`, the complete raw URL of the record, so the bucket
 * arithmetic (`cves/<year>/<floor(seq/1000)>xxx/`, bucket not zero-padded) is a fallback only.
 *
 * @since 4.0.0
 */
final class CvelistClient
{
    public const RAW_BASE = 'https://raw.githubusercontent.com/CVEProject/cvelistV5/main/';

    /**
     * Average bytes per delta entry, measured.
     *
     * @since 4.0.0
     */
    public const BYTES_PER_ENTRY = 13600;

    /**
     * Delta entries per hour, measured (p50 cadence ~11 minutes).
     *
     * @since 4.0.0
     */
    public const ENTRIES_PER_HOUR = 5.5;

    public const MIN_RANGE_BYTES = 65536;

    /**
     * Seconds between record fetches: a courtesy, not a quota.
     *
     * @since 4.0.0
     */
    public const COURTESY_PAUSE = 0.2;

    /**
     * @param Http $http              HTTP client.
     * @param int  $rangeMaxBytes     Cap on the Range read.
     * @param int  $fullFetchMaxBytes Cap on an unranged read when Range is ignored.
     * @param int  $timeout           Seconds per request. A run driven from the browser passes
     *                                less than the default so one slow request cannot outlast
     *                                the web server's gateway timeout.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly Http $http,
        private readonly int $rangeMaxBytes = 2 * 1024 * 1024,
        private readonly int $fullFetchMaxBytes = 64 * 1024 * 1024,
        private readonly int $timeout = 60
    ) {
    }

    /**
     * The delta log entries newer than a moment, newest first.
     *
     * @param \DateTimeImmutable|null $since       The last run's window end, or null for "as much as the cap allows".
     * @param string|null             $etag        The ETag stored from the last run.
     *
     * @return array{entries: array, etag: ?string, complete: bool, not_modified: bool, bytes: int}
     *         `complete` is false when the oldest entry read is still newer than `$since`
     *         (the cap was reached before the gap was covered).
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function deltaEntriesSince(?\DateTimeImmutable $since, ?string $etag): array
    {
        $hours = $since === null ? 72.0 : max(1.0, (time() - $since->getTimestamp()) / 3600);
        $bytes = (int) max(self::MIN_RANGE_BYTES, min($this->rangeMaxBytes, ceil($hours * self::ENTRIES_PER_HOUR * self::BYTES_PER_ENTRY * 1.5)));

        while (true) {
            $result = $this->rangeRead(self::RAW_BASE . 'cves/deltaLog.json', $bytes, $etag);

            if ($result['not_modified']) {
                return ['entries' => [], 'etag' => $etag, 'complete' => true, 'not_modified' => true, 'bytes' => 0];
            }

            $entries = self::parseHead($result['body'], $result['complete_body']);

            // Covered the gap when the oldest entry read predates the window end, or when the
            // whole file was read.
            $oldest   = $entries !== [] ? (string) (end($entries)['fetchTime'] ?? '') : '';
            $covered  = $result['complete_body'] || $since === null || ($oldest !== '' && strtotime($oldest) !== false && strtotime($oldest) <= $since->getTimestamp());

            if ($covered || $bytes >= $this->rangeMaxBytes) {
                $kept = [];

                foreach ($entries as $entry) {
                    $fetchTime = strtotime((string) ($entry['fetchTime'] ?? ''));

                    if ($since !== null && $fetchTime !== false && $fetchTime <= $since->getTimestamp()) {
                        break;
                    }

                    $kept[] = $entry;
                }

                return ['entries' => $kept, 'etag' => $result['etag'], 'complete' => $covered, 'not_modified' => false, 'bytes' => $bytes];
            }

            $bytes = (int) min($this->rangeMaxBytes, $bytes * 2);
        }
    }

    /**
     * How much of the log the first slice of a read asks for: enough for the gap at the measured
     * rate, with a margin, between MIN_RANGE_BYTES and the range cap.
     *
     * @param \DateTimeImmutable|null $since Where the read has to reach back to.
     *
     * @return int  Bytes.
     *
     * @since 4.0.0
     */
    public function firstSliceBytes(?\DateTimeImmutable $since): int
    {
        $hours = $since === null ? 72.0 : max(1.0, (time() - $since->getTimestamp()) / 3600);

        return (int) max(self::MIN_RANGE_BYTES, min($this->rangeMaxBytes, ceil($hours * self::ENTRIES_PER_HOUR * self::BYTES_PER_ENTRY * 1.5)));
    }

    /**
     * @return int  Bytes per slice after the first: the range cap.
     *
     * @since 4.0.0
     */
    public function sliceBytes(): int
    {
        return max(self::MIN_RANGE_BYTES, $this->rangeMaxBytes);
    }

    /**
     * @return int  The largest slice a single entry may force (the unranged-read cap).
     *
     * @since 4.0.0
     */
    public function maxSliceBytes(): int
    {
        return max($this->sliceBytes(), $this->fullFetchMaxBytes);
    }

    /**
     * One slice of `deltaLog.json`, from byte `$offset`, as complete entries. A read of the log
     * is a series of these, each starting where the last complete entry of the one before ended,
     * so a gap of any length up to the log's 30 days can be read without holding it in memory.
     *
     * Strict, because a log that does not load must not look like an empty one: anything that
     * is not a well-formed array of entries with a `fetchTime` throws. The one exception is a
     * continuation slice that does not start at an entry (`aligned` false): the file changed
     * between two slices and the caller reads it again from the top.
     *
     * @param int         $offset The first byte (0, or a `next` from the slice before).
     * @param int         $bytes  How many bytes to ask for.
     * @param string|null $etag   If-None-Match, for the first slice only.
     *
     * @return array{not_modified: bool, entries: list<array>, next: ?int, aligned: bool, whole: bool, etag: ?string, bytes: int}
     *         `next` is the byte offset to continue from, or null at the end of the log;
     *         `whole` says the server ignored the Range and sent the whole file.
     *
     * @throws RuntimeException  When the log cannot be fetched or does not parse.
     *
     * @since 4.0.0
     */
    public function deltaSlice(int $offset, int $bytes, ?string $etag = null): array
    {
        $result = $this->rangeRead(self::RAW_BASE . 'cves/deltaLog.json', $bytes, $offset === 0 ? $etag : null, $offset);
        $slice  = ['not_modified' => false, 'entries' => [], 'next' => null, 'aligned' => true, 'whole' => false, 'etag' => $result['etag'], 'bytes' => \strlen($result['body'])];

        if ($result['not_modified']) {
            return ['not_modified' => true, 'etag' => $etag] + $slice;
        }

        if (!empty($result['unsatisfiable'])) {
            return ['aligned' => false] + $slice;
        }

        if ($result['complete_body']) {
            $decoded = json_decode($result['body'], true);

            if (!\is_array($decoded) || !array_is_list($decoded)) {
                throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : 'not a JSON array'));
            }

            foreach ($decoded as $i => $entry) {
                self::assertEntry($entry, $i);
            }

            return ['entries' => $decoded, 'whole' => true] + $slice;
        }

        $parsed = self::parseSlice($result['body'], $offset === 0);

        return [
            'entries' => $parsed['entries'],
            'next'    => $parsed['next'] === null ? null : $offset + $parsed['next'],
            'aligned' => $parsed['aligned'],
        ] + $slice;
    }

    /**
     * Parse a slice of the pretty-printed JSON array of delta entries: every complete top-level
     * entry, and where the first incomplete one begins.
     *
     * @param string $text    The bytes read.
     * @param bool   $atStart Whether they start at the top of the file (the `[`), or at an entry.
     *
     * @return array{entries: list<array>, next: ?int, aligned: bool}
     *         `next`: offset within `$text` to continue from; null when the array ended here.
     *         `aligned`: false when a continuation does not start at a delta entry.
     *
     * @throws RuntimeException  When the slice is not a well-formed part of the log.
     *
     * @since 4.0.0
     */
    public static function parseSlice(string $text, bool $atStart): array
    {
        $length = \strlen($text);
        $i      = strspn($text, " \t\r\n");

        if ($atStart) {
            if ($i >= $length || $text[$i] !== '[') {
                throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', 'not a JSON array'));
            }

            $i++;
        } elseif ($i >= $length || $text[$i] !== '{') {
            return ['entries' => [], 'next' => null, 'aligned' => false];
        }

        $entries  = [];
        $depth    = 0;
        $inString = false;
        $escape   = false;
        $entryAt  = -1;

        for (; $i < $length; $i++) {
            $c = $text[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($depth === 0) {
                // Between entries only separators may appear.
                if ($c === '{') {
                    $entryAt = $i;
                    $depth   = 1;
                } elseif ($c === ']') {
                    return ['entries' => $entries, 'next' => null, 'aligned' => true];
                } elseif ($c !== ',' && !ctype_space($c)) {
                    throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', 'unexpected "' . $c . '" between entries'));
                }

                continue;
            }

            if ($c === '"') {
                $inString = true;
            } elseif ($c === '{' || $c === '[') {
                $depth++;
            } elseif ($c === '}' || $c === ']') {
                $depth--;

                if ($depth === 0) {
                    $entry = json_decode(substr($text, $entryAt, $i - $entryAt + 1), true);

                    if (!$atStart && $entries === [] && !(\is_array($entry) && isset($entry['fetchTime']))) {
                        // A continuation that starts at some inner object: the file moved.
                        return ['entries' => [], 'next' => null, 'aligned' => false];
                    }

                    $entries[] = self::assertEntry($entry, \count($entries));
                    $entryAt   = -1;
                }
            }
        }

        // Cut: continue from the entry that did not fit, or from the end when none had begun.
        return ['entries' => $entries, 'next' => $entryAt >= 0 ? $entryAt : $length, 'aligned' => true];
    }

    /**
     * @param mixed $entry A decoded delta entry.
     * @param int   $index Its position, for the message.
     *
     * @return array  The entry.
     *
     * @throws RuntimeException  When it is not one.
     *
     * @since 4.0.0
     */
    private static function assertEntry(mixed $entry, int $index): array
    {
        if (!\is_array($entry) || !isset($entry['fetchTime']) || !\is_string($entry['fetchTime']) || strtotime($entry['fetchTime']) === false) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', 'entry ' . ($index + 1) . ' has no valid fetchTime'));
        }

        foreach (['new', 'updated'] as $bucket) {
            if (isset($entry[$bucket]) && !\is_array($entry[$bucket])) {
                throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', 'entry ' . ($index + 1) . ' has a malformed "' . $bucket . '" list'));
            }
        }

        return $entry;
    }

    /**
     * The single newest delta window - a liveness probe.
     *
     * @return array|null
     *
     * @since 4.0.0
     */
    public function latestDelta(): ?array
    {
        $decoded = $this->getJson(self::RAW_BASE . 'cves/delta.json');

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @param string $cveId The id.
     *
     * @return array|null  The record, or null when there is none (404).
     *
     * @throws RuntimeException  Other failures.
     *
     * @since 4.0.0
     */
    public function record(string $cveId): ?array
    {
        $url = self::recordUrl($cveId);

        if ($url === null) {
            return null;
        }

        return $this->recordByUrl($url);
    }

    /**
     * @param string $url A raw record URL (the delta item's `githubLink`).
     *
     * @return array|null
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function recordByUrl(string $url): ?array
    {
        if (!str_starts_with($url, self::RAW_BASE)) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_FOREIGN_URL', $url));
        }

        usleep((int) (self::COURTESY_PAUSE * 1000000));

        try {
            $decoded = $this->getJson($url);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw $e;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @param string $cveId The id.
     *
     * @return string|null
     *
     * @since 4.0.0
     */
    public static function recordUrl(string $cveId): ?string
    {
        $cveId = strtoupper(trim($cveId));

        if (!preg_match('/^CVE-(\d{4})-(\d{4,})$/', $cveId, $m)) {
            return null;
        }

        return self::RAW_BASE . 'cves/' . $m[1] . '/' . (int) floor(((int) $m[2]) / 1000) . 'xxx/' . $cveId . '.json';
    }

    /**
     * Read the first bytes of a resource.
     *
     * @param string      $url    The URL.
     * @param int         $bytes  How many.
     * @param string|null $etag   If-None-Match.
     * @param int         $offset The first byte.
     *
     * @return array{body: string, etag: ?string, not_modified: bool, complete_body: bool, unsatisfiable?: bool}
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function rangeRead(string $url, int $bytes, ?string $etag, int $offset = 0): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => 'Joomla-VEL/2.0 (+https://extensions.joomla.org/vulnerable-extensions)',
            'Range'      => 'bytes=' . $offset . '-' . ($offset + $bytes - 1),
        ];

        if ($etag !== null && $etag !== '') {
            $headers['If-None-Match'] = $etag;
        }

        try {
            $response = $this->http->get($url, $headers, $this->timeout);
        } catch (\Throwable $e) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_REQUEST_FAILED', $e->getMessage()), 0, $e);
        }

        $status  = $response->getStatusCode();
        $newEtag = trim($response->getHeaderLine('ETag')) ?: null;

        if ($status === 304) {
            return ['body' => '', 'etag' => $etag, 'not_modified' => true, 'complete_body' => false];
        }

        if ($status === 416 && $offset > 0) {
            // Past the end: the file is shorter than when the read began (it changed).
            return ['body' => '', 'etag' => $newEtag, 'not_modified' => false, 'complete_body' => false, 'unsatisfiable' => true];
        }

        if ($status === 206) {
            return ['body' => (string) $response->getBody(), 'etag' => $newEtag, 'not_modified' => false, 'complete_body' => false];
        }

        if ($status === 200) {
            // The Range was ignored. Acceptable only for a modest file.
            $length = (int) $response->getHeaderLine('Content-Length');
            $body   = (string) $response->getBody();

            if (($length > 0 ? $length : \strlen($body)) > $this->fullFetchMaxBytes) {
                throw new RuntimeException(Text::_('COM_VEL_ERROR_CVELIST_RANGE_IGNORED'));
            }

            return ['body' => $body, 'etag' => $newEtag, 'not_modified' => false, 'complete_body' => true];
        }

        throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_STATUS', $status, $url));
    }

    /**
     * Parse the head of a pretty-printed JSON array of delta entries, keeping every complete
     * top-level entry and discarding the truncated tail.
     *
     * @param string $head     The bytes read.
     * @param bool   $complete Whether they are the whole file.
     *
     * @return array<int, array>
     *
     * @since 4.0.0
     */
    public static function parseHead(string $head, bool $complete): array
    {
        if ($complete) {
            $decoded = json_decode($head, true);

            return \is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
        }

        $entries = [];
        $start   = strpos($head, '[');

        if ($start === false) {
            return [];
        }

        $depth    = 0;
        $inString = false;
        $escape   = false;
        $entryAt  = -1;
        $length   = \strlen($head);

        for ($i = $start + 1; $i < $length; $i++) {
            $c = $head[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($c === '"') {
                $inString = true;
            } elseif ($c === '{') {
                if ($depth === 0) {
                    $entryAt = $i;
                }

                $depth++;
            } elseif ($c === '}') {
                $depth--;

                if ($depth === 0 && $entryAt >= 0) {
                    $entry = json_decode(substr($head, $entryAt, $i - $entryAt + 1), true);

                    if (\is_array($entry) && isset($entry['fetchTime'])) {
                        $entries[] = $entry;
                    }

                    $entryAt = -1;
                }
            } elseif ($c === ']' && $depth === 0) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @param string $url The URL.
     *
     * @return mixed  Decoded JSON.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function getJson(string $url): mixed
    {
        $headers = ['Accept' => 'application/json', 'User-Agent' => 'Joomla-VEL/2.0 (+https://extensions.joomla.org/vulnerable-extensions)'];

        try {
            $response = $this->http->get($url, $headers, min(30, $this->timeout));
        } catch (\Throwable $e) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_REQUEST_FAILED', $e->getMessage()), 0, $e);
        }

        $status = $response->getStatusCode();

        if ($status >= 400) {
            // The status is the code, so callers need not read it out of a translated message.
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_STATUS', $status, $url), $status);
        }

        $decoded = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_INVALID_JSON', $url));
        }

        return $decoded;
    }
}
