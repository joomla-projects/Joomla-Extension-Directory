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
use RuntimeException;

/**
 * FeedRunner in steps, as the Feed Runs page drives it: start, step, stop, the lock, abandoned
 * runs, the NVD throttle, what each step reports to the page.
 */
final class FeedRunnerStepsTest extends FeedTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeFeeds::$log = FakeFeeds::deltaLog(time() - 120);
        FakeFeeds::$nvd = FakeFeeds::nvdResults(1100);
    }

    public function testStartMakesNoRequestAndBeginsByReadingTheLog(): void
    {
        $run = $this->runner()->start(FeedSource::CNA);

        self::assertSame([], Cms::$calls, 'no outbound request');
        self::assertSame('running', $run->status);
        self::assertSame('read', json_decode((string) $run->progress, true)['phase']);
    }

    public function testABrowserStepUsesTheShortTimeoutAndStopsAtItsBudget(): void
    {
        $runner = $this->runner(FeedRunner::STEP_HTTP_TIMEOUT);
        $run    = $runner->start(FeedSource::CNA);

        $first = $runner->step((int) $run->id, 0);

        self::assertSame('records', $first['phase']);
        self::assertCount(1, Cms::$calls, 'the first step reads the delta log only');

        $report = $runner->step((int) $run->id, 3);

        self::assertSame([30], array_values(array_unique(array_column(Cms::$calls, 'timeout'))), 'every request times out at 30 s');
        self::assertFalse($report['done']);
        self::assertTrue($report['current'] > 1 && $report['current'] < $report['total'], 'a 3-second step stops part way through the list');

        $row = $this->row((int) $run->id);

        self::assertNull($row->heartbeat, 'the lock is released between steps');
        self::assertSame($report['seen'], (int) $row->items_seen, 'tallies are saved');
    }

    public function testPressingRunAgainAttachesAndOtherKindsOfRunAreRefused(): void
    {
        $runner = $this->runner();
        $run    = $runner->start(FeedSource::CNA);

        self::assertSame((int) $run->id, (int) $runner->start(FeedSource::CNA)->id, 'the same run');

        try {
            $runner->start(FeedSource::CNA, FeedRunner::MODE_SEED);
            self::fail('a seed must be refused while a CVE feed run is going');
        } catch (RuntimeException $e) {
            self::assertStringStartsWith('COM_VEL_ERROR_FEED_RUN_ACTIVE', $e->getMessage());
        }

        $nvd = $runner->start(FeedSource::NVD);

        self::assertNotSame((int) $run->id, (int) $nvd->id, 'the other feed runs alongside');
    }

    public function testOnlyOneRequestWorksOnARunAtATime(): void
    {
        $runner = $this->runner();
        $run    = $runner->start(FeedSource::CNA);
        $id     = (int) $run->id;

        $runner->step($id, 0);
        $this->db->setQuery("UPDATE jos_vel_feed_runs SET heartbeat = '" . gmdate('Y-m-d H:i:s') . "' WHERE id = $id")->execute();

        $seen = (int) $this->row($id)->items_seen;
        $busy = $runner->step($id, 0);

        self::assertTrue($busy['busy'], 'a second request is told the run is busy');
        self::assertSame($seen, (int) $this->row($id)->items_seen, 'and does nothing');
        self::assertTrue($runner->stop($id)['busy'], 'stop waits too');

        $this->db->setQuery("UPDATE jos_vel_feed_runs SET heartbeat = '" . gmdate('Y-m-d H:i:s', time() - 301) . "' WHERE id = $id")->execute();

        self::assertFalse($runner->step($id, 0)['busy'], 'a lock older than 5 minutes (a request that died) is taken over');
    }

    public function testStopLeavesTheRestQueuedAndTheNextRunStartsExactlyThere(): void
    {
        $runner = $this->runner();
        $run    = $this->readLog($runner);

        $runner->step((int) $run->id, 0);

        $report  = $runner->stop((int) $run->id);
        $stopped = $this->row((int) $run->id);
        $queued  = $this->count('SELECT COUNT(*) FROM jos_vel_feed_backlog');

        self::assertTrue($report['done']);
        self::assertSame('partial', $stopped->status);
        self::assertStringStartsWith('COM_VEL_FEED_RUN_MSG_STOPPED', $stopped->message);
        self::assertTrue($queued > 0);
        self::assertStringContainsString('COM_VEL_FEED_RUN_MSG_QUEUED|' . $queued, $stopped->message, 'the message says how many are queued');
        self::assertNull($stopped->progress);
        self::assertNull($stopped->heartbeat);
        self::assertTrue($runner->step((int) $run->id, 0)['done'], 'stepping a closed run only reports it');

        $next = $runner->start(FeedSource::CNA);

        self::assertSame($stopped->window_to, $next->window_from, 'no gap and no overlap');
    }

    public function testNvdStoppedInDiscoveryIsPartialButInEnrichmentIsOk(): void
    {
        $runner = $this->runner();
        $run    = $runner->start(FeedSource::NVD);

        $runner->step((int) $run->id, 0);
        $runner->stop((int) $run->id);

        $row = $this->row((int) $run->id);

        self::assertSame('partial', $row->status);
        self::assertSame($row->window_from, $row->window_to, 'the next run covers the same window again');

        $run = $runner->start(FeedSource::NVD);

        for ($i = 0; $i < 3; $i++) {
            $runner->step((int) $run->id, 0);   // 1100 results: 3 pages, discovery complete
        }

        self::assertSame('done', $runner->stop((int) $run->id)['phase']);
        self::assertSame('ok', $this->row((int) $run->id)->status, 'enrichment is best effort');
    }

    public function testNvdPagesAndEnrichesLikeTheOneRequestRunnerDid(): void
    {
        foreach ([['CVE-2025-1111', 'published'], ['CVE-2025-2222', 'ready'], ['CVE-2025-3333', 'draft']] as $i => [$cve, $state]) {
            $this->db->setQuery('INSERT INTO jos_vel_advisories (id, cve_id, state, cvss_vector, date_updated) VALUES (' . ($i + 1) . ", '$cve', '$state', NULL, '2026-01-0" . ($i + 1) . "')")->execute();
        }

        $run = $this->runner()->run(FeedSource::NVD, 600);

        self::assertSame('ok', $run->status);
        self::assertSame(1100, (int) $run->items_seen);
        self::assertSame(880, (int) $run->items_new);
        self::assertContains('CVE-2025-1111', $this->stored(), 'a published advisory is enriched');
        self::assertContains('CVE-2025-2222', $this->stored(), 'so is a ready one');
        self::assertNotContains('CVE-2025-3333', $this->stored(), 'a draft is not');
    }

    public function testAnNvdRefusalFailsTheRunLoudly(): void
    {
        FakeFeeds::$nvdFail = [500 => 403];

        $run = $this->runner()->run(FeedSource::NVD, 600);

        self::assertSame('failed', $run->status);
        self::assertSame('COM_VEL_ERROR_NVD_DISCOVERY|COM_VEL_ERROR_NVD_RATE_LIMIT|403', $run->message);
        self::assertNull($run->progress);
    }

    public function testNvdRequestsInSeparateStepsKeepTheirPause(): void
    {
        $runner = $this->runner();
        $run    = $runner->start(FeedSource::NVD);

        for ($i = 0; $i < 3; $i++) {
            $runner->step((int) $run->id, 0);
        }

        $times = array_column(array_filter(Cms::$calls, static fn ($c) => str_starts_with($c['url'], FakeFeeds::NVD)), 'at');

        self::assertCount(3, $times);
        self::assertTrue(min($times[1] - $times[0], $times[2] - $times[1]) >= 0.99, 'at least 1 s apart (with an API key)');
    }

    public function testTheSeedIsResumable(): void
    {
        Cms::$params['cna_max_records_per_run'] = 10;

        FakeFeeds::$seedOurs    = array_map(static fn ($i) => 'CVE-2026-' . (60000 + $i), range(0, 13));
        FakeFeeds::$seedKeyword = array_merge(\array_slice(FakeFeeds::$seedOurs, 0, 4), ['CVE-2026-61000', 'CVE-2026-61001', 'CVE-2019-1234']);

        $first  = $this->runner()->seed(600);
        $second = $this->runner()->seed(600);

        self::assertSame('partial', $first->status, '16 ids, 10 per run');
        self::assertSame(10, (int) $first->items_seen);
        self::assertSame('ok', $second->status);
        self::assertSame(6, (int) $second->items_seen, 'the second seed fetches the rest');
        self::assertCount(16, $this->stored());
    }

    public function testADisabledFeedSaysSoAndWritesNoRun(): void
    {
        Cms::$params['cna_enabled'] = 0;

        $run = $this->runner()->start(FeedSource::CNA);

        self::assertSame('ok', $run->status);
        self::assertSame('COM_VEL_FEED_RUN_MSG_DISABLED', $run->message);
        self::assertSame(0, $this->count('SELECT COUNT(*) FROM jos_vel_feed_runs'));
    }

    public function testAbandonedRunsAreClosedWhenTheFeedNextStarts(): void
    {
        $runner = $this->runner();
        $old    = $this->readLog($runner);

        $progress            = $this->progress((int) $old->id);
        $progress['touched'] = gmdate('Y-m-d H:i:s', time() - 1000);

        $this->db->setQuery('UPDATE jos_vel_feed_runs SET progress = ' . $this->db->quote(json_encode($progress)) . ' WHERE id = ' . (int) $old->id)->execute();
        $this->db->setQuery("INSERT INTO jos_vel_feed_runs (source, started, window_to, status, message, cursor) VALUES ('cna', '" . gmdate('Y-m-d H:i:s', time() - 5000) . "', NULL, 'running', '', '')")->execute();

        $legacy = (int) $this->db->insertid();
        $new    = $runner->start(FeedSource::CNA);

        self::assertSame('partial', $this->row((int) $old->id)->status, 'untouched for 15 minutes: closed');
        self::assertStringStartsWith('COM_VEL_FEED_RUN_MSG_INTERRUPTED', $this->row((int) $old->id)->message);
        self::assertSame('failed', $this->row($legacy)->status, 'left running by the old one-request runner: failed');
        self::assertNotSame((int) $old->id, (int) $new->id);
        self::assertSame((int) $new->id, (int) $runner->start(FeedSource::CNA)->id, 'a recently stepped run is not abandoned');
    }

    public function testTwoStartsRacingKeepTheOlderRun(): void
    {
        $this->db->setQuery("INSERT INTO sqlite_sequence (name, seq) VALUES ('jos_vel_feed_runs', 100)")->execute();

        $progress = $this->db->quote(json_encode(['phase' => 'read', 'mode' => 'delta', 'to' => gmdate('Y-m-d H:i:s'), 'touched' => gmdate('Y-m-d H:i:s'), 'read' => ['done' => false]]));

        // Another request's run appears right after this one's insert.
        $this->db->setQuery("CREATE TRIGGER racer AFTER INSERT ON jos_vel_feed_runs WHEN NEW.id > 100 BEGIN
            INSERT INTO jos_vel_feed_runs (id, source, started, window_from, window_to, status, message, cursor, progress)
            VALUES (50, 'cna', NEW.started, NEW.window_from, NEW.window_to, 'running', '', '', $progress); END")->execute();

        $run = $this->runner()->start(FeedSource::CNA);

        self::assertSame(50, (int) $run->id);
        self::assertSame([50], array_map('intval', $this->db->setQuery('SELECT id FROM jos_vel_feed_runs')->loadColumn()));
    }

    public function testAnOutOfDateSchemaIsReportedNotSilentlyBroken(): void
    {
        foreach (['no progress columns' => [true, false], 'no queue table' => [false, true]] as $label => [$queue, $progress]) {
            $this->db = self::database($queue, $progress);

            try {
                $this->runner()->start(FeedSource::CNA);
                self::fail("$label: start() must refuse");
            } catch (RuntimeException $e) {
                self::assertSame('COM_VEL_ERROR_FEED_RUN_SCHEMA', $e->getMessage(), $label);
            }
        }
    }

    public function testAnUnknownRunIsA404(): void
    {
        try {
            $this->runner()->step(999999);
            self::fail('no such run');
        } catch (RuntimeException $e) {
            self::assertSame(404, $e->getCode());
        }
    }

    public function testEachStepReportsWhatItDidAndWhatItAskedOfTheServers(): void
    {
        $runner = $this->runner();
        $run    = $runner->start(FeedSource::CNA);
        $read   = $runner->step((int) $run->id, 0);

        $expected = ['id', 'source', 'label', 'status', 'statusLabel', 'done', 'busy', 'phase', 'activity', 'current', 'total', 'seen', 'new', 'updated', 'message', 'elapsed', 'events', 'requests', 'requestsDropped'];

        self::assertSame([], array_diff($expected, array_keys($read)), 'every key the page reads');
        self::assertNotFalse(json_encode($read));
        self::assertCount(1, $read['requests']);
        self::assertSame(FakeFeeds::RAW . 'cves/deltaLog.json', $read['requests'][0]['url']);
        self::assertSame(206, $read['requests'][0]['status']);
        self::assertStringStartsWith('bytes=0-', $read['requests'][0]['range']);

        $record = $runner->step((int) $run->id, 0);

        self::assertCount(1, $record['requests'], 'one record fetched');
        self::assertStringStartsWith(FakeFeeds::RAW . 'cves/2026/', $record['requests'][0]['url']);
        self::assertSame([], array_diff(['method', 'url', 'range', 'status', 'bytes', 'ms', 'error'], array_keys($record['requests'][0])));
    }
}
