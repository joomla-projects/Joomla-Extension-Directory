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
use Jed\Component\Vel\Administrator\Enum\FeedSource;

/**
 * One external vulnerability record in the shape the VEL reads, whichever feed it came from
 * (VEL2-PRD §6.6). The CVE 5.x mapper and the NVD mapper both produce this; the classifier,
 * the merger and the review screen read only this.
 *
 * The `normalised` array:
 *
 *   cve_id, assigner, state, title, description, description_html,
 *   affected[]   {vendor, product, versions[] {from, to, fixed}, platforms[], collection_url, package}
 *   cwes[]       {id, description}
 *   cvss         {version, vector, score, severity} | null
 *   references[] {url, tags[]}
 *   credits[]    {name, role}
 *   dates        {reserved, published, updated}  (SQL datetimes, UTC)
 *   source_url, mentions_joomla (bool), is_core (bool)
 *
 * @since 4.0.0
 */
final class FeedRecord
{
    /**
     * @param FeedSource              $source         Which feed.
     * @param string                  $externalId     The CVE id.
     * @param array                   $normalised     See the class comment.
     * @param array                   $payload        The record as received.
     * @param FeedClassification|null $classification Set by the classifier.
     *
     * @since 4.0.0
     */
    public function __construct(
        public readonly FeedSource $source,
        public readonly string $externalId,
        public readonly array $normalised,
        public readonly array $payload,
        public ?FeedClassification $classification = null
    ) {
    }

    /**
     * @return string  Stable hash of what matters, so an unchanged record is skipped.
     *
     * @since 4.0.0
     */
    public function hash(): string
    {
        $copy = $this->normalised;
        unset($copy['dates']['updated']);

        return hash('sha256', (string) json_encode($copy));
    }

    /**
     * @return string
     *
     * @since 4.0.0
     */
    public function title(): string
    {
        return mb_substr((string) ($this->normalised['title'] ?? ''), 0, 255);
    }

    /**
     * @return string|null  SQL datetime.
     *
     * @since 4.0.0
     */
    public function updated(): ?string
    {
        return $this->normalised['dates']['updated'] ?? null;
    }

    /**
     * @return string
     *
     * @since 4.0.0
     */
    public function assigner(): string
    {
        return mb_substr((string) ($this->normalised['assigner'] ?? ''), 0, 64);
    }
}
