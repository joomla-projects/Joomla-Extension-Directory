<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Queue;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Hit\HitAggregator;

/**
 * Rolls the raw hit log into daily buckets on demand.
 *
 * The scheduled routine is the normal path; this exists for the case where somebody needs a day
 * recomputed now - after a backfill, or after finding that a run was missed. Safe to enqueue
 * repeatedly, because the aggregation replaces a day rather than adding to it.
 *
 * @since 4.1.0
 */
class HitAggregateJobHandler implements JobHandlerInterface
{
    /**
     * @param HitAggregator $aggregator The aggregator.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly HitAggregator $aggregator)
    {
    }

    /**
     * @param object $job The job row.
     *
     * @return array<string, mixed>
     *
     * @since 4.1.0
     */
    public function handle(object $job): array
    {
        $meta = json_decode((string) ($job->payload ?? '{}'), true) ?: [];
        $day  = (string) ($meta['day'] ?? '');

        if ($day !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return ['day' => $day, 'rows' => $this->aggregator->aggregateDay($day)];
        }

        return $this->aggregator->run((int) ($meta['days'] ?? 2));
    }
}
