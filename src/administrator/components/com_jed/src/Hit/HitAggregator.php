<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Hit;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Rolls the raw log into daily buckets, then throws the raw rows away.
 *
 * **Idempotent by construction.** The aggregate for a day is *recomputed* from the log rather
 * than added to it - `REPLACE`, not `+=` - so running the job twice, or re-running it after a
 * crash halfway through, produces the same numbers. An aggregation job that can double its own
 * counts is a job nobody dares re-run, and that is exactly when you need to.
 *
 * The consequence is that a day can only be aggregated while its raw rows still exist, which is
 * what the retention window has to be longer than. 30 days against a job that runs daily leaves
 * an enormous margin.
 *
 * **Robots and suspicious hits are counted out** of `views` and `download_clicks` and into
 * `robot_hits`, rather than dropped. Keeping the number means "how much of our traffic is
 * automated" stays answerable once the raw rows are gone; keeping it *separate* means the
 * ranking signal is what a person did.
 *
 * @since 4.0.0
 */
class HitAggregator
{
    /**
     * How long raw rows are kept, in days.
     *
     * Taken from what JED3 actually did: its `jed_hit_log` held 2,158,587 rows spanning exactly
     * 31 days when it was measured, so 30 days is the retention the legacy system settled on and
     * lived with rather than a number chosen here. Long enough to investigate an abuse case,
     * short enough that the table stays a few million rows instead of a hundred million.
     *
     * @since 4.0.0
     */
    public const RETENTION_DAYS = 30;

    /**
     * @param DatabaseInterface $db The database.
     *
     * @since 4.0.0
     */
    public function __construct(protected readonly DatabaseInterface $db)
    {
    }

    /**
     * Aggregate the days that have raw rows, and prune what is past retention.
     *
     * @param int $days How many days back to recompute. Two by default, so a run that was missed
     *                  yesterday repairs itself and a day still receiving hits at midnight is
     *                  completed on the next pass.
     *
     * @return array{days: int, rows: int, pruned: int}
     *
     * @since 4.0.0
     */
    public function run(int $days = 2): array
    {
        $rows = 0;

        for ($back = 0; $back < max(1, $days); $back++) {
            $rows += $this->aggregateDay(Factory::getDate('-' . $back . ' days')->format('Y-m-d'));
        }

        return ['days' => max(1, $days), 'rows' => $rows, 'pruned' => $this->prune()];
    }

    /**
     * Recompute one day's buckets from the log.
     *
     * @param string $day The date, `Y-m-d`.
     *
     * @return int  Listings written for that day.
     *
     * @since 4.0.0
     */
    public function aggregateDay(string $day): int
    {
        $from = $day . ' 00:00:00';
        $to   = $day . ' 23:59:59';

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('extension_id'),
                'SUM(CASE WHEN ' . $this->db->quoteName('hit_type') . " = 'view' AND "
                    . $this->db->quoteName('is_robot') . ' = 0 AND ' . $this->db->quoteName('suspicious')
                    . ' = 0 THEN 1 ELSE 0 END) AS ' . $this->db->quoteName('views'),
                'SUM(CASE WHEN ' . $this->db->quoteName('hit_type') . " = 'download_click' AND "
                    . $this->db->quoteName('is_robot') . ' = 0 AND ' . $this->db->quoteName('suspicious')
                    . ' = 0 THEN 1 ELSE 0 END) AS ' . $this->db->quoteName('download_clicks'),
                'SUM(CASE WHEN ' . $this->db->quoteName('is_robot') . ' = 1 OR '
                    . $this->db->quoteName('suspicious') . ' = 1 THEN 1 ELSE 0 END) AS '
                    . $this->db->quoteName('robot_hits'),
            ])
            ->from($this->db->quoteName('#__jed_hit_log'))
            ->where($this->db->quoteName('hit_time') . ' BETWEEN :from AND :to')
            ->bind(':from', $from)
            ->bind(':to', $to)
            ->group($this->db->quoteName('extension_id'));

        $buckets = $this->db->setQuery($query)->loadObjectList() ?: [];

        // Cleared first: a listing that had hits yesterday and none today must end up at zero for
        // today rather than keeping yesterday's row, and a re-run after rows were pruned must not
        // leave a stale bucket behind.
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_hit_stats'))
                ->where($this->db->quoteName('period') . ' = :day')
                ->bind(':day', $day)
        )->execute();

        foreach ($buckets as $bucket) {
            $row = (object) [
                'extension_id'    => (int) $bucket->extension_id,
                'period'          => $day,
                'views'           => (int) $bucket->views,
                'download_clicks' => (int) $bucket->download_clicks,
                'robot_hits'      => (int) $bucket->robot_hits,
            ];

            $this->db->insertObject('#__jed_hit_stats', $row);
        }

        return \count($buckets);
    }

    /**
     * Delete raw rows past the retention window.
     *
     * @return int  Rows removed.
     *
     * @since 4.0.0
     */
    public function prune(): int
    {
        $cutoff = Factory::getDate('-' . self::RETENTION_DAYS . ' days')->toSql();

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_hit_log'))
                ->where($this->db->quoteName('hit_time') . ' < :cutoff')
                ->bind(':cutoff', $cutoff)
        )->execute();

        return (int) $this->db->getAffectedRows();
    }

    /**
     * Views and download clicks for one listing over a window, from the aggregate.
     *
     * @param int $extensionId The listing.
     * @param int $days        How far back.
     *
     * @return array{views: int, download_clicks: int}
     *
     * @since 4.0.0
     */
    public function totals(int $extensionId, int $days = 90): array
    {
        $since = Factory::getDate('-' . $days . ' days')->format('Y-m-d');

        $query = $this->db->getQuery(true)
            ->select('COALESCE(SUM(' . $this->db->quoteName('views') . '), 0) AS ' . $this->db->quoteName('views'))
            ->select('COALESCE(SUM(' . $this->db->quoteName('download_clicks') . '), 0) AS ' . $this->db->quoteName('download_clicks'))
            ->from($this->db->quoteName('#__jed_hit_stats'))
            ->where($this->db->quoteName('extension_id') . ' = :id')
            ->where($this->db->quoteName('period') . ' >= :since')
            ->bind(':id', $extensionId, ParameterType::INTEGER)
            ->bind(':since', $since);

        $row = $this->db->setQuery($query)->loadAssoc() ?: [];

        return [
            'views'           => (int) ($row['views'] ?? 0),
            'download_clicks' => (int) ($row['download_clicks'] ?? 0),
        ];
    }
}
