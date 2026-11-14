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
 * full JSON-Schema validation runs on top when the schema (`CVE_Record_Format.json` from the
 * `v5.2.0` tag of CVEProject/cve-schema: draft-07, CC0-1.0, one self-contained file) is at
 * `administrator/components/com_vel/schema/cve-record.json` and `opis/json-schema` (the
 * draft-07 validator; `justinrainbow` is draft-04 and will not do) is in the component's vendor
 * folder - both optional, both detected at runtime. The CNA container validates against
 * `#/definitions/cnaPublishedContainer` of the same file.
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
     * @return array<int, array<int, string>>  Problems as `[language key, ...arguments]`, ready for
     *                                       `Text::sprintf(...$problem)`; empty means valid.
     *
     * @since 4.0.0
     */
    public function validateRecord(array $record): array
    {
        $problems = [];

        if (($record['dataType'] ?? '') !== 'CVE_RECORD') {
            $problems[] = ['COM_VEL_CVE_PROBLEM_DATATYPE'];
        }

        // The schema's own pattern: 5.2 and 5.2.0 both validate; untouched records are still 5.1.
        if (!preg_match('/^5\.(0|[1-9][0-9]*)(\.(0|[1-9][0-9]*))?$/', (string) ($record['dataVersion'] ?? ''))) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_DATAVERSION'];
        }

        $meta = $record['cveMetadata'] ?? null;

        if (!\is_array($meta)) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_REQUIRED', 'cveMetadata'];
        } else {
            if (!preg_match('/^CVE-\d{4}-\d{4,19}$/', (string) ($meta['cveId'] ?? ''))) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_NO_CVE_ID'];
            }

            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) ($meta['assignerOrgId'] ?? ''))) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_NOT_UUID', 'cveMetadata.assignerOrgId'];
            }

            if (!\in_array((string) ($meta['state'] ?? ''), ['PUBLISHED', 'RESERVED', 'REJECTED'], true)) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_STATE'];
            }

            foreach (['dateReserved', 'datePublished', 'dateUpdated'] as $date) {
                if (isset($meta[$date]) && !self::isTimestamp((string) $meta[$date])) {
                    $problems[] = ['COM_VEL_CVE_PROBLEM_NOT_TIMESTAMP', 'cveMetadata.' . $date];
                }
            }

            if (($meta['state'] ?? '') === 'PUBLISHED' && empty($meta['datePublished'])) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_DATE_PUBLISHED'];
            }
        }

        $cna = $record['containers']['cna'] ?? null;

        if (!\is_array($cna)) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_REQUIRED', 'containers.cna'];
        } else {
            $problems = array_merge($problems, $this->validateCna($cna));
        }

        return array_merge($problems, $this->schemaProblems($record, null));
    }

    /**
     * @param array $cna A CNA container.
     *
     * @return array<int, array<int, string>>  As validateRecord().
     *
     * @since 4.0.0
     */
    public function validateCna(array $cna): array
    {
        $problems = [];

        $provider = $cna['providerMetadata'] ?? null;

        if (!\is_array($provider) || empty($provider['orgId'])) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_REQUIRED', 'providerMetadata.orgId'];
        }

        if (isset($cna['title']) && mb_strlen((string) $cna['title']) > 256) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_TOO_LONG', 'title', '256'];
        }

        $descriptions = $cna['descriptions'] ?? [];

        if (!\is_array($descriptions) || $descriptions === []) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_AT_LEAST_ONE', 'descriptions'];
        } else {
            foreach ($descriptions as $i => $description) {
                if (empty($description['lang']) || trim((string) ($description['value'] ?? '')) === '') {
                    $problems[] = ['COM_VEL_CVE_PROBLEM_NEEDS', 'descriptions[' . $i . ']', 'lang, value'];
                } elseif (mb_strlen((string) $description['value']) > 4096) {
                    $problems[] = ['COM_VEL_CVE_PROBLEM_TOO_LONG', 'descriptions[' . $i . '].value', '4096'];
                }
            }
        }

        $affected = $cna['affected'] ?? [];

        if (!\is_array($affected) || $affected === []) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_AT_LEAST_ONE', 'affected'];
        } else {
            foreach ($affected as $i => $product) {
                if (trim((string) ($product['vendor'] ?? '')) === '' || trim((string) ($product['product'] ?? '')) === '') {
                    $problems[] = ['COM_VEL_CVE_PROBLEM_NEEDS', 'affected[' . $i . ']', 'vendor, product'];
                }

                if (isset($product['defaultStatus']) && !\in_array($product['defaultStatus'], ['affected', 'unaffected', 'unknown'], true)) {
                    $problems[] = ['COM_VEL_CVE_PROBLEM_INVALID', 'affected[' . $i . '].defaultStatus'];
                }

                foreach ((array) ($product['versions'] ?? []) as $j => $version) {
                    if (trim((string) ($version['version'] ?? '')) === '') {
                        $problems[] = ['COM_VEL_CVE_PROBLEM_REQUIRED', 'affected[' . $i . '].versions[' . $j . '].version'];
                    }

                    if (!\in_array((string) ($version['status'] ?? ''), ['affected', 'unaffected', 'unknown'], true)) {
                        $problems[] = ['COM_VEL_CVE_PROBLEM_INVALID', 'affected[' . $i . '].versions[' . $j . '].status'];
                    }

                    if (isset($version['lessThan'], $version['lessThanOrEqual'])) {
                        $problems[] = ['COM_VEL_CVE_PROBLEM_BOTH_BOUNDS', 'affected[' . $i . '].versions[' . $j . ']'];
                    }

                    if ((isset($version['lessThan']) || isset($version['lessThanOrEqual'])) && empty($version['versionType'])) {
                        $problems[] = ['COM_VEL_CVE_PROBLEM_RANGE_NEEDS_TYPE', 'affected[' . $i . '].versions[' . $j . ']'];
                    }
                }

                if (empty($product['versions']) && ($product['defaultStatus'] ?? '') !== 'affected') {
                    $problems[] = ['COM_VEL_CVE_PROBLEM_NOTHING_AFFECTED', 'affected[' . $i . ']'];
                }
            }
        }

        $problemTypes = $cna['problemTypes'] ?? [];

        if (!\is_array($problemTypes) || $problemTypes === []) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_AT_LEAST_ONE', 'problemTypes'];
        } else {
            foreach ($problemTypes as $i => $type) {
                foreach ((array) ($type['descriptions'] ?? []) as $j => $description) {
                    if (empty($description['lang']) || trim((string) ($description['description'] ?? '')) === '') {
                        $problems[] = ['COM_VEL_CVE_PROBLEM_NEEDS', 'problemTypes[' . $i . '].descriptions[' . $j . ']', 'lang, description'];
                    }

                    if (isset($description['cweId']) && !preg_match('/^CWE-\d{1,5}$/', (string) $description['cweId'])) {
                        $problems[] = ['COM_VEL_CVE_PROBLEM_NOT_CWE', 'problemTypes[' . $i . '].descriptions[' . $j . '].cweId'];
                    }
                }
            }
        }

        $references = $cna['references'] ?? [];

        if (!\is_array($references) || $references === []) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_AT_LEAST_ONE', 'references'];
        } else {
            foreach ($references as $i => $reference) {
                if (!preg_match('~^https?://~i', (string) ($reference['url'] ?? ''))) {
                    $problems[] = ['COM_VEL_CVE_PROBLEM_NOT_URL', 'references[' . $i . '].url'];
                }

                foreach ((array) ($reference['tags'] ?? []) as $tag) {
                    if (!\in_array($tag, self::TAGS, true) && !str_starts_with((string) $tag, 'x_')) {
                        $problems[] = ['COM_VEL_CVE_PROBLEM_UNKNOWN_TAG', 'references[' . $i . ']', (string) $tag];
                    }
                }
            }
        }

        foreach ((array) ($cna['credits'] ?? []) as $i => $credit) {
            if (trim((string) ($credit['value'] ?? '')) === '') {
                $problems[] = ['COM_VEL_CVE_PROBLEM_REQUIRED', 'credits[' . $i . '].value'];
            }

            if (isset($credit['type']) && !\in_array($credit['type'], self::CREDIT_TYPES, true)) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_NOT_CREDIT_TYPE', 'credits[' . $i . '].type', (string) $credit['type']];
            }
        }

        foreach ((array) ($cna['metrics'] ?? []) as $i => $metric) {
            $cvss = $metric['cvssV3_1'] ?? $metric['cvssV4_0'] ?? $metric['cvssV3_0'] ?? null;

            if (!\is_array($cvss)) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_NO_CVSS', 'metrics[' . $i . ']'];

                continue;
            }

            if (!isset($cvss['baseScore']) || (float) $cvss['baseScore'] < 0 || (float) $cvss['baseScore'] > 10) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_SCORE_RANGE', 'metrics[' . $i . '].baseScore'];
            }

            if (!\in_array((string) ($cvss['baseSeverity'] ?? ''), ['NONE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_INVALID', 'metrics[' . $i . '].baseSeverity'];
            }

            if (!preg_match('/^CVSS:(3\.[01]|4\.0)\//', (string) ($cvss['vectorString'] ?? ''))) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_NOT_VECTOR', 'metrics[' . $i . '].vectorString'];
            }
        }

        if (isset($cna['source']['discovery']) && !\in_array($cna['source']['discovery'], ['UNKNOWN', 'INTERNAL', 'EXTERNAL', 'USER'], true)) {
            $problems[] = ['COM_VEL_CVE_PROBLEM_INVALID', 'source.discovery'];
        }

        foreach ((array) ($cna['timeline'] ?? []) as $i => $entry) {
            if (!self::isTimestamp((string) ($entry['time'] ?? ''))) {
                $problems[] = ['COM_VEL_CVE_PROBLEM_NOT_TIMESTAMP', 'timeline[' . $i . '].time'];
            }
        }

        return array_merge($problems, $this->schemaProblems($cna, 'cnaPublishedContainer'));
    }

    /**
     * Full JSON-Schema validation when the schema and a validator library are present.
     *
     * @param array       $record     The record.
     * @param string|null $definition A named definition of the schema to validate against.
     *
     * @return array<int, array<int, string>>  As validateRecord().
     *
     * @since 4.0.0
     */
    private function schemaProblems(array $record, ?string $definition): array
    {
        if ($this->schemaPath === null || !is_file($this->schemaPath) || !class_exists('\\Opis\\JsonSchema\\Validator')) {
            return [];
        }

        try {
            $validator = new \Opis\JsonSchema\Validator();
            $schema    = json_decode((string) file_get_contents($this->schemaPath));

            if ($definition !== null) {
                // Validate against one named definition of the same file.
                $schema = (object) ['$ref' => (string) ($schema->{'$id'} ?? 'cve-record.json') . '#/definitions/' . $definition];
                $validator->resolver()->registerFile((string) ($schema->{'$ref'} ? explode('#', $schema->{'$ref'})[0] : ''), $this->schemaPath);
            }

            $result    = $validator->validate(json_decode((string) json_encode($record)), $schema);

            if ($result->isValid()) {
                return [];
            }

            $error = $result->error();

            return [$error ? ['COM_VEL_CVE_PROBLEM_SCHEMA', $error->message(), implode('/', $error->data()->fullPath())] : ['COM_VEL_CVE_PROBLEM_SCHEMA_INVALID']];
        } catch (\Throwable $e) {
            return [['COM_VEL_CVE_PROBLEM_SCHEMA_UNAVAILABLE', $e->getMessage()]];
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
