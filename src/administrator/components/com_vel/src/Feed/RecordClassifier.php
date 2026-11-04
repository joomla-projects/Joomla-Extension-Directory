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
 * What an external record is to the VEL (VEL2-PRD §6.6.1).
 *
 *   joomla_cna                   assigned by the Joomla CNA, not about core
 *   joomla_core                  Joomla itself - the JSST's domain, listed for the record only
 *   joomla_extension_other_cna   another CNA's record that names a Joomla extension
 *   unrelated                    nothing to do with Joomla
 *
 * @since 4.0.0
 */
final class RecordClassifier
{
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
     * @param FeedRecord $record The record.
     *
     * @return FeedClassification
     *
     * @since 4.0.0
     */
    public function classify(FeedRecord $record): FeedClassification
    {
        $n        = $record->normalised;
        // Ours by short name (CVE Program), by org id, or - the NVD spells the assigner as an
        // address - by a joomla.org address (R-01 confirms the exact one).
        $assigner = strtolower((string) ($n['assigner'] ?? ''));
        $ours     = strcasecmp($assigner, $this->cnaShortName) === 0
            || ($this->cnaOrgId !== '' && strcasecmp((string) ($n['assigner_org_id'] ?? ''), $this->cnaOrgId) === 0)
            || str_ends_with($assigner, '@joomla.org');
        $isCore   = (bool) ($n['is_core'] ?? false);
        $mentions = (bool) ($n['mentions_joomla'] ?? false);

        if ($isCore) {
            // A record whose every product is core is core. One extension alongside core would
            // be odd but is treated as an extension record.
            $allCore = true;

            foreach ((array) ($n['affected'] ?? []) as $product) {
                if (!CveRecordMapper::isCoreProduct((string) ($product['vendor'] ?? ''), (string) ($product['product'] ?? ''))) {
                    $allCore = false;
                }
            }

            if ($allCore) {
                return FeedClassification::JOOMLA_CORE;
            }
        }

        if ($ours) {
            return FeedClassification::JOOMLA_CNA;
        }

        if ($mentions) {
            return FeedClassification::JOOMLA_EXTENSION_OTHER_CNA;
        }

        return FeedClassification::UNRELATED;
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
            FeedClassification::JOOMLA_EXTENSION_OTHER_CNA => $this->importOthers,
            default                                        => false,
        };
    }
}
