<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Cve;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\Http\Http;
use RuntimeException;

/**
 * The CVE Program's CVE Services API, verified against the official OpenAPI specification
 * (version 2.9.2) and the live service on 2026-11-05 (R-02.4):
 *
 *   GET  /api/cve/{id}                      public, no auth
 *   GET  /api/cve-id?state=…&cve_id_year=…  the organisation's own ids ("registered users"),
 *                                           500 per page; pair with GET /cve/{id} for records
 *   POST /api/cve-id?amount=&cve_year=&short_name=[&batch_type=]   reserve; 206 = partial
 *   POST /api/cve/{id}/cna?erlcheck=        publish the CNA container
 *   PUT  /api/cve/{id}/cna?erlcheck=        update it
 *   GET  /api/org/{shortname}/id_quota      remaining quota
 *
 * What is **not** available to a CNA: `GET /cve` and `GET /cve_cursor` need the Secretariat or
 * Bulk Download role. There is no "list our records" call; `GET /cve-id` is the path.
 *
 * Authentication is three headers on every authenticated operation: CVE-API-ORG, CVE-API-USER,
 * CVE-API-KEY. Test and production are separate hosts with separate data and credentials.
 *
 * A submission body is `{"cnaContainer": …}` with no `dataVersion`; `providerMetadata`,
 * `datePublished` and `assignerShortName` are set by the server and overwritten if supplied.
 * The 200 body is `{valid, warnings[], message, created}` - the warnings are worth showing.
 *
 * @since 4.0.0
 */
final class CveServicesClient
{
    public const PROD = 'https://cveawg.mitre.org/api';
    public const TEST = 'https://cveawg-test.mitre.org/api';

    /**
     * @param Http   $http    The HTTP client.
     * @param string $baseUrl The API base.
     * @param string $org     CVE-API-ORG.
     * @param string $user    CVE-API-USER.
     * @param string $key     CVE-API-KEY.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly Http $http,
        private readonly string $baseUrl,
        private readonly string $org,
        private readonly string $user,
        private readonly string $key
    ) {
    }

    /**
     * @return self  Configured from the component options.
     *
     * @since 4.0.0
     */
    public static function fromOptions(): self
    {
        $params = ComponentHelper::getParams('com_vel');

        return new self(
            HttpFactory::getHttp(['timeout' => 30]),
            (string) $params->get('cve_services_env', 'test') === 'prod' ? self::PROD : self::TEST,
            (string) $params->get('cve_api_org', ''),
            (string) $params->get('cve_api_user', ''),
            (string) $params->get('cve_api_key', '')
        );
    }

    /**
     * @return bool  Whether credentials are configured.
     *
     * @since 4.0.0
     */
    public function configured(): bool
    {
        return $this->org !== '' && $this->user !== '' && $this->key !== '';
    }

    /**
     * @return bool  Whether this client talks to production.
     *
     * @since 4.0.0
     */
    public function isProduction(): bool
    {
        return $this->baseUrl === self::PROD;
    }

    /**
     * Fetch a record. Public; no credentials needed.
     *
     * @param string $cveId The id.
     *
     * @return array  The record.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function getRecord(string $cveId): array
    {
        return $this->request('GET', '/cve/' . rawurlencode(strtoupper($cveId)), null, false);
    }

    /**
     * Reserve ids for a year. All parameters go in the query string; `batch_type` is required
     * only when more than one id is asked for.
     *
     * @param int      $amount How many.
     * @param int|null $year   The CVE year, or null for the current one.
     *
     * @return array{ids: string[], partial: bool}  `partial` is true on HTTP 206: the quota ran
     *                                              out and fewer ids than asked came back.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function reserve(int $amount = 1, ?int $year = null): array
    {
        $this->requireCredentials();

        $year  ??= (int) gmdate('Y');
        $amount  = max(1, $amount);
        $query   = ['amount' => $amount, 'cve_year' => $year, 'short_name' => $this->org];

        if ($amount > 1) {
            $query['batch_type'] = 'sequential';
        }

        [$status, $response] = $this->requestWithStatus('POST', '/cve-id?' . http_build_query($query), null, true, [200, 206]);

        $ids = [];

        foreach ((array) ($response['cve_ids'] ?? []) as $entry) {
            $id = strtoupper((string) ($entry['cve_id'] ?? ''));

            if (preg_match('/^CVE-\d{4}-\d{4,}$/', $id)) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVE_SERVICES_NO_ID', (string) json_encode($response)));
        }

        return ['ids' => $ids, 'partial' => $status === 206];
    }

    /**
     * The organisation's remaining id quota.
     *
     * @return array  The response (`id_quota`, `total_reserved`, `available`).
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function idQuota(): array
    {
        $this->requireCredentials();

        return $this->request('GET', '/org/' . rawurlencode($this->org) . '/id_quota', null, true);
    }

    /**
     * Publish or update the CNA container.
     *
     * @param string $cveId     The id.
     * @param array  $container The CNA container (server-set members are stripped here).
     * @param bool   $update    PUT (update) rather than POST (first publication).
     * @param bool   $erlcheck  Ask for the stricter enrichment-data validation.
     *
     * @return array{valid: bool, warnings: string[], message: string, created: array}
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function submitCna(string $cveId, array $container, bool $update, bool $erlcheck = false): array
    {
        $this->requireCredentials();

        unset($container['providerMetadata'], $container['datePublished'], $container['assignerShortName']);

        $path = '/cve/' . rawurlencode(strtoupper($cveId)) . '/cna' . ($erlcheck ? '?erlcheck=true' : '');

        $response = $this->request($update ? 'PUT' : 'POST', $path, ['cnaContainer' => $container], true);

        return [
            'valid'    => (bool) ($response['valid'] ?? true),
            'warnings' => array_values(array_map('strval', (array) ($response['warnings'] ?? []))),
            'message'  => (string) ($response['message'] ?? ''),
            'created'  => (array) ($response['created'] ?? $response['updated'] ?? []),
        ];
    }

    /**
     * One page of the organisation's own CVE ids. Not the records: pair with getRecord().
     *
     * @param string|null $state   RESERVED | PUBLISHED | REJECTED, or null for all.
     * @param int|null    $year    `cve_id_year`, or null for all.
     * @param int         $page    Page number (500 per page).
     * @param string|null $modifiedSince ISO 8601 for `time_modified.gt`, or null.
     *
     * @return array  `{totalCount, itemsPerPage, pageCount, currentPage, prevPage, nextPage, cve_ids[]}`.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function listOwnIds(?string $state = null, ?int $year = null, int $page = 1, ?string $modifiedSince = null): array
    {
        $this->requireCredentials();

        $query = ['page' => max(1, $page)];

        if ($state !== null) {
            $query['state'] = strtoupper($state);
        }

        if ($year !== null) {
            $query['cve_id_year'] = $year;
        }

        if ($modifiedSince !== null) {
            $query['time_modified.gt'] = $modifiedSince;
        }

        return $this->request('GET', '/cve-id?' . http_build_query($query), null, true);
    }

    /**
     * @return void
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function requireCredentials(): void
    {
        if (!$this->configured()) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_CVE_SERVICES_NOT_CONFIGURED'));
        }
    }

    /**
     * @param string     $method HTTP method.
     * @param string     $path   Path under the base.
     * @param array|null $body   JSON body.
     * @param bool       $auth   Send credentials.
     *
     * @return array  Decoded JSON.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function request(string $method, string $path, ?array $body, bool $auth): array
    {
        return $this->requestWithStatus($method, $path, $body, $auth, [200])[1];
    }

    /**
     * @param string     $method   HTTP method.
     * @param string     $path     Path under the base.
     * @param array|null $body     JSON body.
     * @param bool       $auth     Send credentials.
     * @param int[]      $accepted Status codes that are successes.
     *
     * @return array{0: int, 1: array}  [status, decoded JSON].
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function requestWithStatus(string $method, string $path, ?array $body, bool $auth, array $accepted): array
    {
        $headers = ['Accept' => 'application/json', 'User-Agent' => 'Joomla-VEL/2.0'];

        if ($auth) {
            $headers['CVE-API-ORG']  = $this->org;
            $headers['CVE-API-USER'] = $this->user;
            $headers['CVE-API-KEY']  = $this->key;
        }

        $url = $this->baseUrl . $path;

        try {
            if ($body !== null) {
                $headers['Content-Type'] = 'application/json';
                $data                    = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $response                = $method === 'PUT' ? $this->http->put($url, $data, $headers) : $this->http->post($url, $data, $headers);
            } else {
                $response = $method === 'POST' ? $this->http->post($url, '', $headers) : $this->http->get($url, $headers);
            }
        } catch (\Throwable $e) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVE_SERVICES_REQUEST_FAILED', $e->getMessage()), 0, $e);
        }

        $status  = $response->getStatusCode();
        $raw     = (string) $response->getBody();
        $decoded = json_decode($raw, true);

        if (!\in_array($status, $accepted, true)) {
            $message = \is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error'] ?? json_encode($decoded)) : $raw;

            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVE_SERVICES_STATUS', $status, mb_substr($message, 0, 500)));
        }

        return [$status, \is_array($decoded) ? $decoded : []];
    }
}
