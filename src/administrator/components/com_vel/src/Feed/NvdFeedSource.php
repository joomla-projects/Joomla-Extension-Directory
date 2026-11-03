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

use DateTimeImmutable;
use Jed\Component\Vel\Administrator\Enum\FeedClassification;
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Joomla\CMS\Log\Log;

/**
 * The NVD feed (VEL2-PRD §6.6.2): discovery by keyword, enrichment by id.
 *
 * The NVD's own source identifier for a record is an address, not a CNA short name, so the
 * classifier's "ours" test cannot fire here; what the NVD contributes for our own records is
 * enrichment, done by {@see self::fetchOne()} for every advisory with a CVE id. Discovery keeps
 * other-CNA records that name a Joomla extension.
 *
 * @since 4.0.0
 */
final class NvdFeedSource implements FeedSourceInterface
{
    /**
     * @param NvdClient        $client     The client.
     * @param RecordClassifier $classifier The classifier.
     * @param string           $keyword    The discovery keyword.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly NvdClient $client,
        private readonly RecordClassifier $classifier,
        private readonly string $keyword = 'Joomla'
    ) {
    }

    /**
     * @return FeedSource
     *
     * @since 4.0.0
     */
    public function source(): FeedSource
    {
        return FeedSource::NVD;
    }

    /**
     * @param DateTimeImmutable $from     Window start.
     * @param DateTimeImmutable $to       Window end.
     * @param callable          $deadline Budget check.
     *
     * @return iterable<FeedRecord>
     *
     * @since 4.0.0
     */
    public function fetch(DateTimeImmutable $from, DateTimeImmutable $to, callable $deadline): iterable
    {
        // The API refuses windows over 120 days; split.
        $cursor = $from;

        while ($cursor < $to) {
            $end   = min($to, $cursor->modify('+119 days'));
            $start = 0;

            do {
                if ($deadline()) {
                    return;
                }

                try {
                    $page = $this->client->search($this->keyword, $cursor, $end, $start, 500);
                } catch (\Throwable $e) {
                    Log::add('VEL NVD feed: ' . $e->getMessage(), Log::WARNING, 'com_vel');

                    return;
                }

                $items = (array) ($page['vulnerabilities'] ?? []);

                foreach ($items as $item) {
                    if (!\is_array($item)) {
                        continue;
                    }

                    $mapped = NvdRecordMapper::map($item);

                    if ($mapped === null) {
                        continue;
                    }

                    $mapped->classification = $this->classifier->classify($mapped);

                    // Core and "mentions Joomla somewhere" are the NVD's only useful verdicts;
                    // a description that merely contains the word is the one to review.
                    if ($mapped->classification === FeedClassification::UNRELATED && (bool) ($mapped->normalised['mentions_joomla'] ?? false)) {
                        $mapped->classification = FeedClassification::UNKNOWN;
                    }

                    if ($mapped->classification !== FeedClassification::UNRELATED && $this->classifier->worthKeeping($mapped->classification) || $mapped->classification === FeedClassification::UNKNOWN) {
                        yield $mapped;
                    }
                }

                $total = (int) ($page['totalResults'] ?? 0);
                $start += max(1, \count($items));
            } while ($items !== [] && $start < $total);

            $cursor = $end;
        }
    }

    /**
     * @param string $cveId The id.
     *
     * @return FeedRecord|null
     *
     * @since 4.0.0
     */
    public function fetchOne(string $cveId): ?FeedRecord
    {
        $item = $this->client->byId($cveId);

        if ($item === null) {
            return null;
        }

        $mapped = NvdRecordMapper::map($item);

        if ($mapped !== null) {
            $mapped->classification = $this->classifier->classify($mapped);
        }

        return $mapped;
    }
}
