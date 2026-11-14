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
use Jed\Tests\Feeds\Support\Cms;
use Jed\Tests\Feeds\Support\FakeFeeds;

/**
 * The CVE feed loses nothing:
 *
 *  - a delta log that does not load - error status, HTML, corrupt, cut off, changing under the
 *    read - fails the run and changes nothing (no ids queued, position and ETag unmoved);
 *  - a read cut short (Stop, closed page) changes nothing either;
 *  - records not reached (fetch cap, Stop, timeout) stay queued for the next run;
 *  - the position is the newest entry read, so runs neither overlap nor leave gaps.
 */
final class FeedLossTest extends FeedTestCase
{
    public function testALogThatDoesNotLoadChangesNothing(): void
    {
        $variants = ['HTTP 500', 'HTML with 200', 'HTML with 206', 'a corrupt entry', 'a file cut off inside the window'];

        foreach ($variants as $variant) {
            $this->setUp();

            $log            = FakeFeeds::deltaLog(time() - 300, 6, 3);
            FakeFeeds::$log = $log;
            $runner         = $this->runner();
            $good           = $runner->run(FeedSource::CNA, 600);
            $queueBefore    = $this->queued();

            // The log moves on, and then fails to load.
            array_unshift($log, FakeFeeds::entry(time(), 'CVE-2026-99999'));
            FakeFeeds::$log  = $log;
            FakeFeeds::$etag = '"e2"';
            $json            = FakeFeeds::logText();

            match ($variant) {
                'HTTP 500'        => FakeFeeds::$fail = [FakeFeeds::$reads + 1 => [500, 'oops']],
                'HTML with 200'   => FakeFeeds::$fail = [FakeFeeds::$reads + 1 => [200, '<html><body>Rate limited</body></html>']],
                'HTML with 206'   => FakeFeeds::$fail = [FakeFeeds::$reads + 1 => [206, '<html><body>Rate limited</body></html>']],
                'a corrupt entry' => FakeFeeds::$log = preg_replace('/"fetchTime": "([^"]+)"/', '"fetchTime" "$1"', $json, 1),
                // Inside the second entry: the old top, which this read has to reach.
                default => FakeFeeds::$log = substr($json, 0, strpos($json, '"fetchTime"', strpos($json, '"fetchTime"') + 1) + 40),
            };

            $bad  = $runner->run(FeedSource::CNA, 600);
            $next = $runner->start(FeedSource::CNA);

            self::assertSame('failed', $bad->status, "$variant: the run fails");
            self::assertStringStartsWith('COM_VEL_FEED_RUN_MSG_READ_FAILED', $bad->message, "$variant: and says nothing was changed");
            self::assertSame($queueBefore, $this->queued(), "$variant: the queue is as it was");
            self::assertNotContains('CVE-2026-99999', $this->stored(), "$variant: nothing from the bad read was used");
            self::assertSame($good->window_to, $next->window_from, "$variant: the next run starts from the last good position");
            self::assertEquals(FeedRunner::logPosition($good->cursor)['etag'], json_decode($next->progress, true)['read']['send_etag'], "$variant: with the last good ETag");
        }
    }

    public function testALargeLogIsReadInSlicesAndEveryIdIsQueuedOnce(): void
    {
        Cms::$params += ['cna_range_max_kb' => 64, 'cna_max_records_per_run' => 10];

        FakeFeeds::$log = FakeFeeds::deltaLog(time() - 120, 40, 25, 91000, false);   // ~1000 ids, ~340 KB

        $runner = $this->runner();
        $run    = $runner->start(FeedSource::CNA);
        $bars   = [];

        $this->stepRun($runner, (int) $run->id, static function (array $report) use (&$bars): bool {
            if ($report['phase'] === 'read') {
                $bars[] = $report['current'] / $report['total'];
            }

            return $report['phase'] === 'records';
        });

        $read = $this->progress((int) $run->id)['read'];

        self::assertGreaterThanOrEqual((int) ceil(\strlen(FakeFeeds::logText()) / 65536), $read['slices'], 'slices of at most 64 KB');
        self::assertSame(0, $read['restarts']);
        self::assertSame(FakeFeeds::ids(FakeFeeds::$log), $this->queued());

        $sorted = $bars;
        sort($sorted);

        self::assertGreaterThanOrEqual(4, \count($bars));
        self::assertSame($sorted, $bars, 'the progress bar only moves forward');
        self::assertLessThanOrEqual(1, max($bars));
    }

    public function testAnEntryBiggerThanASliceIsStillReadWhole(): void
    {
        Cms::$params['cna_range_max_kb'] = 64;

        FakeFeeds::$log = FakeFeeds::deltaLog(time() - 120, 3, 400, 80000, false);   // ~100 KB entries

        $this->readLog($this->runner());

        self::assertSame(FakeFeeds::ids(FakeFeeds::$log), $this->queued());
    }

    public function testALogThatChangesWhileBeingReadIsReadAgainAndNothingIsMissed(): void
    {
        Cms::$params['cna_range_max_kb'] = 64;

        foreach (['with a new ETag' => '"v2"', 'with no ETag change' => null] as $label => $newEtag) {
            $this->setUp();
            Cms::$params['cna_range_max_kb'] = 64;

            $log   = FakeFeeds::deltaLog(time() - 120, 40, 25, 91000, false);
            $added = FakeFeeds::deltaLog(time() + 1200, 2, 25, 97000, false);

            FakeFeeds::$log    = $log;
            FakeFeeds::$onRead = static function (int $n) use ($log, $added, $newEtag): void {
                if ($n === 3) {
                    FakeFeeds::$log  = array_merge($added, $log);
                    FakeFeeds::$etag = $newEtag ?? FakeFeeds::$etag;
                }
            };

            $runner = $this->runner();
            $run    = $this->readLog($runner);

            self::assertGreaterThanOrEqual(1, $this->progress((int) $run->id)['read']['restarts'], "$label: the read starts again");
            self::assertSame(FakeFeeds::ids(array_merge($added, $log)), $this->queued(), "$label: old and new entries all queued");

            $runner->stop((int) $run->id);

            self::assertSame($added[0]['fetchTime'], FeedRunner::logPosition($this->row((int) $run->id)->cursor)['top'], "$label: the position is the top as last read");
        }
    }

    public function testALogThatNeverHoldsStillFailsTheRunAndQueuesNothing(): void
    {
        Cms::$params['cna_range_max_kb'] = 64;

        $log               = FakeFeeds::deltaLog(time() - 120, 40, 25, 91000, false);
        FakeFeeds::$log    = $log;
        FakeFeeds::$onRead = static function (int $n) use ($log): void {
            FakeFeeds::$log  = array_merge(FakeFeeds::deltaLog(time() + $n * 700, 1, 5, 60000 + $n * 10, false), $log);
            FakeFeeds::$etag = '"v' . $n . '"';
        };

        $run = $this->runner()->run(FeedSource::CNA, 600);

        self::assertSame('failed', $run->status);
        self::assertStringContainsString('COM_VEL_ERROR_CVELIST_DELTA_CHANGING', $run->message);
        self::assertSame([], $this->queued());
    }

    public function testAFailureOnALaterSliceDiscardsWhatTheEarlierOnesStaged(): void
    {
        Cms::$params['cna_range_max_kb'] = 64;

        FakeFeeds::$log  = FakeFeeds::deltaLog(time() - 120, 40, 25, 91000, false);
        FakeFeeds::$fail = [3 => [503, 'busy']];

        $runner = $this->runner();
        $run    = $runner->start(FeedSource::CNA);

        $runner->step((int) $run->id, 0);
        $runner->step((int) $run->id, 0);

        self::assertGreaterThan(0, $this->count('SELECT COUNT(*) FROM jos_vel_feed_backlog WHERE staged_by IS NOT NULL'), 'staged while the read goes on');
        self::assertSame(0, $this->count('SELECT COUNT(*) FROM jos_vel_feed_backlog WHERE staged_by IS NULL'), 'not yet queued');

        self::assertSame('failed', $runner->step((int) $run->id, 0)['status']);
        self::assertSame([], $this->queued(), 'all discarded');
    }

    public function testStoppedOrInterruptedWhileReadingChangesNothing(): void
    {
        Cms::$params += ['cna_range_max_kb' => 64, 'cna_max_records_per_run' => 10];

        $log            = FakeFeeds::deltaLog(time() - 120, 40, 25, 91000, false);
        FakeFeeds::$log = $log;
        $runner         = $this->runner();
        $good           = $runner->run(FeedSource::CNA, 600);
        $queued         = $this->queued();

        // More new entries than one slice holds, so the next read takes several.
        FakeFeeds::$log  = array_merge(FakeFeeds::deltaLog(time() + 15 * 660, 12, 25, 70000, false), $log);
        FakeFeeds::$etag = '"v2"';

        $run = $runner->start(FeedSource::CNA);
        $runner->step((int) $run->id, 0);
        $runner->stop((int) $run->id);

        $row = $this->row((int) $run->id);

        self::assertSame('partial', $row->status);
        self::assertStringStartsWith('COM_VEL_FEED_RUN_MSG_READ_CUT', $row->message, 'says nothing was changed');
        self::assertNull($row->window_to, 'not a window for the next run');
        self::assertSame('', $row->cursor);
        self::assertSame($queued, $this->queued(), 'what it staged is gone');
        self::assertSame($good->window_to, $runner->start(FeedSource::CNA)->window_from, 'the next run reads from the last complete read');

        // Interrupted (the page closed) part way through the read: the same, on the next start.
        $run = $runner->start(FeedSource::CNA);
        $runner->step((int) $run->id, 0);

        $progress            = $this->progress((int) $run->id);
        $progress['touched'] = gmdate('Y-m-d H:i:s', time() - 1000);
        $this->db->setQuery('UPDATE jos_vel_feed_runs SET progress = ' . $this->db->quote(json_encode($progress)) . ' WHERE id = ' . (int) $run->id)->execute();

        $after = $runner->start(FeedSource::CNA);
        $row   = $this->row((int) $run->id);

        self::assertSame('partial', $row->status);
        self::assertStringContainsString('COM_VEL_FEED_RUN_MSG_INTERRUPTED', $row->message);
        self::assertNull($row->window_to);
        self::assertSame(0, $this->count('SELECT COUNT(*) FROM jos_vel_feed_backlog WHERE staged_by IS NOT NULL'));
        self::assertSame($good->window_to, $after->window_from);
    }

    public function testThePositionIsExactNoOverlapAndNoGap(): void
    {
        $log            = FakeFeeds::deltaLog(time() - 300, 6, 3);
        FakeFeeds::$log = $log;
        $runner         = $this->runner();
        $first          = $runner->run(FeedSource::CNA, 600);

        self::assertSame($log[0]['fetchTime'], FeedRunner::logPosition($first->cursor)['top'], 'the top is the newest entry, to the millisecond');

        // No 304 this time (as if the old tail was trimmed): still nothing queued twice.
        FakeFeeds::$etag = '"e1-trimmed"';
        $before          = $this->count('SELECT COUNT(*) FROM jos_vel_feed_backlog');
        $runner->run(FeedSource::CNA, 600);

        self::assertLessThanOrEqual($before, $this->count('SELECT COUNT(*) FROM jos_vel_feed_backlog'), 'an unchanged log queues nothing again');

        // Published late: its fetchTime is before the last run began, but after the top it saw.
        $late = FakeFeeds::entry(strtotime($first->started . ' UTC') - 5, 'CVE-2026-98888');

        self::assertGreaterThan(strtotime($log[0]['fetchTime']), strtotime($late['fetchTime']));

        FakeFeeds::$log  = array_merge([$late], $log);
        FakeFeeds::$etag = '"e3"';
        $runner->run(FeedSource::CNA, 600);

        self::assertContains('CVE-2026-98888', $this->stored(), 'a late entry is still picked up');
    }

    public function testTheQueueAfter304And404AndRepeatedFailures(): void
    {
        Cms::$params['cna_max_records_per_run'] = 10;

        FakeFeeds::$log = FakeFeeds::deltaLog(time() - 120);
        $runner         = $this->runner();

        $runner->run(FeedSource::CNA, 600);
        $left = $this->count('SELECT COUNT(*) FROM jos_vel_feed_backlog');

        $second = $runner->run(FeedSource::CNA, 600);   // 304: nothing new in the log

        self::assertGreaterThan(0, $left);
        self::assertLessThan($left, $this->count('SELECT COUNT(*) FROM jos_vel_feed_backlog'), 'after a 304 the run still works through the queue');
        self::assertNotSame('failed', $second->status);
        self::assertNotContains('CVE-2026-90007', $this->queued(), 'a 404 leaves the queue at once');
        self::assertGreaterThanOrEqual(1, $this->count("SELECT attempts FROM jos_vel_feed_backlog WHERE external_id = 'CVE-2026-90009'"), 'a failing record stays queued, attempts counted');

        for ($i = 0; $i < 8; $i++) {
            $last = $runner->run(FeedSource::CNA, 600);
        }

        self::assertSame(5, $this->count("SELECT attempts FROM jos_vel_feed_backlog WHERE external_id = 'CVE-2026-90009'"));
        self::assertSame('ok', $last->status, 'once set aside it no longer keeps the feed partial');
        self::assertStringContainsString('COM_VEL_FEED_RUN_MSG_SET_ASIDE|2|5', $last->message, 'but the run says so');
        self::assertCount(14, $this->stored(), 'everything else was stored');

        // The CVE list changes it again: it gets a fresh start.
        FakeFeeds::$log  = array_merge([FakeFeeds::entry(time(), 'CVE-2026-90009')], FakeFeeds::deltaLog(time() - 120));
        FakeFeeds::$etag = '"e9"';
        $runner->run(FeedSource::CNA, 600);

        self::assertSame(1, $this->count("SELECT attempts FROM jos_vel_feed_backlog WHERE external_id = 'CVE-2026-90009'"));
    }

    public function testARecordThatCannotBeStoredStaysQueued(): void
    {
        FakeFeeds::$log = FakeFeeds::deltaLog(time() - 120, 2, 2, 90000, false);

        $runner = $this->runner();
        $run    = $this->readLog($runner);

        $this->db->setQuery('ALTER TABLE jos_vel_feed_items RENAME TO jos_vel_feed_items_gone')->execute();
        $report = $runner->step((int) $run->id, 0);
        $this->db->setQuery('ALTER TABLE jos_vel_feed_items_gone RENAME TO jos_vel_feed_items')->execute();

        self::assertSame('failed', $report['status']);
        self::assertContains('CVE-2026-90000', $this->queued());
    }

    public function testRepeatedCappedRunsStoreEverythingAnUncappedRunWould(): void
    {
        FakeFeeds::$log = FakeFeeds::deltaLog(time() - 120);
        $this->runner()->run(FeedSource::CNA, 600);
        $reference = $this->stored();

        self::assertCount(14, $reference);

        foreach (['one call per run (the scheduler)' => false, 'step by step (the Feed Runs page)' => true] as $label => $stepped) {
            foreach (['the log standing still' => false, 'the log moving on between runs' => true] as $moving => $grows) {
                $this->setUp();
                Cms::$params['cna_max_records_per_run'] = 10;

                $log            = FakeFeeds::deltaLog(time() - 120);
                FakeFeeds::$log = $log;
                $runner         = $this->runner();

                for ($i = 0; $i < 6; $i++) {
                    if ($stepped) {
                        $this->stepRun($runner, (int) $runner->start(FeedSource::CNA)->id);
                    } else {
                        $runner->run(FeedSource::CNA, 600);
                    }

                    if ($grows) {
                        array_unshift($log, FakeFeeds::entry(time() + $i + 1, 'CVE-2026-' . (95000 + $i)));
                        FakeFeeds::$log  = $log;
                        FakeFeeds::$etag = '"e' . ($i + 2) . '"';
                    }
                }

                $stored = array_values(array_filter($this->stored(), static fn ($id) => !str_starts_with($id, 'CVE-2026-95')));

                self::assertSame($reference, $stored, "$label, $moving: nothing lost");
            }
        }
    }

    public function testARunFromBeforeTheUpdateIsContinuedFrom(): void
    {
        $this->db->setQuery("INSERT INTO jos_vel_feed_runs (source, started, finished, window_from, window_to, status, message, cursor) VALUES ('cna', '"
            . gmdate('Y-m-d H:i:s', time() - 7200) . "', '" . gmdate('Y-m-d H:i:s', time() - 7100) . "', '" . gmdate('Y-m-d H:i:s', time() - 10800) . "', '"
            . gmdate('Y-m-d H:i:s', time() - 7200) . "', 'ok', '', '\"old-etag\"')")->execute();

        FakeFeeds::$log = FakeFeeds::deltaLog(time() - 120, 6, 2);

        $runner = $this->runner();
        $run    = $runner->start(FeedSource::CNA);

        self::assertSame('"old-etag"', json_decode($run->progress, true)['read']['send_etag'], 'its plain ETag is used');
        self::assertSame(gmdate('Y-m-d H:i:s', time() - 7200 - 3600), $run->window_from, 'with the old one-hour overlap, once');

        $this->stepRun($runner, (int) $run->id);

        self::assertNotNull(FeedRunner::logPosition($this->row((int) $run->id)->cursor)['top'], 'and it leaves an exact position behind');
    }

    public function testAFeedOffForFortyDaysReadsAllTheLogHasAndSaysWhatItCannotHave(): void
    {
        Cms::$params['cna_lookback_days'] = 7;

        $oldTop = FakeFeeds::iso(time() - 40 * 86400);

        $this->db->setQuery("INSERT INTO jos_vel_feed_runs (source, started, window_to, status, message, cursor) VALUES ('cna', '"
            . gmdate('Y-m-d H:i:s', time() - 40 * 86400) . "', '" . gmdate('Y-m-d H:i:s', time() - 40 * 86400) . "', 'ok', '', "
            . $this->db->quote(FeedRunner::logPosition(null, '"x"', $oldTop)) . ')')->execute();

        FakeFeeds::$log = FakeFeeds::deltaLog(time() - 120, 3, 2);

        $run = $this->runner()->run(FeedSource::CNA, 600);

        self::assertSame('partial', $run->status);
        self::assertStringStartsWith('COM_VEL_FEED_RUN_MSG_GAP_EXCEEDS_RETENTION', $run->message);
        self::assertSame(gmdate('Y-m-d H:i:s', strtotime($oldTop)), $run->window_from, 'not clamped to the 7-day look-back');
        self::assertNotSame($oldTop, FeedRunner::logPosition($run->cursor)['top'], 'the position moves on to the top of the log');
    }
}
