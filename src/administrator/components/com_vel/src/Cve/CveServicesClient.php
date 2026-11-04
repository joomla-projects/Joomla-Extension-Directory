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
use Joomla\Http\HttpFactory;
use Joomla\Http\Http;
use RuntimeException;

/**
 * The CVE Program's CVE Services API (VEL2-PRD §6.5, Appendix D).
 *
 * Endpoints, from the CVE Services documentation as known at the time of writing and to be
 * confirmed in research task R-02:
 *
 *   GET  /api/cve/{id}                  public; the record
 *   GET  /api/cve-id?state=…&cve_id_year=…   the organisation's ids
 *   POST /api/cve-id?amount=1&cve_year=…&short_name=…   reserve
 *   POST /api/cve/{id}/cna              publish the CNA container
 *   PUT  /api/cve/{id}/cna              update it
 *   GET  /api/cve?assignerShortName=…&time_modified.gt=…   list (authenticated)
 *
 * Authentication is three headers: CVE-API-ORG, CVE-API-USER, CVE-API-KEY. Test and production
 * are separate hosts with separate credentials; the option decides which.
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
            (new HttpFactory())->getHttp(['timeout' => 30]),
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
     * Reserve one id for the current year.
     *
     * @param int|null $year The CVE year, or null for the current one.
     *
     * @return string  The id.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function reserve(?int $year = null): string
    {
        $this->requireCredentials();

        $year ??= (int) gmdate('Y');

        $response = $this->request('POST', '/cve-id?' . http_build_query([
            'amount'     => 1,
            'cve_year'   => $year,
            'short_name' => $this->org,
            'batch_type' => 'sequential',
        ]), null, true);

        $id = (string) ($response['cve_ids'][0]['cve_id'] ?? '');

        if (!preg_match('/^CVE-\d{4}-\d{4,}$/', $id)) {
            throw new RuntimeException('CVE Services returned no id: ' . json_encode($response));
        }

        return $id;
    }

    /**
     * Publish or update the CNA container.
     *
     * @param string $cveId     The id.
     * @param array  $container The CNA container.
     * @param bool   $update    PUT (update) rather than POST (first publication).
     *
     * @return array  The response.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function submitCna(string $cveId, array $container, bool $update): array
    {
        $this->requireCredentials();

        return $this->request($update ? 'PUT' : 'POST', '/cve/' . rawurlencode(strtoupper($cveId)) . '/cna', ['cnaContainer' => $container], true);
    }

    /**
     * The organisation's records modified since a time.
     *
     * @param string $sinceIso ISO 8601.
     * @param int    $page     Page number.
     *
     * @return array
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function listModified(string $sinceIso, int $page = 1): array
    {
        $this->requireCredentials();

        return $this->request('GET', '/cve?' . http_build_query([
            'time_modified.gt' => $sinceIso,
            'page'             => $page,
        ]), null, true);
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
            throw new RuntimeException('CVE Services credentials are not configured in the VEL options.');
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
            throw new RuntimeException('CVE Services request failed: ' . $e->getMessage(), 0, $e);
        }

        $decoded = json_decode((string) $response->body, true);

        if ($response->code >= 400) {
            $message = \is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error'] ?? json_encode($decoded)) : (string) $response->body;

            throw new RuntimeException('CVE Services answered ' . $response->code . ': ' . mb_substr($message, 0, 500));
        }

        return \is_array($decoded) ? $decoded : [];
    }
}
