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

use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Jed\Component\Vel\Administrator\Enum\Severity;

/**
 * NVD API 2.0 item -> {@see FeedRecord}, written against a census of the 1,633-record
 * `keywordSearch=Joomla` corpus (R-01.8, 2026-11-05):
 *
 *  - CVSS is chosen **version first** (4.0, then 3.1, then 3.0; v2 ignored), then Primary over
 *    Secondary within the version. Every v4.0 score in the corpus is Secondary - NVD produces
 *    none of its own - so "prefer Primary" would have discarded all 375 of them.
 *  - `ssvcV203` sits under `metrics` and is not CVSS; it is skipped.
 *  - `NVD-CWE-Other` and `NVD-CWE-noinfo` are placeholders, not ids (141 occurrences), and are
 *    dropped.
 *  - `references[].tags` use NVD's Title Case vocabulary and are mapped to the CVE 5.x list;
 *    `VDB Entry` and `URL Repurposed` have no equivalent and are dropped (the reference stays).
 *  - `configurations` is absent on 287 of 1,633 records and on most of ours (`Deferred`); the
 *    CPE list is carried as corroboration for the classifier, never as the lead.
 *
 * @since 4.0.0
 */
final class NvdRecordMapper
{
    /**
     * NVD's reference tags onto CVE 5.x tags. Complete for the corpus; absent = drop the tag.
     *
     * @var array<string, string>
     *
     * @since 4.0.0
     */
    public const TAG_MAP = [
        'exploit'                => 'exploit',
        'vendor advisory'        => 'vendor-advisory',
        'third party advisory'   => 'third-party-advisory',
        'patch'                  => 'patch',
        'product'                => 'product',
        'mailing list'           => 'mailing-list',
        'broken link'            => 'broken-link',
        'release notes'          => 'release-notes',
        'issue tracking'         => 'issue-tracking',
        'us government resource' => 'government-resource',
        'not applicable'         => 'not-applicable',
        'technical description'  => 'technical-description',
        'permissions required'   => 'permissions-required',
        'mitigation'             => 'mitigation',
    ];

    /**
     * @param array $item One `vulnerabilities[]` entry.
     *
     * @return FeedRecord|null
     *
     * @since 4.0.0
     */
    public static function map(array $item): ?FeedRecord
    {
        $cve   = $item['cve'] ?? null;
        $cveId = strtoupper((string) ($cve['id'] ?? ''));

        if (!\is_array($cve) || !preg_match('/^CVE-\d{4}-\d{4,}$/', $cveId)) {
            return null;
        }

        $description = '';

        foreach ((array) ($cve['descriptions'] ?? []) as $entry) {
            if (($entry['lang'] ?? '') === 'en' || $description === '') {
                $description = (string) ($entry['value'] ?? '');
            }
        }

        $status   = (string) ($cve['vulnStatus'] ?? '');
        $rejected = strtolower($status) === 'rejected';

        $affected = [];
        $cpes     = [];
        $mentions = stripos($description, 'joomla') !== false;
        $isCore   = false;
        $seen     = [];

        foreach ((array) ($cve['configurations'] ?? []) as $configuration) {
            foreach ((array) ($configuration['nodes'] ?? []) as $node) {
                foreach ((array) ($node['cpeMatch'] ?? []) as $match) {
                    $parts = VersionRangeMapper::cpeParts((string) ($match['criteria'] ?? ''));

                    if ($parts === null || $parts['part'] !== 'a') {
                        continue;
                    }

                    $cpes[] = ['vendor' => $parts['vendor'], 'product' => $parts['product'], 'target_sw' => $parts['target_sw']];

                    $ranges = VersionRangeMapper::fromCpeMatch($match);

                    if ($ranges === null) {
                        continue;
                    }

                    $vendor  = str_replace('_', ' ', $parts['vendor']);
                    $product = str_replace('_', ' ', $parts['product']);

                    if (strtolower($parts['target_sw']) === 'joomla' || str_contains(strtolower($vendor . ' ' . $product), 'joomla')) {
                        $mentions = true;
                    }

                    if ($parts['vendor'] === 'joomla' && ($parts['product'] === 'joomla!' || $parts['product'] === 'joomla')) {
                        $isCore = true;
                    }

                    $key = $vendor . '|' . $product;

                    if (!isset($seen[$key])) {
                        $seen[$key] = \count($affected);
                        $affected[] = [
                            'vendor'         => $vendor,
                            'product'        => $product,
                            'versions'       => [],
                            'fixed'          => '',
                            'platforms'      => strtolower($parts['target_sw']) === 'joomla' ? ['Joomla'] : [],
                            'collection_url' => '',
                            'package'        => '',
                            'default_status' => 'unaffected',
                        ];
                    }

                    $index = $seen[$key];

                    foreach ($ranges['ranges'] as $range) {
                        $affected[$index]['versions'][] = $range;
                    }

                    if ($ranges['fixed'] !== '' && $affected[$index]['fixed'] === '') {
                        $affected[$index]['fixed'] = $ranges['fixed'];
                    }
                }
            }
        }

        $cwes = [];

        foreach ((array) ($cve['weaknesses'] ?? []) as $weakness) {
            foreach ((array) ($weakness['description'] ?? []) as $entry) {
                $id = strtoupper(trim((string) ($entry['value'] ?? '')));

                // NVD-CWE-Other / NVD-CWE-noinfo are placeholders, not ids.
                if (preg_match('/^CWE-\d+$/', $id) && !isset($cwes[$id])) {
                    $cwes[$id] = ['id' => $id, 'description' => $id];
                }
            }
        }

        $cvss = self::pickCvss((array) ($cve['metrics'] ?? []));

        $references = [];

        foreach ((array) ($cve['references'] ?? []) as $reference) {
            $url = trim((string) ($reference['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $tags = [];

            foreach ((array) ($reference['tags'] ?? []) as $tag) {
                $mapped = self::TAG_MAP[strtolower(trim((string) $tag))] ?? null;

                if ($mapped !== null && !\in_array($mapped, $tags, true)) {
                    $tags[] = $mapped;
                }
            }

            $references[] = ['url' => $url, 'tags' => $tags];
        }

        $normalised = [
            'cve_id'           => $cveId,
            'assigner'         => (string) ($cve['sourceIdentifier'] ?? ''),
            'assigner_org_id'  => '',
            'state'            => $rejected ? 'REJECTED' : 'PUBLISHED',
            'data_version'     => '',
            'title'            => mb_substr($description, 0, 200),
            'description'      => $description,
            'description_html' => null,
            'affected'         => $affected,
            'cpes'             => $cpes,
            'cwes'             => array_values($cwes),
            'problem_text'     => '',
            'cvss'             => $cvss,
            'references'       => $references,
            'credits'          => [],
            'dates'            => [
                'reserved'  => null,
                'published' => CveRecordMapper::sql($cve['published'] ?? null),
                'updated'   => CveRecordMapper::sql($cve['lastModified'] ?? null),
            ],
            'solution'         => '',
            'workaround'       => '',
            'discovery'        => 'UNKNOWN',
            'source_url'       => 'https://nvd.nist.gov/vuln/detail/' . $cveId,
            'mentions_joomla'  => $mentions,
            'is_core'          => $isCore,
            'nvd_status'       => $status,
        ];

        return new FeedRecord(FeedSource::NVD, $cveId, $normalised, $item);
    }

    /**
     * Version first, then Primary within the version. v2 and SSVC are not candidates.
     *
     * @param array $metrics The `metrics` block.
     *
     * @return array|null  {version, vector, score, severity, from}.
     *
     * @since 4.0.0
     */
    public static function pickCvss(array $metrics): ?array
    {
        foreach (['cvssMetricV40' => '4.0', 'cvssMetricV31' => '3.1', 'cvssMetricV30' => '3.0'] as $key => $version) {
            $candidates = (array) ($metrics[$key] ?? []);

            if ($candidates === []) {
                continue;
            }

            usort($candidates, static fn (array $a, array $b): int => strcmp((string) ($a['type'] ?? 'Z'), (string) ($b['type'] ?? 'Z')));

            foreach ($candidates as $metric) {
                $data = $metric['cvssData'] ?? null;

                if (\is_array($data) && isset($data['vectorString'], $data['baseScore'])) {
                    $score = (float) $data['baseScore'];

                    return [
                        'version'  => $version,
                        'vector'   => (string) $data['vectorString'],
                        'score'    => $score,
                        'severity' => strtolower((string) ($data['baseSeverity'] ?? $metric['baseSeverity'] ?? Severity::fromScore($score)->value)),
                        'from'     => 'nvd:' . strtolower((string) ($metric['type'] ?? 'unknown')),
                    ];
                }
            }
        }

        return null;
    }
}
