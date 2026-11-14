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
 * CVE Record Format 5.x -> {@see FeedRecord} (Appendix C in reverse).
 *
 * Reads the CNA container first and the ADP containers for what the CNA left out. On Joomla
 * records the only ADP is CISA-ADP carrying SSVC, not CVSS or CWE (R-02 finding D), so the
 * fallback rarely fires for ours; it is kept for other CNAs' records. `dataVersion` is carried
 * but never branched on: untouched records are still 5.1, anything written today is 5.2.
 *
 * @since 4.0.0
 */
final class CveRecordMapper
{
    /**
     * @param array $record A full CVE record.
     *
     * @return FeedRecord|null  Null for a record with no usable metadata.
     *
     * @since 4.0.0
     */
    public static function map(array $record): ?FeedRecord
    {
        $meta  = $record['cveMetadata'] ?? [];
        $cveId = strtoupper((string) ($meta['cveId'] ?? ''));

        if (!preg_match('/^CVE-\d{4}-\d{4,}$/', $cveId)) {
            return null;
        }

        $cna  = $record['containers']['cna'] ?? [];
        $adps = (array) ($record['containers']['adp'] ?? []);

        // A rejected record has a CNA container of `providerMetadata`, `rejectedReasons` and
        // `x_generator` only - no descriptions, no affected. It is mapped to the little it has.
        if (strtoupper((string) ($meta['state'] ?? '')) === 'REJECTED') {
            $reason = '';

            foreach ((array) ($cna['rejectedReasons'] ?? []) as $entry) {
                $reason = (string) ($entry['value'] ?? '');

                if (($entry['lang'] ?? 'en') === 'en') {
                    break;
                }
            }

            return new FeedRecord(FeedSource::CNA, $cveId, [
                'cve_id'           => $cveId,
                'assigner'         => (string) ($meta['assignerShortName'] ?? ''),
                'assigner_org_id'  => (string) ($meta['assignerOrgId'] ?? ''),
                'state'            => 'REJECTED',
                'data_version'     => (string) ($record['dataVersion'] ?? ''),
                'title'            => 'REJECTED: ' . $cveId,
                'description'      => $reason,
                'description_html' => null,
                'affected'         => [],
                'cpes'             => [],
                'cwes'             => [],
                'problem_text'     => '',
                'cvss'             => null,
                'references'       => [],
                'credits'          => [],
                'dates'            => [
                    'reserved'  => self::sql($meta['dateReserved'] ?? null),
                    'published' => self::sql($meta['datePublished'] ?? null),
                    'updated'   => self::sql($meta['dateUpdated'] ?? null),
                    'rejected'  => self::sql($meta['dateRejected'] ?? null),
                ],
                'solution'         => '',
                'workaround'       => '',
                'discovery'        => 'UNKNOWN',
                'source_url'       => 'https://www.cve.org/CVERecord?id=' . $cveId,
                'mentions_joomla'  => false,
                'is_core'          => false,
            ], $record);
        }

        $description = '';
        $html        = null;

        foreach ((array) ($cna['descriptions'] ?? []) as $entry) {
            if (($entry['lang'] ?? 'en') === 'en' || $description === '') {
                $description = (string) ($entry['value'] ?? '');

                foreach ((array) ($entry['supportingMedia'] ?? []) as $media) {
                    if (($media['type'] ?? '') === 'text/html' && empty($media['base64'])) {
                        $html = (string) ($media['value'] ?? '');
                    }
                }
            }
        }

        $affected = [];
        $mentions = false;
        $isCore   = false;

        foreach ((array) ($cna['affected'] ?? []) as $product) {
            $vendor  = trim((string) ($product['vendor'] ?? ''));
            $name    = trim((string) ($product['product'] ?? ''));
            $ranges  = VersionRangeMapper::fromCve((array) ($product['versions'] ?? []));
            $haystack = strtolower($vendor . ' ' . $name . ' ' . implode(' ', (array) ($product['platforms'] ?? [])) . ' ' . (string) ($product['collectionURL'] ?? ''));

            if (str_contains($haystack, 'joomla')) {
                $mentions = true;
            }

            if (RecordClassifier::isCoreProduct($vendor, $name)) {
                $isCore = true;
            }

            $affected[] = [
                'vendor'         => $vendor,
                'product'        => $name,
                'versions'       => $ranges['ranges'],
                'fixed'          => $ranges['fixed'],
                'platforms'      => array_values(array_map('strval', (array) ($product['platforms'] ?? []))),
                'collection_url' => (string) ($product['collectionURL'] ?? ''),
                'package'        => (string) ($product['packageName'] ?? ''),
                'default_status' => (string) ($product['defaultStatus'] ?? 'unaffected'),
            ];
        }

        if (stripos($description, 'joomla') !== false || stripos((string) ($cna['title'] ?? ''), 'joomla') !== false) {
            $mentions = true;
        }

        $cwes        = [];
        $problemText = '';

        foreach (array_merge([$cna], $adps) as $container) {
            foreach ((array) ($container['problemTypes'] ?? []) as $type) {
                foreach ((array) ($type['descriptions'] ?? []) as $entry) {
                    $id = strtoupper(trim((string) ($entry['cweId'] ?? '')));

                    if ($id === '' && preg_match('/\b(CWE-\d+)\b/i', (string) ($entry['description'] ?? ''), $m)) {
                        $id = strtoupper($m[1]);
                    }

                    if ($id !== '' && !isset($cwes[$id])) {
                        $cwes[$id] = ['id' => $id, 'description' => trim((string) ($entry['description'] ?? $id))];
                    } elseif ($id === '' && $problemText === '') {
                        // Pre-2026 Joomla records carry `type: "text"` with no CWE id
                        // ("Information Disclosure"). Kept for the editor, never as a CWE.
                        $problemText = trim((string) ($entry['description'] ?? ''));
                    }
                }
            }

            if ($cwes !== []) {
                break;
            }
        }

        $cvss = null;

        foreach (array_merge([$cna], $adps) as $container) {
            foreach ((array) ($container['metrics'] ?? []) as $metric) {
                foreach (['cvssV4_0' => '4.0', 'cvssV3_1' => '3.1', 'cvssV3_0' => '3.0'] as $key => $version) {
                    if (isset($metric[$key]['vectorString'], $metric[$key]['baseScore'])) {
                        $score = (float) $metric[$key]['baseScore'];
                        $cvss  = [
                            'version'  => $version,
                            'vector'   => (string) $metric[$key]['vectorString'],
                            'score'    => $score,
                            'severity' => strtolower((string) ($metric[$key]['baseSeverity'] ?? Severity::fromScore($score)->value)),
                            'from'     => $container === $cna ? 'cna' : (string) ($container['providerMetadata']['shortName'] ?? 'adp'),
                        ];

                        break 3;
                    }
                }
            }
        }

        $references = [];

        foreach ((array) ($cna['references'] ?? []) as $reference) {
            $url = trim((string) ($reference['url'] ?? ''));

            if ($url !== '') {
                $references[] = ['url' => $url, 'tags' => array_values(array_map('strval', (array) ($reference['tags'] ?? [])))];
            }
        }

        $credits = [];

        foreach ((array) ($cna['credits'] ?? []) as $credit) {
            $name = trim((string) ($credit['value'] ?? ''));

            if ($name !== '') {
                $credits[] = ['name' => $name, 'role' => str_replace(' ', '_', strtolower((string) ($credit['type'] ?? 'finder')))];
            }
        }

        $assigner = (string) ($meta['assignerShortName'] ?? '');

        $normalised = [
            'cve_id'           => $cveId,
            'assigner'         => $assigner,
            'assigner_org_id'  => (string) ($meta['assignerOrgId'] ?? ''),
            'state'            => strtoupper((string) ($meta['state'] ?? 'PUBLISHED')),
            'data_version'     => (string) ($record['dataVersion'] ?? ''),
            'title'            => (string) ($cna['title'] ?? mb_substr($description, 0, 200)),
            'description'      => $description,
            'description_html' => $html,
            'affected'         => $affected,
            'cpes'             => [],
            'cwes'             => array_values($cwes),
            'problem_text'     => $problemText,
            'cvss'             => $cvss,
            'references'       => $references,
            'credits'          => $credits,
            'dates'            => [
                'reserved'  => self::sql($meta['dateReserved'] ?? null),
                'published' => self::sql($meta['datePublished'] ?? null),
                'updated'   => self::sql($meta['dateUpdated'] ?? null),
            ],
            'solution'         => (string) (($cna['solutions'][0]['value'] ?? '')),
            'workaround'       => (string) (($cna['workarounds'][0]['value'] ?? '')),
            'discovery'        => (string) ($cna['source']['discovery'] ?? 'UNKNOWN'),
            'source_url'       => 'https://www.cve.org/CVERecord?id=' . $cveId,
            'mentions_joomla'  => $mentions,
            'is_core'          => $isCore,
        ];

        return new FeedRecord(FeedSource::CNA, $cveId, $normalised, $record);
    }

    /**
     * Whether a vendor/product pair is Joomla itself. Delegates to the classifier, which owns
     * the vocabulary (`Joomla! Project` / `Joomla! CMS` live).
     *
     * @param string $vendor  Vendor.
     * @param string $product Product.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function isCoreProduct(string $vendor, string $product): bool
    {
        return RecordClassifier::isCoreProduct($vendor, $product);
    }

    /**
     * @param mixed $iso An ISO timestamp or nothing.
     *
     * @return string|null  SQL datetime in UTC.
     *
     * @since 4.0.0
     */
    public static function sql(mixed $iso): ?string
    {
        if (!\is_string($iso) || trim($iso) === '') {
            return null;
        }

        try {
            // UTC is the default for a stamp without an offset (NVD's), and the conversion for one with.
            return (new \DateTimeImmutable($iso, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
