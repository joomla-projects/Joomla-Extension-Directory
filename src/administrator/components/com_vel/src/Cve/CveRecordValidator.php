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

/**
 * Checks a generated record against the CVE Record Format 5.x rules that matter in practice.
 *
 * Two layers. The structural checks below are written from the schema and need nothing
 * installed; they catch every mistake the builder could plausibly make (a missing required
 * member, a bad id, a bad date, an unknown tag or credit type, an empty affected list). The
 * full JSON-Schema validation runs on top when research task R-02 has dropped the schema into
 * `administrator/components/com_vel/schema/cve-record.json` and `opis/json-schema` is in the
 * component's vendor folder - both optional, both detected at runtime.
 *
 * @since 4.0.0
 */
final class CveRecordValidator
{
    /**
     * @var string[]
     *
     * @since 4.0.0
     */
    private const TAGS = [
        'broken-link', 'customer-entitlement', 'exploit', 'government-resource', 'issue-tracking', 'mailing-list',
        'mitigation', 'not-applicable', 'patch', 'permissions-required', 'media-coverage', 'product', 'related',
        'release-notes', 'signature', 'technical-description', 'third-party-advisory', 'vendor-advisory',
    ];

    /**
     * @var string[]
     *
     * @since 4.0.0
     */
    private const CREDIT_TYPES = [
        'finder', 'reporter', 'analyst', 'coordinator', 'remediation developer', 'remediation reviewer',
        'remediation verifier', 'tool', 'sponsor', 'other',
    ];

    /**
     * @param string|null $schemaPath Where the full JSON schema lives, if anywhere.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly ?string $schemaPath = null)
    {
    }

    /**
     * @param array $record A full record (dataType CVE_RECORD).
     *
     * @return string[]  Problems; empty means valid.
     *
     * @since 4.0.0
     */
    public function validateRecord(array $record): array
    {
        $problems = [];

        if (($record['dataType'] ?? '') !== 'CVE_RECORD') {
            $problems[] = 'dataType must be CVE_RECORD.';
        }

        if (!preg_match('/^5\.\d+(\.\d+)?$/', (string) ($record['dataVersion'] ?? ''))) {
            $problems[] = 'dataVersion must be a 5.x version.';
        }

        $meta = $record['cveMetadata'] ?? null;

        if (!\is_array($meta)) {
            $problems[] = 'cveMetadata is required.';
        } else {
            if (!preg_match('/^CVE-\d{4}-\d{4,19}$/', (string) ($meta['cveId'] ?? ''))) {
                $problems[] = 'cveMetadata.cveId is not a CVE id (the advisory has no CVE id yet).';
            }

            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) ($meta['assignerOrgId'] ?? ''))) {
                $problems[] = 'cveMetadata.assignerOrgId is not a UUID.';
            }

            if (!\in_array((string) ($meta['state'] ?? ''), ['PUBLISHED', 'RESERVED', 'REJECTED'], true)) {
                $problems[] = 'cveMetadata.state must be PUBLISHED, RESERVED or REJECTED.';
            }

            foreach (['dateReserved', 'datePublished', 'dateUpdated'] as $date) {
                if (isset($meta[$date]) && !self::isTimestamp((string) $meta[$date])) {
                    $problems[] = 'cveMetadata.' . $date . ' is not an ISO 8601 timestamp.';
                }
            }

            if (($meta['state'] ?? '') === 'PUBLISHED' && empty($meta['datePublished'])) {
                $problems[] = 'A PUBLISHED record needs cveMetadata.datePublished.';
            }
        }

        $cna = $record['containers']['cna'] ?? null;

        if (!\is_array($cna)) {
            $problems[] = 'containers.cna is required.';
        } else {
            $problems = array_merge($problems, $this->validateCna($cna));
        }

        return array_merge($problems, $this->schemaProblems($record));
    }

    /**
     * @param array $cna A CNA container.
     *
     * @return string[]
     *
     * @since 4.0.0
     */
    public function validateCna(array $cna): array
    {
        $problems = [];

        $provider = $cna['providerMetadata'] ?? null;

        if (!\is_array($provider) || empty($provider['orgId'])) {
            $problems[] = 'providerMetadata.orgId is required.';
        }

        if (isset($cna['title']) && mb_strlen((string) $cna['title']) > 256) {
            $problems[] = 'title is longer than 256 characters.';
        }

        $descriptions = $cna['descriptions'] ?? [];

        if (!\is_array($descriptions) || $descriptions === []) {
            $problems[] = 'At least one description is required.';
        } else {
            foreach ($descriptions as $i => $description) {
                if (empty($description['lang']) || trim((string) ($description['value'] ?? '')) === '') {
                    $problems[] = 'descriptions[' . $i . '] needs lang and value.';
                } elseif (mb_strlen((string) $description['value']) > 4096) {
                    $problems[] = 'descriptions[' . $i . '].value is longer than 4096 characters.';
                }
            }
        }

        $affected = $cna['affected'] ?? [];

        if (!\is_array($affected) || $affected === []) {
            $problems[] = 'At least one affected product is required.';
        } else {
            foreach ($affected as $i => $product) {
                if (trim((string) ($product['vendor'] ?? '')) === '' || trim((string) ($product['product'] ?? '')) === '') {
                    $problems[] = 'affected[' . $i . '] needs vendor and product.';
                }

                if (isset($product['defaultStatus']) && !\in_array($product['defaultStatus'], ['affected', 'unaffected', 'unknown'], true)) {
                    $problems[] = 'affected[' . $i . '].defaultStatus is invalid.';
                }

                foreach ((array) ($product['versions'] ?? []) as $j => $version) {
                    if (trim((string) ($version['version'] ?? '')) === '') {
                        $problems[] = 'affected[' . $i . '].versions[' . $j . '].version is required.';
                    }

                    if (!\in_array((string) ($version['status'] ?? ''), ['affected', 'unaffected', 'unknown'], true)) {
                        $problems[] = 'affected[' . $i . '].versions[' . $j . '].status is invalid.';
                    }

                    if (isset($version['lessThan'], $version['lessThanOrEqual'])) {
                        $problems[] = 'affected[' . $i . '].versions[' . $j . '] carries both lessThan and lessThanOrEqual.';
                    }

                    if ((isset($version['lessThan']) || isset($version['lessThanOrEqual'])) && empty($version['versionType'])) {
                        $problems[] = 'affected[' . $i . '].versions[' . $j . '] needs versionType with a range.';
                    }
                }

                if (empty($product['versions']) && ($product['defaultStatus'] ?? '') !== 'affected') {
                    $problems[] = 'affected[' . $i . '] has no versions and is not affected by default - nothing is marked affected.';
                }
            }
        }

        $problemTypes = $cna['problemTypes'] ?? [];

        if (!\is_array($problemTypes) || $problemTypes === []) {
            $problems[] = 'At least one problemType is required.';
        } else {
            foreach ($problemTypes as $i => $type) {
                foreach ((array) ($type['descriptions'] ?? []) as $j => $description) {
                    if (empty($description['lang']) || trim((string) ($description['description'] ?? '')) === '') {
                        $problems[] = 'problemTypes[' . $i . '].descriptions[' . $j . '] needs lang and description.';
                    }

                    if (isset($description['cweId']) && !preg_match('/^CWE-\d{1,5}$/', (string) $description['cweId'])) {
                        $problems[] = 'problemTypes[' . $i . '].descriptions[' . $j . '].cweId is not a CWE id.';
                    }
                }
            }
        }

        $references = $cna['references'] ?? [];

        if (!\is_array($references) || $references === []) {
            $problems[] = 'At least one reference is required.';
        } else {
            foreach ($references as $i => $reference) {
                if (!preg_match('~^https?://~i', (string) ($reference['url'] ?? ''))) {
                    $problems[] = 'references[' . $i . '].url is not a URL.';
                }

                foreach ((array) ($reference['tags'] ?? []) as $tag) {
                    if (!\in_array($tag, self::TAGS, true) && !str_starts_with((string) $tag, 'x_')) {
                        $problems[] = 'references[' . $i . '] carries the unknown tag "' . $tag . '".';
                    }
                }
            }
        }

        foreach ((array) ($cna['credits'] ?? []) as $i => $credit) {
            if (trim((string) ($credit['value'] ?? '')) === '') {
                $problems[] = 'credits[' . $i . '].value is required.';
            }

            if (isset($credit['type']) && !\in_array($credit['type'], self::CREDIT_TYPES, true)) {
                $problems[] = 'credits[' . $i . '].type "' . $credit['type'] . '" is not a credit type.';
            }
        }

        foreach ((array) ($cna['metrics'] ?? []) as $i => $metric) {
            $cvss = $metric['cvssV3_1'] ?? $metric['cvssV4_0'] ?? $metric['cvssV3_0'] ?? null;

            if (!\is_array($cvss)) {
                $problems[] = 'metrics[' . $i . '] carries no CVSS object.';

                continue;
            }

            if (!isset($cvss['baseScore']) || (float) $cvss['baseScore'] < 0 || (float) $cvss['baseScore'] > 10) {
                $problems[] = 'metrics[' . $i . '].baseScore is out of range.';
            }

            if (!\in_array((string) ($cvss['baseSeverity'] ?? ''), ['NONE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
                $problems[] = 'metrics[' . $i . '].baseSeverity is invalid.';
            }

            if (!preg_match('/^CVSS:(3\.[01]|4\.0)\//', (string) ($cvss['vectorString'] ?? ''))) {
                $problems[] = 'metrics[' . $i . '].vectorString is not a CVSS vector.';
            }
        }

        if (isset($cna['source']['discovery']) && !\in_array($cna['source']['discovery'], ['UNKNOWN', 'INTERNAL', 'EXTERNAL', 'USER'], true)) {
            $problems[] = 'source.discovery is invalid.';
        }

        foreach ((array) ($cna['timeline'] ?? []) as $i => $entry) {
            if (!self::isTimestamp((string) ($entry['time'] ?? ''))) {
                $problems[] = 'timeline[' . $i . '].time is not a timestamp.';
            }
        }

        return $problems;
    }

    /**
     * Full JSON-Schema validation when the schema and a validator library are present.
     *
     * @param array $record The record.
     *
     * @return string[]
     *
     * @since 4.0.0
     */
    private function schemaProblems(array $record): array
    {
        if ($this->schemaPath === null || !is_file($this->schemaPath) || !class_exists('\\Opis\\JsonSchema\\Validator')) {
            return [];
        }

        try {
            $validator = new \Opis\JsonSchema\Validator();
            $schema    = json_decode((string) file_get_contents($this->schemaPath));
            $result    = $validator->validate(json_decode((string) json_encode($record)), $schema);

            if ($result->isValid()) {
                return [];
            }

            $error = $result->error();

            return ['Schema: ' . ($error ? $error->message() . ' at ' . implode('/', $error->data()->fullPath()) : 'invalid')];
        } catch (\Throwable $e) {
            return ['Schema validation could not run: ' . $e->getMessage()];
        }
    }

    /**
     * @param string $value A candidate.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private static function isTimestamp(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+\-]\d{2}:\d{2})?$/', $value);
    }
}
