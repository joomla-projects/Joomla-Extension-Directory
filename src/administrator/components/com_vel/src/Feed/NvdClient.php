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
use Joomla\Http\Http;
use RuntimeException;

/**
 * NVD API 2.0 (VEL2-PRD §6.6.2, Appendix D).
 *
 * Rate limits: 5 requests per rolling 30 seconds without a key, 50 with one. This client sleeps
 * between requests regardless, because a 403 from the NVD costs more time than the sleep. Date
 * windows may not exceed 120 days; the runner splits longer ones.
 *
 * @since 4.0.0
 */
final class NvdClient
{
    public const BASE = 'https://services.nvd.nist.gov/rest/json/cves/2.0';

    /**
     * Seconds between requests: 6 without a key (5 per 30 s), 1 with one.
     *
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
     * @param Http   $http   HTTP client.
     * @param string $apiKey Optional API key.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly Http $http, private readonly string $apiKey = '')
    {
        $this->pause = $apiKey !== '' ? 1.0 : 6.5;
    }

    /**
     * One page of CVEs modified in a window that mention a keyword.
     *
     * @param string            $keyword    `keywordSearch`.
     * @param DateTimeImmutable $from       Window start.
     * @param DateTimeImmutable $to         Window end (at most 120 days after start).
     * @param int               $startIndex Paging offset.
     * @param int               $perPage    Page size (max 2000).
     *
     * @return array  The decoded response (`totalResults`, `vulnerabilities[]`).
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function search(string $keyword, DateTimeImmutable $from, DateTimeImmutable $to, int $startIndex = 0, int $perPage = 500): array
    {
        return $this->get([
            'keywordSearch'    => $keyword,
            'lastModStartDate' => $from->format('Y-m-d\TH:i:s.000'),
            'lastModEndDate'   => $to->format('Y-m-d\TH:i:s.000'),
            'resultsPerPage'   => max(1, min(2000, $perPage)),
            'startIndex'       => max(0, $startIndex),
        ]);
    }

    /**
     * @param string $cveId The id.
     *
     * @return array|null  The `vulnerabilities[0]` entry, or null.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function byId(string $cveId): ?array
    {
        $response = $this->get(['cveId' => strtoupper(trim($cveId))]);
        $first    = $response['vulnerabilities'][0] ?? null;

        return \is_array($first) ? $first : null;
    }

    /**
     * @param array $query Query parameters.
     *
     * @return array
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function get(array $query): array
    {
        $this->throttle();

        $headers = ['Accept' => 'application/json', 'User-Agent' => 'Joomla-VEL/2.0 (+https://extensions.joomla.org/vulnerable-extensions)'];

        if ($this->apiKey !== '') {
            $headers['apiKey'] = $this->apiKey;
        }

        $url = self::BASE . '?' . http_build_query($query);

        try {
            $response = $this->http->get($url, $headers, 60);
        } catch (\Throwable $e) {
            throw new RuntimeException('NVD request failed: ' . $e->getMessage(), 0, $e);
        }

        if ($response->code === 403 || $response->code === 429) {
            throw new RuntimeException('NVD rate limit hit (' . $response->code . '); try again later or add an API key.');
        }

        if ($response->code >= 400) {
            throw new RuntimeException('NVD answered ' . $response->code . ': ' . mb_substr((string) ($response->headers['message'][0] ?? $response->body), 0, 200));
        }

        $decoded = json_decode((string) $response->body, true);

        if (!\is_array($decoded)) {
            throw new RuntimeException('NVD returned invalid JSON.');
        }

        return $decoded;
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
