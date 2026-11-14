<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Feeds;

use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Jed\Component\Vel\Administrator\Feed\FeedRunner;
use Jed\Component\Vel\Administrator\Service\VelService;
use Jed\Tests\Feeds\Support\Cms;
use Jed\Tests\Feeds\Support\FakeFeeds;
use Jed\Tests\Feeds\Support\TestDatabase;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * A fresh SQLite copy of the feed tables, fresh fake servers, and a runner, for every test.
 */
abstract class FeedTestCase extends TestCase
{
    protected DatabaseInterface $db;

    protected function setUp(): void
    {
        if (VEL_FEED_TESTS_SKIP !== '') {
            self::markTestSkipped(VEL_FEED_TESTS_SKIP);
        }

        Cms::reset(['nvd_api_key' => 'test-key']);   // a key: 1 s between NVD requests, not 6.5 s
        FakeFeeds::reset();
        FakeFeeds::install();

        $this->db = self::database();
    }

    /** The com_vel feed tables, in SQLite. */
    protected static function database(bool $withQueue = true, bool $withProgress = true): DatabaseInterface
    {
        return TestDatabase::create($withQueue, $withProgress);
    }

    protected function runner(int $httpTimeout = 60): FeedRunner
    {
        return new FeedRunner($this->db, new VelService(), $httpTimeout);
    }

    /** Step a run one unit of work at a time until it is done, or `$until` says stop. */
    protected function stepRun(FeedRunner $runner, int $runId, ?callable $until = null): array
    {
        for ($i = 0; $i < 5000; $i++) {
            $report = $runner->step($runId, 0);

            if ($report['done'] || ($until !== null && $until($report))) {
                return $report;
            }
        }

        self::fail('The run never finished.');
    }

    /** Start a CVE feed run and read the log until its records phase (or the end). */
    protected function readLog(FeedRunner $runner): object
    {
        $run = $runner->start(FeedSource::CNA);

        $this->stepRun($runner, (int) $run->id, static fn (array $report): bool => $report['phase'] === 'records');

        return $run;
    }

    protected function row(int $runId): object
    {
        return $this->db->setQuery('SELECT * FROM jos_vel_feed_runs WHERE id = ' . $runId)->loadObject();
    }

    protected function count(string $sql): int
    {
        return (int) $this->db->setQuery($sql)->loadResult();
    }

    /** @return list<string> */
    protected function queued(): array
    {
        return array_map('strval', $this->db->setQuery('SELECT external_id FROM jos_vel_feed_backlog ORDER BY external_id')->loadColumn());
    }

    /** @return list<string> */
    protected function stored(): array
    {
        return array_map('strval', $this->db->setQuery('SELECT external_id FROM jos_vel_feed_items ORDER BY external_id')->loadColumn());
    }

    protected function progress(int $runId): array
    {
        return (array) json_decode((string) $this->row($runId)->progress, true);
    }
}
