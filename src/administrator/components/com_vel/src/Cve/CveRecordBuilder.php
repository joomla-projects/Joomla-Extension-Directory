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

use Jed\Component\Vel\Administrator\Enum\CreditRole;
use Jed\Component\Vel\Administrator\Enum\CveState;
use Jed\Component\Vel\Administrator\Enum\ReferenceTag;
use Jed\Component\Vel\Administrator\Enum\Severity;
use Jed\Component\Vel\Administrator\Service\AdvisorySerializer;
use Jed\Component\Vel\Administrator\Version\Version;
use Joomla\CMS\Component\ComponentHelper;

/**
 * Turns one advisory into a CVE Record Format 5.x document (VEL2-PRD §6.5, Appendix C).
 *
 * Derives, never stores: the title prefix, the product suffix, the description's plain-text
 * form, the structured version ranges, the JED reference and the generator stamp are all
 * computed here from the lean internal model, which is the brief's point (§3: "keep the input
 * model lean and derive the final JSON structure from the stored data").
 *
 * Two shapes come out: the full record (what Vulnogram opens, what the brief's appendix shows)
 * and the CNA container alone (what CVE Services `POST /cve/{id}/cna` accepts).
 *
 * Framework-light on purpose: everything configurable is passed in, so the class can be unit
 * tested against the brief's example without a Joomla application.
 *
 * @since 4.0.0
 */
final class CveRecordBuilder
{
    /**
     * @var string  The generator stamp.
     *
     * @since 4.0.0
     */
    public const ENGINE = 'Joomla VEL 2.0';

    /**
     * @param AdvisorySerializer $serializer    For URLs and the plain-text description.
     * @param string             $orgId         The CNA's orgId.
     * @param string             $shortName     The CNA's short name.
     * @param string             $dataVersion   "5.1" or "5.2".
     * @param string             $titlePrefix   Rendered with {vendor}.
     * @param string             $productSuffix Appended to each product name.
     * @param string             $versionsStyle "structured" or "string".
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly AdvisorySerializer $serializer,
        private readonly string $orgId = '6ff30186-7fb7-4ad9-be33-533e7b05e586',
        private readonly string $shortName = 'Joomla',
        private readonly string $dataVersion = '5.1',
        private readonly string $titlePrefix = 'Joomla Extension - {vendor} - ',
        private readonly string $productSuffix = ' extension for Joomla',
        private readonly string $versionsStyle = 'structured'
    ) {
    }

    /**
     * A builder configured from the component options.
     *
     * @param AdvisorySerializer $serializer The serializer.
     *
     * @return self
     *
     * @since 4.0.0
     */
    public static function fromOptions(AdvisorySerializer $serializer): self
    {
        $params = ComponentHelper::getParams('com_vel');

        return new self(
            $serializer,
            (string) $params->get('cna_org_id', '6ff30186-7fb7-4ad9-be33-533e7b05e586'),
            (string) $params->get('cna_short_name', 'Joomla'),
            (string) $params->get('cve_data_version', '5.1'),
            (string) $params->get('title_prefix', 'Joomla Extension - {vendor} - '),
            (string) $params->get('product_suffix', ' extension for Joomla'),
            (string) $params->get('cve_versions_style', 'structured')
        );
    }

    /**
     * The full record.
     *
     * @param object $advisory An advisory with children.
     *
     * @return array<string, mixed>
     *
     * @since 4.0.0
     */
    public function record(object $advisory): array
    {
        $state = CveState::tryFrom((string) ($advisory->cve_state ?? '')) ?? CveState::NONE;
        $now   = self::stamp(null);

        $metadata = [
            'cveId'             => (string) ($advisory->cve_id ?? '') !== '' ? (string) $advisory->cve_id : 'CVE-YYYY-NNNNN',
            'assignerOrgId'     => $this->orgId,
            'assignerShortName' => $this->shortName,
            'state'             => $state === CveState::NONE ? 'RESERVED' : $state->cveValue(),
        ];

        if (!empty($advisory->date_reserved)) {
            $metadata['dateReserved'] = self::stamp((string) $advisory->date_reserved);
        }

        if (!empty($advisory->date_published)) {
            $metadata['datePublished'] = self::stamp((string) $advisory->date_published);
        }

        $metadata['dateUpdated'] = !empty($advisory->date_updated) ? self::stamp((string) $advisory->date_updated) : $now;

        return [
            'dataType'    => 'CVE_RECORD',
            'dataVersion' => $this->dataVersion,
            'cveMetadata' => $metadata,
            'containers'  => [
                'cna' => $this->cnaContainer($advisory),
            ],
        ];
    }

    /**
     * The CNA container alone.
     *
     * @param object $advisory An advisory with children.
     *
     * @return array<string, mixed>
     *
     * @since 4.0.0
     */
    public function cnaContainer(object $advisory): array
    {
        $products = (array) ($advisory->products ?? []);
        $vendor   = $products !== [] ? (string) $products[0]->vendor : '';
        $title    = $this->title($advisory, $vendor);
        $text     = AdvisorySerializer::text((string) ($advisory->description ?? ''));
        $html     = trim((string) ($advisory->description ?? ''));

        // The CVE description repeats the title, the way the Joomla CNA's records do: the
        // description is what the NVD and every aggregator show on its own.
        $value = $title . ' - ' . $text;

        $container = [
            'providerMetadata' => [
                'orgId'       => $this->orgId,
                'shortName'   => $this->shortName,
                'dateUpdated' => !empty($advisory->date_updated) ? self::stamp((string) $advisory->date_updated) : self::stamp(null),
            ],
            'title'        => mb_substr($title, 0, 256),
            'descriptions' => [
                array_filter([
                    'lang'            => 'en',
                    'value'           => mb_substr($value, 0, 4096),
                    'supportingMedia' => $html !== '' ? [[
                        'type'   => 'text/html',
                        'base64' => false,
                        'value'  => mb_substr($title . ' - ' . $html, 0, 16384),
                    ]] : null,
                ], static fn ($v): bool => $v !== null),
            ],
            'affected'     => $this->affected($products),
            'problemTypes' => $this->problemTypes((array) ($advisory->cwes ?? [])),
            'references'   => $this->references($advisory),
        ];

        $metrics = $this->metrics($advisory);

        if ($metrics !== []) {
            $container['metrics'] = $metrics;
        }

        $credits = $this->credits((array) ($advisory->credits ?? []));

        if ($credits !== []) {
            $container['credits'] = $credits;
        }

        if ((string) ($advisory->solution ?? '') !== '') {
            $container['solutions'] = [['lang' => 'en', 'value' => mb_substr((string) $advisory->solution, 0, 4096)]];
        }

        if ((string) ($advisory->workaround ?? '') !== '') {
            $container['workarounds'] = [['lang' => 'en', 'value' => mb_substr((string) $advisory->workaround, 0, 4096)]];
        }

        $timeline = $this->timeline($advisory);

        if ($timeline !== []) {
            $container['timeline'] = $timeline;
        }

        if (!empty($advisory->date_public)) {
            $container['datePublic'] = self::stamp((string) $advisory->date_public);
        }

        $container['source'] = [
            'discovery' => \in_array((string) ($advisory->discovery ?? ''), ['UNKNOWN', 'INTERNAL', 'EXTERNAL', 'USER'], true) ? (string) $advisory->discovery : 'UNKNOWN',
        ];

        $container['x_generator'] = ['engine' => self::ENGINE];

        return $container;
    }

    /**
     * @param object $advisory The advisory.
     * @param string $vendor   The first product's vendor.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function title(object $advisory, string $vendor): string
    {
        $prefix = str_replace('{vendor}', $vendor, $this->titlePrefix);

        // Do not double a prefix an editor already typed into the title.
        $title = trim((string) ($advisory->title ?? ''));

        if ($prefix !== '' && str_starts_with($title, trim($prefix))) {
            return $title;
        }

        return $prefix . $title;
    }

    /**
     * @param object[] $products The products with versions.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function affected(array $products): array
    {
        $affected = [];

        foreach ($products as $product) {
            $versions = [];

            foreach ((array) ($product->versions ?? []) as $range) {
                $from = (string) $range->version_from;
                $to   = (string) $range->version_to;
                $type = (string) ($range->version_type ?? 'custom') === 'semver' ? 'semver' : 'custom';

                if ($this->versionsStyle === 'string') {
                    // The single-string style of older Vulnogram records: "1.0.0-15.0.0".
                    $versions[] = [
                        'status'  => 'affected',
                        'version' => ($from === Version::ANY ? '0' : $from) . ($to === Version::ANY ? '-*' : '-' . $to),
                    ];

                    continue;
                }

                $entry = [
                    'version'     => $from === Version::ANY ? '0' : $from,
                    'status'      => 'affected',
                    'versionType' => $type,
                ];

                // An open-ended range is "everything from `version` on": lessThanOrEqual "*"
                // is how the schema spells that.
                $entry['lessThanOrEqual'] = $to === Version::ANY ? '*' : $to;

                $versions[] = $entry;
            }

            $entry = [
                'vendor'        => (string) $product->vendor,
                'product'       => (string) $product->product . $this->productSuffix,
                'defaultStatus' => \in_array((string) ($product->default_status ?? ''), ['affected', 'unaffected', 'unknown'], true) ? (string) $product->default_status : 'unaffected',
                'platforms'     => ['Joomla'],
            ];

            if ($versions !== []) {
                $entry['versions'] = $versions;
            }

            if ((string) ($product->element ?? '') !== '') {
                $entry['packageName'] = (string) $product->element;
            }

            if (!empty($product->extension_id)) {
                $entry['collectionURL'] = 'https://extensions.joomla.org';
            }

            $affected[] = $entry;
        }

        return $affected;
    }

    /**
     * @param object[] $cwes The advisory's CWEs.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function problemTypes(array $cwes): array
    {
        $descriptions = [];

        foreach ($cwes as $cwe) {
            $id   = strtoupper((string) $cwe->cwe_id);
            $text = trim((string) $cwe->description);

            if ($text === '') {
                $text = $id;
            } elseif (!str_starts_with($text, $id)) {
                $text = $id . ' ' . $text;
            }

            $descriptions[] = [
                'lang'        => 'en',
                'type'        => 'CWE',
                'cweId'       => $id,
                'description' => mb_substr($text, 0, 4096),
            ];
        }

        if ($descriptions === []) {
            // The schema wants at least one problem type; "n/a" is the CVE Program's own
            // convention for a record without a CWE.
            $descriptions[] = ['lang' => 'en', 'description' => 'n/a'];
        }

        return [['descriptions' => $descriptions]];
    }

    /**
     * The stored references plus the ones the VEL adds: the JED listing for each linked product
     * and the advisory's own page.
     *
     * @param object $advisory The advisory.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function references(object $advisory): array
    {
        $references = [];
        $seen       = [];

        foreach ((array) ($advisory->references ?? []) as $reference) {
            $url = trim((string) $reference->url);

            if ($url === '' || isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;
            $entry      = ['url' => $url];
            $tags       = array_values(array_filter(array_map('strval', (array) ($reference->tags ?? [])), static fn (string $t): bool => ReferenceTag::tryFrom($t) !== null));

            if ($tags !== []) {
                $entry['tags'] = $tags;
            }

            if ((string) ($reference->name ?? '') !== '') {
                $entry['name'] = mb_substr((string) $reference->name, 0, 512);
            }

            $references[] = $entry;
        }

        foreach ((array) ($advisory->products ?? []) as $product) {
            if (empty($product->extension_id)) {
                continue;
            }

            $url = 'https://extensions.joomla.org/extension/' . (int) $product->extension_id;

            if (!isset($seen[$url])) {
                $seen[$url]   = true;
                $references[] = ['url' => $url, 'tags' => ['product', ReferenceTag::JED->value]];
            }
        }

        $own = $this->serializer->url($advisory);

        if (!isset($seen[$own])) {
            $references[] = ['url' => $own, 'tags' => ['third-party-advisory'], 'name' => \sprintf('VEL-%04d', (int) $advisory->id)];
        }

        return $references;
    }

    /**
     * @param object $advisory The advisory.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function metrics(object $advisory): array
    {
        $vector = (string) ($advisory->cvss_vector ?? '');

        if ($vector === '' || $advisory->cvss_score === null) {
            return [];
        }

        $severity = Severity::tryFrom((string) ($advisory->severity ?? '')) ?? Severity::fromScore((float) $advisory->cvss_score);
        $version  = (string) ($advisory->cvss_version ?? '3.1');
        $key      = $version === '4.0' ? 'cvssV4_0' : 'cvssV3_1';

        return [[
            'format'    => 'CVSS',
            'scenarios' => [['lang' => 'en', 'value' => 'GENERAL']],
            $key        => [
                'version'      => $version === '4.0' ? '4.0' : '3.1',
                'vectorString' => $vector,
                'baseScore'    => (float) $advisory->cvss_score,
                'baseSeverity' => $severity->cveValue(),
            ],
        ]];
    }

    /**
     * @param object[] $credits The credits.
     *
     * @return array<int, array<string, string>>
     *
     * @since 4.0.0
     */
    private function credits(array $credits): array
    {
        $out = [];

        foreach ($credits as $credit) {
            if ((int) ($credit->is_public ?? 1) !== 1 || trim((string) $credit->name) === '') {
                continue;
            }

            $role  = CreditRole::tryFrom((string) ($credit->role ?? '')) ?? CreditRole::OTHER;
            $out[] = ['lang' => 'en', 'value' => mb_substr(trim((string) $credit->name), 0, 4096), 'type' => $role->cveValue()];
        }

        return $out;
    }

    /**
     * @param object $advisory The advisory.
     *
     * @return array<int, array<string, string>>
     *
     * @since 4.0.0
     */
    private function timeline(object $advisory): array
    {
        $timeline = [];

        foreach ([
            'date_reported'        => 'Reported to the Joomla VEL',
            'date_vendor_notified' => 'Vendor notified',
            'date_fixed'           => 'Fix released',
            'date_published'       => 'Advisory published',
        ] as $column => $label) {
            if (!empty($advisory->$column)) {
                $timeline[] = ['time' => self::stamp((string) $advisory->$column), 'lang' => 'en', 'value' => $label];
            }
        }

        usort($timeline, static fn (array $a, array $b): int => strcmp($a['time'], $b['time']));

        return $timeline;
    }

    /**
     * @param string|null $sql A SQL datetime (UTC), or null for now.
     *
     * @return string  ISO 8601 with milliseconds, the form CVE Services writes.
     *
     * @since 4.0.0
     */
    public static function stamp(?string $sql): string
    {
        try {
            $date = $sql === null || $sql === '' ? new \DateTimeImmutable('now', new \DateTimeZone('UTC')) : new \DateTimeImmutable($sql, new \DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            $date = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        return $date->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * @param array $document A record or container.
     *
     * @return string  Pretty JSON.
     *
     * @since 4.0.0
     */
    public static function encode(array $document): string
    {
        return (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
