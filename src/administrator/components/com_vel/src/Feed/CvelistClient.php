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

use Joomla\Http\Http;
use RuntimeException;

/**
 * The public `CVEProject/cvelistV5` repository on GitHub (VEL2-PRD §6.6.1, Appendix D).
 *
 *   cves/deltaLog.json                          the recent hourly deltas (new / updated ids)
 *   cves/<year>/<NNNxxx>/CVE-<year>-<NNNNN>.json   one record
 *
 * No credentials; a token only raises the rate limit. The exact delta retention and URL layout
 * are confirmed in research task R-02 - both are constants here for that reason.
 *
 * @since 4.0.0
 */
final class CvelistClient
{
    public const RAW_BASE = 'https://raw.githubusercontent.com/CVEProject/cvelistV5/main/';

    /**
     * @param Http   $http  HTTP client.
     * @param string $token Optional GitHub token.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly Http $http, private readonly string $token = '')
    {
    }

    /**
     * The delta log: entries with `fetchTime`, `new[]`, `updated[]` (each `cveId`, `dateUpdated`).
     *
     * @return array
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function deltaLog(): array
    {
        $decoded = $this->getJson(self::RAW_BASE . 'cves/deltaLog.json');

        return \is_array($decoded) ? $decoded : [];
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
        $cveId = strtoupper(trim($cveId));

        if (!preg_match('/^CVE-(\d{4})-(\d{4,})$/', $cveId, $m)) {
            return null;
        }

        $year   = $m[1];
        $number = (int) $m[2];
        $bucket = (int) floor($number / 1000) . 'xxx';

        try {
            $decoded = $this->getJson(self::RAW_BASE . 'cves/' . $year . '/' . $bucket . '/' . $cveId . '.json');
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), ' 404')) {
                return null;
            }

            throw $e;
        }

        return \is_array($decoded) ? $decoded : null;
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

        if ($this->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        try {
            $response = $this->http->get($url, $headers, 30);
        } catch (\Throwable $e) {
            throw new RuntimeException('cvelistV5 request failed: ' . $e->getMessage(), 0, $e);
        }

        if ($response->code >= 400) {
            throw new RuntimeException('cvelistV5 answered ' . $response->code . ' for ' . $url);
        }

        $decoded = json_decode((string) $response->body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('cvelistV5 returned invalid JSON for ' . $url);
        }

        return $decoded;
    }
}
