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

use Jed\Component\Vel\Administrator\Enum\FeedClassification;

/**
 * What an external record is to the VEL: the ten-rule cascade of the feeds PRD §5.1, measured
 * against the live corpus (R-01/R-02, 2026-11-05). The first rule that matches wins, and the
 * rule's name travels with the verdict so an editor can see why a record is in the queue.
 *
 * Why this is not CPE-led: NVD has tagged no Joomla CPE at all on 2024–2025 records and only 94
 * of the 326 Joomla CNA records of 2026 carry any `configurations`. What is regular and ours is
 * the CNA's 2026 house style - `Joomla Extension - <vendor> - …` and `Joomla! Core - [YYYYMMDD]
 * - …` - and the assigner. CPE is corroboration, never the lead.
 *
 * @since 4.0.0
 */
final class RecordClassifier
{
    /**
     * How the CNA spells itself in `affected[]` on core records (`Joomla! Project` live;
     * `Joomla` tolerated).
     *
     * @var string[]
     *
     * @since 4.0.0
     */
    private const CORE_VENDORS = ['joomla! project', 'joomla', 'joomla!', 'joomla project'];

    /**
     * @var string[]
     *
     * @since 4.0.0
     */
    private const CORE_PRODUCTS = ['joomla! cms', 'joomla!', 'joomla', 'joomla cms', 'joomla! core', 'joomla core'];

    /**
     * The NVD's own `sourceIdentifier` for the Joomla CNA (R-01.7: 558 records).
     *
     * @since 4.0.0
     */
    public const NVD_SOURCE_IDENTIFIER = 'security@joomla.org';

    /**
     * @param string $cnaShortName The Joomla CNA's short name.
     * @param string $cnaOrgId     The Joomla CNA's org id.
     * @param bool   $importOthers Whether other CNAs' Joomla records are kept.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly string $cnaShortName = 'Joomla',
        private readonly string $cnaOrgId = '',
        private readonly bool $importOthers = true
    ) {
    }

    /**
     * Classify, and write the verdict onto the record.
     *
     * @param FeedRecord $record The record.
     *
     * @return FeedClassification
     *
     * @since 4.0.0
     */
    public function classify(FeedRecord $record): FeedClassification
    {
        [$verdict, $rule, $confidence] = $this->decide($record->normalised);

        $record->classify($verdict, $rule, $confidence);

        return $verdict;
    }

    /**
     * The cascade.
     *
     * @param array $n A normalised record.
     *
     * @return array{0: FeedClassification, 1: string, 2: string}  [verdict, rule, confidence].
     *
     * @since 4.0.0
     */
    public function decide(array $n): array
    {
        // 1. Rejected or withdrawn.
        if (strtoupper((string) ($n['state'] ?? '')) === 'REJECTED' || strtolower((string) ($n['nvd_status'] ?? '')) === 'rejected') {
            return [FeedClassification::REJECTED, 'rejected_state', 'high'];
        }

        $ours        = $this->isOurs($n);
        $title       = (string) ($n['title'] ?? '');
        $description = (string) ($n['description'] ?? '');
        $affected    = (array) ($n['affected'] ?? []);

        // 2. Ours, and a product that is Joomla itself.
        if ($ours) {
            foreach ($affected as $product) {
                if (self::isCoreProduct((string) ($product['vendor'] ?? ''), (string) ($product['product'] ?? ''))) {
                    return [FeedClassification::JOOMLA_CORE, 'ours_core_product', 'high'];
                }
            }

            // 3. Ours, with the core title template.
            if (preg_match('/^Joomla!?\s*Core\s*-\s*\[\d{8}\]/i', $title) || preg_match('/^Joomla!?\s*Core\s*-\s*\[\d{8}\]/i', $description)) {
                return [FeedClassification::JOOMLA_CORE, 'ours_core_title', 'high'];
            }

            // 4. Ours: an extension record. The extension title template raises confidence.
            $templated = (bool) preg_match('/^Joomla Extension\s*-\s*/i', $title) || (bool) preg_match('/^Joomla Extension\s*-\s*/i', $description);

            return [FeedClassification::JOOMLA_CNA, $templated ? 'ours_extension_title' : 'ours_assigner', $templated ? 'high' : 'medium'];
        }

        // 5. CPE says core: vendor joomla, product joomla\! or joomla.
        foreach ((array) ($n['cpes'] ?? []) as $cpe) {
            $vendor  = strtolower((string) ($cpe['vendor'] ?? ''));
            $product = strtolower((string) ($cpe['product'] ?? ''));

            if ($vendor === 'joomla' && ($product === 'joomla!' || $product === 'joomla')) {
                return [FeedClassification::JOOMLA_CORE, 'cpe_core', 'high'];
            }
        }

        // Another CNA's core record without CPE, by product name.
        foreach ($affected as $product) {
            if (self::isCoreProduct((string) ($product['vendor'] ?? ''), (string) ($product['product'] ?? ''))) {
                return [FeedClassification::JOOMLA_CORE, 'core_product', 'medium'];
            }
        }

        // 6. CPE target_sw joomla: rare (13 records corpus-wide) but certain.
        foreach ((array) ($n['cpes'] ?? []) as $cpe) {
            if (strtolower((string) ($cpe['target_sw'] ?? '')) === 'joomla') {
                return $this->other('cpe_target_sw', 'high');
            }
        }

        // 7. The record's own product metadata says Joomla.
        foreach ($affected as $product) {
            $platforms = array_map('strtolower', array_map('strval', (array) ($product['platforms'] ?? [])));
            $url       = strtolower((string) ($product['collection_url'] ?? ''));

            foreach ($platforms as $platform) {
                if (str_contains($platform, 'joomla')) {
                    return $this->other('platform', 'high');
                }
            }

            if (str_contains($url, 'extensions.joomla.org')) {
                return $this->other('collection_url', 'high');
            }

            if (stripos((string) ($product['product'] ?? ''), 'joomla') !== false || stripos((string) ($product['vendor'] ?? ''), 'joomla') !== false) {
                return $this->other('product_name', 'medium');
            }
        }

        // 8. Description says Joomla and names an extension kind.
        if (preg_match('/joomla/i', $description) && preg_match('/\b(extension|component|module|plugin|template)s?\b/i', $description)) {
            return $this->other('description_extension', 'low');
        }

        // 9. Description merely mentions Joomla: a person decides ("FG Joomla to WordPress").
        if (preg_match('/joomla/i', $description) || preg_match('/joomla/i', $title)) {
            return [FeedClassification::UNCLASSIFIED, 'mentions_joomla', 'low'];
        }

        // 10. Nothing to do with us.
        return [FeedClassification::UNRELATED, 'no_signal', 'high'];
    }

    /**
     * @param string $rule       The rule.
     * @param string $confidence The confidence.
     *
     * @return array{0: FeedClassification, 1: string, 2: string}
     *
     * @since 4.0.0
     */
    private function other(string $rule, string $confidence): array
    {
        return [FeedClassification::JOOMLA_EXTENSION_OTHER_CNA, $rule, $confidence];
    }

    /**
     * Whether the Joomla CNA assigned the record: by short name or org id (CVE Program), or by
     * the NVD's `sourceIdentifier`.
     *
     * @param array $n A normalised record.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function isOurs(array $n): bool
    {
        $assigner = strtolower(trim((string) ($n['assigner'] ?? '')));

        return strcasecmp($assigner, $this->cnaShortName) === 0
            || ($this->cnaOrgId !== '' && strcasecmp((string) ($n['assigner_org_id'] ?? ''), $this->cnaOrgId) === 0)
            || $assigner === self::NVD_SOURCE_IDENTIFIER;
    }

    /**
     * Whether a vendor/product pair is Joomla itself rather than an extension. The CNA writes
     * `Joomla! Project` / `Joomla! CMS`; older records and other CNAs vary.
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
        $v = strtolower(trim($vendor));
        $p = strtolower(trim($product));

        if (!\in_array($v, self::CORE_VENDORS, true) && !str_contains($v, 'joomla')) {
            return false;
        }

        return \in_array($p, self::CORE_PRODUCTS, true) || (bool) preg_match('/^joomla!?\s*(cms|core|framework|platform)?$/', $p);
    }

    /**
     * @param FeedClassification $classification The verdict.
     *
     * @return bool  Whether the record goes into the review queue at all.
     *
     * @since 4.0.0
     */
    public function worthKeeping(FeedClassification $classification): bool
    {
        return match ($classification) {
            FeedClassification::JOOMLA_CNA                 => true,
            FeedClassification::JOOMLA_CORE                => true,
            FeedClassification::REJECTED                   => true,
            FeedClassification::UNCLASSIFIED               => true,
            FeedClassification::JOOMLA_EXTENSION_OTHER_CNA => $this->importOthers,
            default                                        => false,
        };
    }
}
