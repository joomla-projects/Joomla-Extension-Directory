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
 * NVD API 2.0 item -> {@see FeedRecord}.
 *
 * NVD adds what the VEL wants from it: CVSS from its own analysis (Primary) or the CNA's
 * (Secondary), CWE ids, and CPE configurations whose `target_sw` says "joomla" and whose
 * version bounds become ranges. Since 2024 many recent CVEs carry none of that for weeks; the
 * mapper copes with an item that is only a description.
 *
 * @since 4.0.0
 */
final class NvdRecordMapper
{
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

        $affected = [];
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

                    $ranges = VersionRangeMapper::fromCpeMatch($match);

                    if ($ranges === null) {
                        continue;
                    }

                    $vendor  = str_replace('_', ' ', $parts['vendor']);
                    $product = str_replace('_', ' ', $parts['product']);

                    if (strtolower($parts['target_sw']) === 'joomla' || str_contains(strtolower($vendor . ' ' . $product), 'joomla')) {
                        $mentions = true;
                    }

                    if (CveRecordMapper::isCoreProduct($vendor, $product)) {
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

                if (preg_match('/^CWE-\d+$/', $id) && !isset($cwes[$id])) {
                    $cwes[$id] = ['id' => $id, 'description' => $id];
                }
            }
        }

        $cvss = null;

        foreach (['cvssMetricV40' => '4.0', 'cvssMetricV31' => '3.1', 'cvssMetricV30' => '3.0'] as $key => $version) {
            $metrics = (array) ($cve['metrics'][$key] ?? []);

            // Primary (NVD's own) before Secondary (the CNA's).
            usort($metrics, static fn (array $a, array $b): int => strcmp((string) ($a['type'] ?? 'Z'), (string) ($b['type'] ?? 'Z')));

            foreach ($metrics as $metric) {
                $data = $metric['cvssData'] ?? null;

                if (\is_array($data) && isset($data['vectorString'], $data['baseScore'])) {
                    $score = (float) $data['baseScore'];
                    $cvss  = [
                        'version'  => $version,
                        'vector'   => (string) $data['vectorString'],
                        'score'    => $score,
                        'severity' => strtolower((string) ($data['baseSeverity'] ?? $metric['baseSeverity'] ?? Severity::fromScore($score)->value)),
                        'from'     => 'nvd:' . strtolower((string) ($metric['type'] ?? 'unknown')),
                    ];

                    break 2;
                }
            }
        }

        $references = [];

        foreach ((array) ($cve['references'] ?? []) as $reference) {
            $url = trim((string) ($reference['url'] ?? ''));

            if ($url !== '') {
                $references[] = ['url' => $url, 'tags' => array_values(array_map(static fn ($t): string => strtolower(str_replace(' ', '-', (string) $t)), (array) ($reference['tags'] ?? [])))];
            }
        }

        $assigner = (string) ($cve['sourceIdentifier'] ?? '');

        $normalised = [
            'cve_id'           => $cveId,
            'assigner'         => $assigner,
            'assigner_org_id'  => '',
            'state'            => strtoupper((string) ($cve['vulnStatus'] ?? 'PUBLISHED')) === 'REJECTED' ? 'REJECTED' : 'PUBLISHED',
            'title'            => mb_substr($description, 0, 200),
            'description'      => $description,
            'description_html' => null,
            'affected'         => $affected,
            'cwes'             => array_values($cwes),
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
            'nvd_status'       => (string) ($cve['vulnStatus'] ?? ''),
        ];

        return new FeedRecord(FeedSource::NVD, $cveId, $normalised, $item);
    }
}
