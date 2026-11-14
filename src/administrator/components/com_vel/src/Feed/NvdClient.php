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

use DateTimeImmutable;
use DateTimeZone;
use Joomla\CMS\Language\Text;
use Joomla\Http\Http;
use RuntimeException;

/**
 * NVD API 2.0, as measured on 2026-11-05 (R-01).
 *
 * The one fact that shapes this class: **NVD reports parameter errors as HTTP 404 with an empty
 * body and the reason in a `message` response header.** A 404 *with* that header is our bug and
 * fails the run loudly; a 404 *without* it on a `cveId` lookup is "no such record". Treating
 * every 404 as "not found" would report a clean run on every malformed request.
 *
 * Limits, enforced: windows of at most 120 days, `resultsPerPage` at most 2000. Rate limits
 * are the documented 5 requests per rolling 30 s without a key and 50 with one; the service
 * sends no rate-limit headers at all, so the pause is fixed and a 403 or 429 trips the run.
 * Timestamps are accepted in several forms; this client sends `…Z` so the intent is in the URL.
 *
 * @since 4.0.0
 */
final class NvdClient
{
    public const BASE = 'https://services.nvd.nist.gov/rest/json/cves/2.0';

    public const MAX_WINDOW_DAYS = 120;
    public const MAX_PER_PAGE    = 2000;

    /**
     * @var float
     *
     * @since 4.0.0
     */
    private float $pause;

    /**
     * @var float
     *
     * @since 4.0.0
     */
    private float $lastRequest = 0.0;

    /**
     * @param Http   $http    HTTP client.
     * @param string $apiKey  Optional API key.
     * @param int    $timeout Seconds per request.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly Http $http, private readonly string $apiKey = '', private readonly int $timeout = 60)
    {
        $this->pause = $apiKey !== '' ? 1.0 : 6.5;
    }

    /**
     * When the last request was sent (Unix time, 0.0 for never). A run split over several web
     * requests builds a new client for each one; carrying this over keeps the rate limit.
     *
     * @return float
     *
     * @since 4.0.0
     */
    public function lastRequestAt(): float
    {
        return $this->lastRequest;
    }

    /**
     * Continue the throttle of an earlier client (see {@see self::lastRequestAt()}).
     *
     * @param float $at Unix time of the last request.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function resumeThrottle(float $at): void
    {
        $this->lastRequest = max($this->lastRequest, $at);
    }

    /**
     * One page of CVEs matching a keyword, modified in a window.
     *
     * @param string            $keyword    `keywordSearch`.
     * @param DateTimeImmutable $from       Window start.
     * @param DateTimeImmutable $to         Window end (at most 120 days after start).
     * @param int               $startIndex Paging offset.
     * @param int               $perPage    Page size.
     * @param string            $dateField  `lastMod` (default) or `pub`.
     *
     * @return array  The decoded envelope (`totalResults`, `resultsPerPage`, `vulnerabilities[]`).
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function search(string $keyword, DateTimeImmutable $from, DateTimeImmutable $to, int $startIndex = 0, int $perPage = 500, string $dateField = 'lastMod'): array
    {
        return $this->get([
            'keywordSearch'         => $keyword,
            $dateField . 'StartDate' => self::stamp($from),
            $dateField . 'EndDate'   => self::stamp($to),
            'resultsPerPage'        => max(1, min(self::MAX_PER_PAGE, $perPage)),
            'startIndex'            => max(0, $startIndex),
        ]);
    }

    /**
     * One page of CVEs matching a keyword, with no date window at all (the whole corpus: 1,633
     * records for "Joomla", which fits in one page of 2000).
     *
     * @param string $keyword    `keywordSearch`.
     * @param int    $startIndex Paging offset.
     * @param int    $perPage    Page size.
     *
     * @return array
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function keyword(string $keyword, int $startIndex = 0, int $perPage = self::MAX_PER_PAGE): array
    {
        return $this->get([
            'keywordSearch'  => $keyword,
            'resultsPerPage' => max(1, min(self::MAX_PER_PAGE, $perPage)),
            'startIndex'     => max(0, $startIndex),
        ]);
    }

    /**
     * One page of CVEs by their NVD source identifier (`security@joomla.org` for ours).
     *
     * @param string                 $sourceIdentifier The identifier.
     * @param DateTimeImmutable|null $from             Window start, or null for no window.
     * @param DateTimeImmutable|null $to               Window end.
     * @param int                    $startIndex       Paging offset.
     * @param int                    $perPage          Page size.
     * @param string                 $dateField        `lastMod` or `pub`.
     *
     * @return array
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function bySource(string $sourceIdentifier, ?DateTimeImmutable $from, ?DateTimeImmutable $to, int $startIndex = 0, int $perPage = 500, string $dateField = 'lastMod'): array
    {
        $query = [
            'sourceIdentifier' => $sourceIdentifier,
            'resultsPerPage'   => max(1, min(self::MAX_PER_PAGE, $perPage)),
            'startIndex'       => max(0, $startIndex),
        ];

        if ($from !== null && $to !== null) {
            $query[$dateField . 'StartDate'] = self::stamp($from);
            $query[$dateField . 'EndDate']   = self::stamp($to);
        }

        return $this->get($query);
    }

    /**
     * @param string $cveId The id.
     *
     * @return array|null  The `vulnerabilities[0]` entry, or null for no such record.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function byId(string $cveId): ?array
    {
        $response = $this->get(['cveId' => strtoupper(trim($cveId))], true);

        if ($response === null) {
            return null;
        }

        $first = $response['vulnerabilities'][0] ?? null;

        return \is_array($first) ? $first : null;
    }

    /**
     * @param array $query        Query parameters.
     * @param bool  $nullOnAbsent Whether a bare 404 (no `message` header) means "no record"
     *                            and returns null rather than throwing.
     *
     * @return array|null
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function get(array $query, bool $nullOnAbsent = false): ?array
    {
        $this->throttle();

        $headers = ['Accept' => 'application/json', 'User-Agent' => 'Joomla-VEL/2.0 (+https://extensions.joomla.org/vulnerable-extensions)'];

        if ($this->apiKey !== '') {
            $headers['apiKey'] = $this->apiKey;
        }

        $url = self::BASE . '?' . http_build_query($query);

        $start = microtime(true);

        try {
            $response = $this->http->get($url, $headers, $this->timeout);
        } catch (\Throwable $e) {
            // The API key travels in a header, never in the URL, so the URL is safe to show.
            FeedTrace::record('GET', $url, 0, 0, $start, '', $e->getMessage());

            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_NVD_REQUEST_FAILED', $e->getMessage()), 0, $e);
        }

        $status  = $response->getStatusCode();
        $body    = (string) $response->getBody();

        FeedTrace::record('GET', $url, $status, \strlen($body), $start);
        $message = trim($response->getHeaderLine('message'));

        if ($status === 403 || $status === 429) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_NVD_RATE_LIMIT', $status));
        }

        if ($status === 404) {
            // The header is the error body. Its absence on an id lookup is simply "no record".
            if ($message !== '') {
                throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_NVD_REFUSED', $message));
            }

            if ($nullOnAbsent) {
                return null;
            }

            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_NVD_404_NO_MESSAGE', $url));
        }

        if ($status >= 400) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_NVD_STATUS', $status, $message));
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_NVD_INVALID_JSON'));
        }

        return $decoded;
    }

    /**
     * @param DateTimeImmutable $date A moment.
     *
     * @return string  `YYYY-MM-DDTHH:MM:SS.000Z`.
     *
     * @since 4.0.0
     */
    public static function stamp(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.000\Z');
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    private function throttle(): void
    {
        $elapsed = microtime(true) - $this->lastRequest;

        if ($this->lastRequest > 0 && $elapsed < $this->pause) {
            usleep((int) (($this->pause - $elapsed) * 1000000));
        }

        $this->lastRequest = microtime(true);
    }
}
