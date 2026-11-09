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
use Jed\Component\Vel\Administrator\Cve\CveServicesClient;
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use RuntimeException;

/**
 * The CVE Program feed (feeds PRD §3): the Joomla CNA's own records and other CNAs' records
 * that name a Joomla extension, from the cvelistV5 mirror - the only source available to a CNA.
 *
 * Two modes:
 *
 *  - **Delta polling** ({@see self::fetch()}): the head of `deltaLog.json` since the last run,
 *    every record in it fetched (capped per run), classified, and the unrelated ones dropped.
 *    Roughly 4,100 records change a day across the whole programme; a handful are Joomla's.
 *  - **Seed** ({@see self::seed()}): the one-off backfill a fresh installation needs, because
 *    the delta log reaches back 30 days only and a first run over it would fetch ~29,000
 *    records for a handful of ours. Ids come cheaply from the NVD (`sourceIdentifier` for ours,
 *    `keywordSearch` for everybody else's Joomla records - one request each) and from CVE
 *    Services' `GET /cve-id` when credentials exist (which also sees RESERVED ids the NVD
 *    cannot). The records themselves come from the mirror, which is authoritative.
 *
 * @since 4.0.0
 */
final class CnaFeedSource implements FeedSourceInterface
{
    /**
     * A read of the log that needs more slices than this is not reading the log (30 days at the
     * default 2 MB range cap is about 30 slices).
     *
     * @since 4.0.0
     */
    public const MAX_SLICES = 500;

    /**
     * Times a read starts again because the log changed under it before giving up.
     *
     * @since 4.0.0
     */
    public const MAX_RESTARTS = 3;

    /**
     * What the last fetch() left behind for the runner: whether the delta gap was covered, the
     * ETag to store, and where to resume from when it was not.
     *
     * @var array{complete: bool, reason: string, etag: ?string, resume_at: ?string, fetched: int}
     *
     * @since 4.0.0
     */
    private array $status = ['complete' => true, 'reason' => '', 'etag' => null, 'resume_at' => null, 'fetched' => 0];

    /**
     * @param CvelistClient          $cvelist    The mirror client.
     * @param RecordClassifier       $classifier Decides what is worth keeping.
     * @param int                    $maxRecords Per-run record fetch cap.
     * @param string|null            $etag       The ETag stored by the last run.
     * @param NvdClient|null         $nvd        For the seed's id lists.
     * @param CveServicesClient|null $services   For the seed's own-id list, when configured.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly CvelistClient $cvelist,
        private readonly RecordClassifier $classifier,
        private readonly int $maxRecords = 400,
        private readonly ?string $etag = null,
        private readonly ?NvdClient $nvd = null,
        private readonly ?CveServicesClient $services = null
    ) {
    }

    /**
     * @return FeedSource
     *
     * @since 4.0.0
     */
    public function source(): FeedSource
    {
        return FeedSource::CNA;
    }

    /**
     * @return array{complete: bool, reason: string, etag: ?string, resume_at: ?string, fetched: int}
     *
     * @since 4.0.0
     */
    public function status(): array
    {
        return $this->status;
    }

    /**
     * A fresh status array.
     *
     * @param string|null $etag The ETag to carry.
     *
     * @return array{complete: bool, reason: string, etag: ?string, resume_at: ?string, fetched: int}
     *
     * @since 4.0.0
     */
    public static function freshStatus(?string $etag = null): array
    {
        return ['complete' => true, 'reason' => '', 'etag' => $etag, 'resume_at' => null, 'fetched' => 0];
    }

    /**
     * Records changed since the window start, newest first.
     *
     * The one-pass form of {@see self::plan()} + {@see self::fetchItem()} + {@see self::close()},
     * kept for {@see FeedSourceInterface}. FeedRunner does not use it: it reads the log with
     * {@see self::readStep()} into the queue, which - unlike this - loses nothing when a pass
     * is cut short.
     *
     * @param DateTimeImmutable $from     Window start (the last run's end).
     * @param DateTimeImmutable $to       Window end (now).
     * @param callable          $deadline Budget check.
     *
     * @return iterable<FeedRecord>
     *
     * @since 4.0.0
     */
    public function fetch(DateTimeImmutable $from, DateTimeImmutable $to, callable $deadline): iterable
    {
        $plan         = $this->plan($from, $to);
        $this->status = $plan['status'];
        $oldest       = null;

        foreach ($plan['items'] as $item) {
            if ($deadline()) {
                $this->status = self::close($this->status, $oldest, false, 'time_budget');

                return;
            }

            $this->status['fetched']++;
            $oldest = $this->sqlStamp($item['fetchTime']) ?? $oldest;
            $record = $this->fetchItem($item);

            if ($record !== null) {
                yield $record;
            }
        }

        $this->status = self::close($this->status, $oldest, $plan['capped']);
    }

    /**
     * The first part of a delta pass: read the head of the delta log and list the records to
     * fetch, deduplicated, newest first, cut at the per-run fetch cap.
     *
     * @param DateTimeImmutable $from Window start (the last run's end).
     * @param DateTimeImmutable $to   Window end (now).
     *
     * @return array{items: list<array{id: string, url: string, fetchTime: string}>, capped: bool, status: array}
     *         `capped` says the list was cut at the fetch cap; `status` is the pass's status so
     *         far, to be handed to {@see self::close()} at the end.
     *
     * @throws \RuntimeException  When the log cannot be read.
     *
     * @since 4.0.0
     */
    public function plan(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $status = self::freshStatus($this->etag);

        // The log reaches back 30 days; a longer gap cannot be served by it.
        $floor = $to->modify('-30 days');

        if ($from < $floor) {
            $status['complete']  = false;
            $status['reason']    = 'gap_exceeds_retention';
            $status['resume_at'] = $floor->format('Y-m-d H:i:s');
            $from                = $floor;
        }

        $head = $this->cvelist->deltaEntriesSince($from, $this->etag);

        $status['etag'] = $head['etag'];

        if ($head['not_modified']) {
            return ['items' => [], 'capped' => false, 'status' => $status];
        }

        if (!$head['complete']) {
            // The Range cap was reached before the gap was covered: process what was read and
            // resume from its oldest entry next time.
            $status['complete'] = false;
            $status['reason']   = 'range_under_sized';
        }

        // Dedupe ids across entries (a record updated twice in the window appears twice),
        // newest first so a capped run still delivers the most recent items.
        $seen  = [];
        $items = [];

        foreach ($head['entries'] as $entry) {
            foreach (['new', 'updated'] as $bucket) {
                foreach ((array) ($entry[$bucket] ?? []) as $item) {
                    $id = strtoupper((string) ($item['cveId'] ?? ''));

                    if ($id === '' || isset($seen[$id]) || !preg_match('/^CVE-\d{4}-\d{4,}$/', $id)) {
                        continue;
                    }

                    $seen[$id] = true;
                    $items[]   = ['id' => $id, 'url' => (string) ($item['githubLink'] ?? ''), 'fetchTime' => (string) ($entry['fetchTime'] ?? '')];
                }
            }
        }

        return [
            'items'  => \array_slice($items, 0, $this->maxRecords),
            'capped' => \count($items) > $this->maxRecords,
            'status' => $status,
        ];
    }

    /**
     * One listed record: fetched from the mirror, mapped and classified.
     *
     * @param array{id: string, url?: string} $item    An entry of {@see self::plan()} or {@see self::seedIds()}.
     * @param string                          $context Log prefix.
     *
     * @return FeedRecord|null  Null when the record is missing, unreadable or not worth keeping.
     *
     * @since 4.0.0
     */
    public function fetchItem(array $item, string $context = 'VEL CNA feed'): ?FeedRecord
    {
        [$outcome, $record, $error] = $this->fetchOutcome($item);

        if ($outcome === 'failed') {
            Log::add($context . ': ' . (string) ($item['id'] ?? '') . ': ' . $error, Log::WARNING, 'com_vel');
        }

        return $record;
    }

    /**
     * One listed record, with what became of it - which the queue needs, because only a fetch
     * that *failed* must keep the id queued.
     *
     * @param array{id: string, url?: string} $item The item.
     *
     * @return array{0: string, 1: ?FeedRecord, 2: string}  [outcome, record, error]; outcome is
     *         `kept` (record worth keeping), `dropped` (not worth keeping, or not a record the
     *         mapper reads), `missing` (404 at the source) or `failed` (error: try again).
     *
     * @since 4.0.0
     */
    public function fetchOutcome(array $item): array
    {
        $id  = (string) ($item['id'] ?? '');
        $url = (string) ($item['url'] ?? '');

        try {
            $record = $url !== '' ? $this->cvelist->recordByUrl($url) : $this->cvelist->record($id);
        } catch (\Throwable $e) {
            return ['failed', null, $e->getMessage()];
        }

        if ($record === null) {
            return ['missing', null, ''];
        }

        $mapped = CveRecordMapper::map($record);

        if ($mapped === null) {
            return ['dropped', null, ''];
        }

        $this->classifier->classify($mapped);

        return $this->classifier->worthKeeping($mapped->classification) ? ['kept', $mapped, ''] : ['dropped', null, ''];
    }

    /**
     * @return int  The per-run fetch cap.
     *
     * @since 4.0.0
     */
    public function maxRecords(): int
    {
        return $this->maxRecords;
    }

    /**
     * Where a read of the delta log begins: the cursor {@see self::readStep()} advances. No I/O.
     *
     * The log only grows at the top, in `fetchTime` order, so a read that knows the newest entry
     * the last complete read saw (`$top`) reads exactly the entries after it: nothing twice,
     * nothing missed, however late an entry was published.
     *
     * @param DateTimeImmutable $from Window start: the last complete read's top entry, or the
     *                                look-back for a first read.
     * @param DateTimeImmutable $to   Window end (now).
     *
     * @return array  The cursor: `since` (read entries after this), `gap` (the window reaches past
     *                the log's 30 days), `top` (newest entry seen), `offset`, `bytes`,
     *                `send_etag`, `etag`, `last`, `restarts`, `slices`, `read`, `done`,
     *                `not_modified`.
     *
     * @since 4.0.0
     */
    public function readStart(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        // The log reaches back 30 days; a longer gap cannot be served by it.
        $floor = $to->modify('-30 days');
        $gap   = $from < $floor;
        $since = $gap ? $floor : $from;

        return [
            'since'        => $since->format('Y-m-d H:i:s'),
            'since_at'     => self::instant($since),
            'gap'          => $gap,
            'top'          => self::instant($from),
            'prev_top'     => self::instant($from),
            'offset'       => 0,
            'bytes'        => $this->cvelist->firstSliceBytes($since),
            'send_etag'    => $this->etag,
            'etag'         => null,
            'last'         => null,
            'restarts'     => 0,
            'slices'       => 0,
            'read'         => 0,
            'done'         => false,
            'not_modified' => false,
        ];
    }

    /**
     * Read the next slice of the delta log.
     *
     * The log is newest first and only ever grows at the top (and is trimmed at the bottom), so
     * a read walks down it slice by slice until it reaches an entry at or before `since`. When
     * the file changes between two slices - its ETag differs, the next slice does not start at an
     * entry, or it starts with an entry newer than the last one read - the read starts again from
     * the top; the ids found so far stay staged, and seeing them again is harmless.
     *
     * @param array $cursor From {@see self::readStart()} or the last call.
     *
     * @return array{0: array, 1: list<array{id: string, url: string, fetchTime: string}>}
     *         The advanced cursor (`done` once the window is covered) and the ids of this slice.
     *
     * @throws RuntimeException  When the log cannot be fetched or does not load: the read has
     *                           failed and nothing it found may be used.
     *
     * @since 4.0.0
     */
    public function readStep(array $cursor): array
    {
        if (++$cursor['slices'] > self::MAX_SLICES) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', 'more than ' . self::MAX_SLICES . ' slices'));
        }

        $first = $cursor['offset'] === 0;
        $slice = $this->cvelist->deltaSlice((int) $cursor['offset'], (int) $cursor['bytes'], $first ? $cursor['send_etag'] : null);

        $cursor['read'] += $slice['bytes'];

        if ($slice['not_modified']) {
            // Unchanged since the last complete read: nothing new to queue.
            $cursor['done']         = true;
            $cursor['not_modified'] = true;
            $cursor['etag']         = $cursor['send_etag'];

            return [$cursor, []];
        }

        if ($first || $slice['whole']) {
            $cursor['etag'] = $slice['etag'];
            $cursor['last'] = null;

            if ($slice['entries'] !== []) {
                // The top of the log as this read found it: where the next read starts.
                $cursor['top'] = max((float) $cursor['prev_top'], self::instant((string) $slice['entries'][0]['fetchTime']));
            }
        } elseif (!$slice['aligned'] || ($slice['etag'] !== null && $cursor['etag'] !== null && $slice['etag'] !== $cursor['etag'])) {
            return [$this->restart($cursor), []];
        }

        $since = (float) $cursor['since_at'];
        $items = [];

        foreach ($slice['entries'] as $entry) {
            // To the millisecond: two entries may share a second.
            $at = self::instant((string) $entry['fetchTime']);

            if ($cursor['last'] !== null && $at > $cursor['last']) {
                if (!$first && !$slice['whole']) {
                    // The top of the file moved down into this slice.
                    return [$this->restart($cursor), []];
                }

                throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', 'entries out of order at ' . $entry['fetchTime']));
            }

            if ($at <= $since) {
                $cursor['done'] = true;

                break;
            }

            $cursor['last'] = $at;

            foreach (['new', 'updated'] as $bucket) {
                foreach ((array) ($entry[$bucket] ?? []) as $item) {
                    $id = strtoupper((string) ($item['cveId'] ?? ''));

                    if (preg_match('/^CVE-\d{4}-\d{4,}$/', $id)) {
                        $items[] = ['id' => $id, 'url' => (string) ($item['githubLink'] ?? ''), 'fetchTime' => (string) $entry['fetchTime']];
                    }
                }
            }
        }

        if ($cursor['done']) {
            return [$cursor, $items];
        }

        if ($slice['next'] === null || $slice['whole']) {
            // The end of the log: it does not reach back further than this.
            $cursor['done'] = true;
        } elseif ($slice['bytes'] < $cursor['bytes']) {
            // The server had fewer bytes than asked for, yet the array did not end: the file is
            // cut off. That is a log that did not load.
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', 'the file ends before the list of entries does'));
        } elseif ($slice['next'] <= $cursor['offset']) {
            // Not even one entry fitted in the slice: ask for more of it.
            if ($cursor['bytes'] >= $this->cvelist->maxSliceBytes()) {
                throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', 'an entry larger than ' . $cursor['bytes'] . ' bytes'));
            }

            $cursor['bytes'] = min($this->cvelist->maxSliceBytes(), $cursor['bytes'] * 2);
        } else {
            $cursor['offset'] = $slice['next'];
            $cursor['bytes']  = $this->cvelist->sliceBytes();
        }

        return [$cursor, $items];
    }

    /**
     * A moment as Unix time with its fraction (fetchTime carries milliseconds).
     *
     * @param DateTimeImmutable|string $moment The moment, or an ISO 8601 stamp (UTC when it has no offset).
     *
     * @return float
     *
     * @since 4.0.0
     */
    public static function instant(DateTimeImmutable|string $moment): float
    {
        if (\is_string($moment)) {
            try {
                $moment = new DateTimeImmutable($moment, new \DateTimeZone('UTC'));
            } catch (\Throwable $e) {
                throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVELIST_DELTA_INVALID', 'bad fetchTime ' . $moment), 0, $e);
            }
        }

        return (float) $moment->format('U.u');
    }

    /**
     * @param float $instant Unix time with fraction.
     *
     * @return string  ISO 8601, UTC, to the millisecond - the form fetchTime has.
     *
     * @since 4.0.0
     */
    public static function iso(float $instant): string
    {
        return \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', $instant), new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Start a read again from the top of the log, which changed under it.
     *
     * @param array $cursor The cursor.
     *
     * @return array
     *
     * @throws RuntimeException  When it keeps changing.
     *
     * @since 4.0.0
     */
    private function restart(array $cursor): array
    {
        if (++$cursor['restarts'] > self::MAX_RESTARTS) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_CVELIST_DELTA_CHANGING'));
        }

        $cursor['offset']    = 0;
        $cursor['bytes']     = $this->cvelist->firstSliceBytes(new DateTimeImmutable($cursor['since'], new \DateTimeZone('UTC')));
        $cursor['send_etag'] = null;
        $cursor['etag']      = null;
        $cursor['last']      = null;
        $cursor['top']       = $cursor['prev_top'];

        return $cursor;
    }

    /**
     * The last part of a pass: the status the runner reports.
     *
     * @param array       $status The status so far (from {@see self::plan()} or {@see self::freshStatus()}).
     * @param string|null $oldest The `fetchTime` (SQL) of the oldest delta entry processed.
     * @param bool        $capped Whether the list was cut at the fetch cap.
     * @param string|null $cut    Why the pass stopped before the end of its list, if it did:
     *                            `time_budget`, `stopped` or `interrupted`.
     *
     * @return array{complete: bool, reason: string, etag: ?string, resume_at: ?string, fetched: int}
     *
     * @since 4.0.0
     */
    public static function close(array $status, ?string $oldest, bool $capped, ?string $cut = null): array
    {
        $status += self::freshStatus();

        if ($cut !== null || $capped) {
            $status['complete']  = false;
            $status['reason']    = $cut ?? 'fetch_cap';
            $status['resume_at'] = $oldest;

            return $status;
        }

        if (!$status['complete'] && $status['resume_at'] === null) {
            $status['resume_at'] = $oldest;
        }

        return $status;
    }

    /**
     * The backfill. Ids from the NVD (and CVE Services when configured), records from the
     * mirror; every id already in the queue is skipped, so the action is resumable.
     *
     * The one-request form of {@see self::seedIds()} + {@see self::fetchItem()} + {@see self::close()}.
     *
     * @param int[]    $knownIds Ids already in `#__vel_feed_items` for this source.
     * @param int      $years    How many CVE years back from the current one.
     * @param callable $deadline Budget check.
     *
     * @return iterable<FeedRecord>
     *
     * @since 4.0.0
     */
    public function seed(array $knownIds, int $years, callable $deadline): iterable
    {
        $plan         = $this->seedPlan($knownIds, $years, $deadline);
        $this->status = $plan['status'];

        foreach ($plan['items'] as $item) {
            if ($deadline()) {
                $this->status = self::close($this->status, null, false, 'time_budget');

                return;
            }

            $this->status['fetched']++;
            $record = $this->fetchItem($item, 'VEL seed');

            if ($record !== null) {
                yield $record;
            }
        }

        $this->status = self::close($this->status, null, $plan['capped']);
    }

    /**
     * The first part of the backfill in the shape of {@see self::plan()}: the ids to fetch as
     * items, cut at the per-run fetch cap.
     *
     * @param int[]    $knownIds Ids already in `#__vel_feed_items` for this source.
     * @param int      $years    How many CVE years back from the current one.
     * @param callable $deadline Budget check.
     *
     * @return array{items: list<array{id: string}>, capped: bool, status: array}
     *
     * @since 4.0.0
     */
    public function seedPlan(array $knownIds, int $years, callable $deadline): array
    {
        $list   = $this->seedIds($knownIds, $years, $deadline);
        $status = self::freshStatus($this->etag);

        if (!$list['complete']) {
            // The listing itself was cut short: whatever is fetched now, the seed is not done.
            $status['complete'] = false;
            $status['reason']   = 'time_budget';
        }

        return [
            'items'  => array_map(static fn (string $id): array => ['id' => $id], \array_slice($list['ids'], 0, $this->maxRecords)),
            'capped' => \count($list['ids']) > $this->maxRecords,
            'status' => $status,
        ];
    }

    /**
     * The first part of the backfill: every id to fetch, newest first.
     *
     * @param int[]    $knownIds Ids already in `#__vel_feed_items` for this source.
     * @param int      $years    How many CVE years back from the current one.
     * @param callable $deadline Budget check.
     *
     * @return array{ids: list<string>, complete: bool}  `complete` is false when the budget cut
     *         the listing short, so the seed must not call itself complete.
     *
     * @since 4.0.0
     */
    public function seedIds(array $knownIds, int $years, callable $deadline): array
    {
        $complete = true;
        $ids      = [];
        $known    = array_fill_keys(array_map('strtoupper', $knownIds), true);
        $minYear  = (int) gmdate('Y') - max(0, $years - 1);

        $collect = static function (array $page) use (&$ids, $known, $minYear): void {
            foreach ((array) ($page['vulnerabilities'] ?? []) as $item) {
                $id = strtoupper((string) ($item['cve']['id'] ?? ''));

                if (preg_match('/^CVE-(\d{4})-\d{4,}$/', $id, $m) && (int) $m[1] >= $minYear && !isset($known[$id])) {
                    $ids[$id] = true;
                }
            }
        };

        if ($this->nvd !== null) {
            try {
                // Ours: one request covers the whole CNA (558 records NVD-wide).
                $collect($this->nvd->bySource(RecordClassifier::NVD_SOURCE_IDENTIFIER, null, null, 0, NvdClient::MAX_PER_PAGE));
            } catch (\Throwable $e) {
                Log::add('VEL seed: NVD source query failed: ' . $e->getMessage(), Log::WARNING, 'com_vel');
            }

            try {
                // Everybody else's Joomla records: the keyword corpus, 1,633 records in one page.
                $start = 0;

                do {
                    $page  = $this->nvd->keyword('Joomla', $start, NvdClient::MAX_PER_PAGE);
                    $total = (int) ($page['totalResults'] ?? 0);
                    $count = \count((array) ($page['vulnerabilities'] ?? []));
                    $collect($page);
                    $start += max(1, $count);
                } while ($count > 0 && $start < $total && !$deadline());

                if ($count > 0 && $start < $total) {
                    $complete = false;
                }
            } catch (\Throwable $e) {
                // Should the un-windowed query ever be refused, walk 120-day publication
                // windows instead - slower, same result.
                Log::add('VEL seed: NVD keyword query failed (' . $e->getMessage() . '); walking publication windows.', Log::INFO, 'com_vel');

                $cursor = new DateTimeImmutable($minYear . '-01-01', new \DateTimeZone('UTC'));
                $now    = new DateTimeImmutable('now', new \DateTimeZone('UTC'));

                while ($cursor < $now && !$deadline()) {
                    $end = min($now, $cursor->modify('+119 days'));

                    try {
                        $collect($this->nvd->search('Joomla', $cursor, $end, 0, NvdClient::MAX_PER_PAGE, 'pub'));
                    } catch (\Throwable $inner) {
                        Log::add('VEL seed: window ' . $cursor->format('Y-m-d') . ': ' . $inner->getMessage(), Log::WARNING, 'com_vel');
                    }

                    $cursor = $end;
                }

                if ($cursor < $now) {
                    $complete = false;
                }
            }
        }

        if ($this->services !== null && $this->services->configured()) {
            for ($year = (int) gmdate('Y'); $year >= $minYear; $year--) {
                $page = 1;

                do {
                    try {
                        $list = $this->services->listOwnIds(null, $year, $page);
                    } catch (\Throwable $e) {
                        Log::add('VEL seed: CVE Services id list failed: ' . $e->getMessage(), Log::WARNING, 'com_vel');

                        // A failure, not the budget: the year is given up, as before.
                        $page = 0;

                        break;
                    }

                    foreach ((array) ($list['cve_ids'] ?? []) as $entry) {
                        $id = strtoupper((string) ($entry['cve_id'] ?? ''));

                        if (preg_match('/^CVE-\d{4}-\d{4,}$/', $id) && !isset($known[$id])) {
                            $ids[$id] = true;
                        }
                    }

                    $next = $list['nextPage'] ?? null;
                    $page = \is_int($next) && $next > $page ? $next : 0;
                } while ($page > 0 && !$deadline());

                if ($page > 0) {
                    $complete = false;
                }
            }
        }

        $ids = array_keys($ids);
        rsort($ids);

        return ['ids' => $ids, 'complete' => $complete];
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
        $record = $this->cvelist->record($cveId);

        if ($record === null && $this->services !== null) {
            try {
                $record = $this->services->getRecord($cveId);
            } catch (\Throwable $e) {
                $record = null;
            }
        }

        if ($record === null) {
            return null;
        }

        $mapped = CveRecordMapper::map($record);

        if ($mapped !== null) {
            $this->classifier->classify($mapped);
        }

        return $mapped;
    }

    /**
     * @param string $iso A delta `fetchTime`.
     *
     * @return string|null  SQL datetime.
     *
     * @since 4.0.0
     */
    private function sqlStamp(string $iso): ?string
    {
        return CveRecordMapper::sql($iso);
    }
}
