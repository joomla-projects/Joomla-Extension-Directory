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
use DateTimeZone;
use Jed\Component\Vel\Administrator\Cve\CveServicesClient;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\FeedClassification;
use Jed\Component\Vel\Administrator\Enum\FeedDecision;
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

/**
 * One pass of one feed (feeds PRD §3, §6): a run row, a window, the records, the queue.
 *
 * Idempotent by `(source, external_id)` and the content hash. `partial` is reported with a
 * message that says which of its meanings applies - the per-run fetch cap, an under-sized delta
 * range, a gap older than the log's retention, or the time budget - and the window end is left
 * where the next run has to resume. Nothing here publishes; the most a run does is create a
 * *draft* for a Joomla CNA record when the option allows it.
 *
 * A pass is done in steps, so that it can be driven from the browser and shown as it goes:
 *
 *  - {@see self::start()} writes the run row with its plan in `progress` and does no I/O;
 *  - each {@see self::step()} does a few seconds of work - one outbound request at a time -
 *    and saves where it got to, so a step is an ordinary short web request and nothing has to
 *    survive in memory between two of them;
 *  - the step that reaches the end closes the row; {@see self::stop()} closes it early.
 *
 * Phases: the CVE feed `read`s the delta log into its queue ({@see FeedBacklog}) then fetches
 * `records` from the queue; the seed `collect`s ids then fetches `records`; the NVD feed
 * `discover`s page by page then `enrich`es.
 *
 * The CVE feed loses nothing when a run is cut short. Its read of the log stages the ids it
 * finds and commits them - and moves the feed's position (`cursor`: ETag + newest entry read)
 * - only once the whole window has loaded and validated; a log that fails to load, or a read
 * that is stopped, changes nothing. Records leave the queue only once handled, so the fetch
 * cap, Stop, a timeout or a closed page just leave work for the next run.
 * A step holds a lock (`heartbeat`) so two requests never work on one run at once, and a run
 * nobody has stepped for a while is closed as interrupted the next time that feed starts.
 *
 * {@see self::run()} and {@see self::seed()} do a whole pass in one call, for the scheduler.
 *
 * @since 4.0.0
 */
final class FeedRunner
{
    public const MODE_DELTA = 'delta';
    public const MODE_SEED  = 'seed';

    /**
     * Seconds of work per step driven from the browser. A step stops at the first check after
     * this, so it lasts this plus whatever request is in flight.
     *
     * @since 4.0.0
     */
    public const STEP_SECONDS = 10;

    /**
     * HTTP timeout for steps driven from the browser. With STEP_SECONDS and the NVD's pause
     * between requests, one step stays under a 60-second gateway timeout.
     *
     * @since 4.0.0
     */
    public const STEP_HTTP_TIMEOUT = 30;

    /**
     * A step lock older than this was left by a request that died. Long steps (the scheduler's)
     * refresh it every {@see self::BEAT_SECONDS}.
     *
     * @since 4.0.0
     */
    private const LOCK_SECONDS = 300;

    /**
     * @since 4.0.0
     */
    private const BEAT_SECONDS = 60;

    /**
     * A running run nobody has stepped for this long was abandoned (the page was closed).
     *
     * @since 4.0.0
     */
    private const ABANDON_SECONDS = 900;

    /**
     * Per step, how many stored records are reported back for the page's activity log.
     *
     * @since 4.0.0
     */
    private const MAX_EVENTS = 50;

    /**
     * Records stored by the current step: `{id, outcome, title}`.
     *
     * @var array<int, array{id: string, outcome: string, title: string}>
     *
     * @since 4.0.0
     */
    private array $events = [];

    /**
     * The outbound requests of the current step ({@see FeedTrace}).
     *
     * @var array{requests: list<array>, dropped: int}
     *
     * @since 4.0.0
     */
    private array $requests = ['requests' => [], 'dropped' => 0];

    /**
     * The NVD client of the current step, whose throttle is carried to the next one.
     *
     * @since 4.0.0
     */
    private ?NvdClient $nvd = null;

    /**
     * @since 4.0.0
     */
    private bool $autoCreate = true;

    /**
     * @since 4.0.0
     */
    private ?FeedBacklog $backlog = null;

    /**
     * @param DatabaseInterface $db          The database driver.
     * @param VelService        $service     The lifecycle service.
     * @param int               $httpTimeout Seconds per outbound request: the default suits the
     *                                       scheduler; steps driven from the browser pass
     *                                       {@see self::STEP_HTTP_TIMEOUT}.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly VelService $service,
        private readonly int $httpTimeout = 60
    ) {
        // Run messages and notes are written in the site's language; the scheduler runs this
        // outside the administrator, where the component's strings are not loaded by default.
        Factory::getApplication()->getLanguage()->load('com_vel', JPATH_ADMINISTRATOR . '/components/com_vel');
    }

    /**
     * The classifier, from the options.
     *
     * @return RecordClassifier
     *
     * @since 4.0.0
     */
    public static function classifier(): RecordClassifier
    {
        $params = ComponentHelper::getParams('com_vel');

        return new RecordClassifier(
            (string) $params->get('cna_short_name', 'Joomla'),
            (string) $params->get('cna_org_id', ''),
            (int) $params->get('feed_import_other_cnas', 1) === 1
        );
    }

    /**
     * The configured source for a feed.
     *
     * @param FeedSource     $source  Which feed.
     * @param string|null    $etag    The CVE feed's stored ETag.
     * @param int            $timeout Seconds per outbound request.
     * @param NvdClient|null $nvd     An NVD client to use (so its throttle can be carried over).
     *
     * @return FeedSourceInterface
     *
     * @since 4.0.0
     */
    public static function source(FeedSource $source, ?string $etag = null, int $timeout = 60, ?NvdClient $nvd = null): FeedSourceInterface
    {
        $params = ComponentHelper::getParams('com_vel');
        $http   = HttpFactory::getHttp(['timeout' => $timeout]);
        $nvd  ??= new NvdClient($http, (string) $params->get('nvd_api_key', ''), $timeout);

        return match ($source) {
            FeedSource::CNA => new CnaFeedSource(
                new CvelistClient(
                    $http,
                    max(64, (int) $params->get('cna_range_max_kb', 2048)) * 1024,
                    max(1, (int) $params->get('cna_full_fetch_max_mb', 64)) * 1024 * 1024,
                    $timeout
                ),
                self::classifier(),
                max(10, (int) $params->get('cna_max_records_per_run', 400)),
                $etag,
                $nvd,
                CveServicesClient::fromOptions()
            ),
            FeedSource::NVD => new NvdFeedSource($nvd, self::classifier()),
        };
    }

    /**
     * A whole delta pass in one call (the scheduler's way).
     *
     * @param FeedSource $source        Which feed.
     * @param int        $budgetSeconds How long the run may take.
     *
     * @return object  The run row.
     *
     * @since 4.0.0
     */
    public function run(FeedSource $source, int $budgetSeconds = 120): object
    {
        return $this->runToEnd($this->start($source), $budgetSeconds);
    }

    /**
     * The one-off backfill (feeds PRD §3.5) in one call. Resumable: every id already queued is
     * skipped.
     *
     * @param int $budgetSeconds How long it may take.
     *
     * @return object  The run row.
     *
     * @since 4.0.0
     */
    public function seed(int $budgetSeconds = 120): object
    {
        return $this->runToEnd($this->start(FeedSource::CNA, self::MODE_SEED), $budgetSeconds);
    }

    /**
     * Begin a pass: close whatever run of this feed was abandoned, then write the new run row
     * with its plan. No outbound request is made here.
     *
     * A run of this feed that is still going is returned instead of a second one, so pressing
     * the button twice (or reloading the progress page) attaches to the same run.
     *
     * @param FeedSource $source Which feed.
     * @param string     $mode   {@see self::MODE_DELTA} or {@see self::MODE_SEED} (CVE feed only).
     *
     * @return object  The run row. Status `running` unless the feed is disabled, in which case
     *                 the row is an unsaved `ok` with a message saying so.
     *
     * @throws RuntimeException  When another kind of run of this feed is going, or the schema
     *                           is out of date.
     *
     * @since 4.0.0
     */
    public function start(FeedSource $source, string $mode = self::MODE_DELTA): object
    {
        if ($mode !== self::MODE_DELTA && !($mode === self::MODE_SEED && $source === FeedSource::CNA)) {
            throw new \InvalidArgumentException('Unknown feed run mode ' . $mode . ' for ' . $source->value . '.');
        }

        // Without the columns the progress would be dropped silently by insertObject() and
        // every step would find a run it cannot continue.
        $columns = $this->db->getTableColumns('#__vel_feed_runs');

        if (!\array_key_exists('progress', $columns) || !\array_key_exists('heartbeat', $columns)
            || !\in_array($this->db->replacePrefix('#__vel_feed_backlog'), $this->db->getTableList(), true)) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_FEED_RUN_SCHEMA'));
        }

        $this->closeAbandoned($source);

        $active = $this->activeRun($source);

        if ($active !== null) {
            return $this->attach($active, $mode);
        }

        $params = ComponentHelper::getParams('com_vel');
        $now    = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $run    = $this->newRun($source, $now);

        if ($mode === self::MODE_SEED) {
            $run->window_from = null;
            $run->message     = Text::_('COM_VEL_FEED_RUN_MSG_SEED_RUNNING');
            $progress         = ['phase' => 'collect'];
        } else {
            $enabled = $source === FeedSource::CNA ? (int) $params->get('cna_enabled', 1) === 1 : (int) $params->get('nvd_enabled', 1) === 1;

            if (!$enabled) {
                $run->status   = 'ok';
                $run->finished = $run->started;
                $run->message  = Text::_('COM_VEL_FEED_RUN_MSG_DISABLED');

                return $run;
            }

            $last     = $this->lastRun($source);
            $position = self::logPosition((string) ($last->cursor ?? ''));

            // The CVE feed resumes exactly after the newest log entry its last complete read saw;
            // otherwise (first run, NVD, a run from before the queue) from the window end.
            $from = $source === FeedSource::CNA && $position['top'] !== null
                ? new DateTimeImmutable($position['top'], new DateTimeZone('UTC'))
                : $this->windowStart($source, $now, $last);

            $run->window_from = $from->format('Y-m-d H:i:s');
            $run->cursor      = (string) ($last->cursor ?? '');
            $progress         = $source === FeedSource::CNA
                ? [
                    // Read the log into the queue first; fetch records from the queue after.
                    'phase'  => 'read',
                    'read'   => self::source(FeedSource::CNA, $position['etag'], $this->httpTimeout)->readStart($from, $now),
                    'staged' => 0,
                ]
                : ['phase' => 'discover', 'cursor' => $run->window_from, 'start' => 0, 'read' => 0, 'total' => null];
        }

        $progress += ['mode' => $mode, 'to' => $now->format('Y-m-d H:i:s'), 'touched' => $this->sqlTime()];

        $run->progress = json_encode($progress, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->db->insertObject('#__vel_feed_runs', $run, 'id');

        // Two presses close together can both get this far; the older run wins.
        $first = $this->activeRun($source);

        if ($first !== null && (int) $first->id !== (int) $run->id) {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->delete($this->db->quoteName('#__vel_feed_runs'))
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':id', $run->id, ParameterType::INTEGER)
            )->execute();

            return $this->attach($first, $mode);
        }

        return $this->load((int) $run->id) ?? $run;
    }

    /**
     * Do up to `$budgetSeconds` of work on a run and save where it got to.
     *
     * @param int $runId         The run.
     * @param int $budgetSeconds Seconds of work; checked between requests.
     *
     * @return array  See {@see self::report()}.
     *
     * @throws RuntimeException  When there is no such run.
     *
     * @since 4.0.0
     */
    public function step(int $runId, int $budgetSeconds = self::STEP_SECONDS): array
    {
        $run = $this->mustLoad($runId);

        if ((string) $run->status !== 'running') {
            return $this->report($run);
        }

        if (!$this->claim($runId)) {
            // Another request is working on it (a second tab, or a step the gateway gave up on
            // that PHP is still finishing). The page waits and asks again.
            return $this->report($run, $this->decode($run), true);
        }

        // Re-read: the previous step may have saved between the first read and the claim.
        $run          = $this->mustLoad($runId);
        $progress     = $this->decode($run);
        $this->events = [];

        if ($progress === null) {
            $this->fail($run, Text::_('COM_VEL_FEED_RUN_MSG_INTERRUPTED'), false);
            $this->save($run, null);

            return $this->report($run);
        }

        $start    = microtime(true);
        $beat     = $start;
        $deadline = static fn (): bool => (microtime(true) - $start) > $budgetSeconds;

        // Only this step's requests go to the page.
        FeedTrace::take();

        try {
            $feed = $this->feedFor($run, $progress);

            // At least one unit per step, however small the budget.
            while ($progress['phase'] !== 'done') {
                $this->advance($run, $progress, $feed, $deadline);

                if (microtime(true) - $beat > self::BEAT_SECONDS) {
                    $this->beat($runId);
                    $beat = microtime(true);
                }

                if ($deadline()) {
                    break;
                }
            }

            if ($progress['phase'] === 'done') {
                $this->conclude($run, $progress);
            }
        } catch (Throwable $e) {
            if ($this->readsTheLog($run, $progress) && $progress['phase'] === 'read') {
                // The log did not load: nothing it found is used, and the window and ETag stay
                // where the last complete read left them (a failed run is never the last run).
                $this->backlog()->discard((int) $run->id);
                $this->fail($run, Text::sprintf('COM_VEL_FEED_RUN_MSG_READ_FAILED', $e->getMessage()), false);
            } else {
                $this->fail($run, $e->getMessage(), ($progress['mode'] ?? '') === self::MODE_SEED);
            }
        }

        if ($this->nvd !== null) {
            $progress['nvd_last'] = $this->nvd->lastRequestAt();
        }

        $this->requests = FeedTrace::take();

        $this->save($run, (string) $run->status === 'running' ? $progress : null);

        return $this->report($run, $progress);
    }

    /**
     * Close a run before the end of its plan. The window is left where the next run has to
     * resume, exactly as when the time budget runs out.
     *
     * @param int    $runId  The run.
     * @param string $reason `stopped` (by a person), `time_budget` or `interrupted`.
     *
     * @return array  See {@see self::report()}; `busy` when a step is still working on it.
     *
     * @throws RuntimeException  When there is no such run.
     *
     * @since 4.0.0
     */
    public function stop(int $runId, string $reason = 'stopped'): array
    {
        $run = $this->mustLoad($runId);

        if ((string) $run->status !== 'running') {
            return $this->report($run);
        }

        if (!$this->claim($runId)) {
            return $this->report($run, $this->decode($run), true);
        }

        $run      = $this->mustLoad($runId);
        $progress = $this->decode($run);

        if ($progress === null) {
            $this->fail($run, Text::_('COM_VEL_FEED_RUN_MSG_INTERRUPTED'), false);
        } else {
            $this->conclude($run, $progress, $reason);
        }

        $this->save($run, null);

        return $this->report($run);
    }

    /**
     * Where a run is, for the progress page.
     *
     * @param object     $run      The run row.
     * @param array|null $progress Its decoded progress, when the caller has it.
     * @param bool       $busy     Whether a step was refused because another one holds the run.
     *
     * @return array{id: int, source: string, label: string, status: string, statusLabel: string,
     *               done: bool, busy: bool, phase: string, activity: string, current: ?int,
     *               total: ?int, seen: int, new: int, updated: int, enriched: int,
     *               message: string, window: string, elapsed: int, events: array,
     *               requests: array, requestsDropped: int}
     *
     * @since 4.0.0
     */
    public function report(object $run, ?array $progress = null, bool $busy = false): array
    {
        $status = (string) $run->status;
        $done   = $status !== 'running';

        if (!$done) {
            $progress ??= $this->decode($run);
        }

        $phase   = $done ? 'done' : (string) ($progress['phase'] ?? '');
        $current = null;
        $total   = null;

        if ($phase === 'read') {
            // How far back the read has got, as a share of the window.
            $to      = strtotime((string) $progress['to'] . ' UTC');
            $since   = strtotime((string) $progress['read']['since'] . ' UTC');
            $last    = $progress['read']['last'] ?? null;
            $total   = max(1, $to - $since);
            $current = $last === null ? 0 : min($total, max(0, $to - (int) $last));
        } elseif ($phase === 'records') {
            $current = (int) ($progress['index'] ?? 0);
            $total   = \count((array) ($progress['items'] ?? []));
        } elseif ($phase === 'discover' && ($progress['total'] ?? null) !== null) {
            $current = (int) ($progress['read'] ?? 0);
            $total   = (int) $progress['total'];
        } elseif ($phase === 'enrich' && isset($progress['enrich'])) {
            $current = (int) ($progress['eindex'] ?? 0);
            $total   = \count((array) $progress['enrich']);
        }

        $activity = match ($phase) {
            'plan'     => Text::_('COM_VEL_FEEDRUN_PHASE_PLAN'),
            'read'     => ($progress['read']['last'] ?? null) === null
                ? Text::_('COM_VEL_FEEDRUN_PHASE_PLAN')
                : Text::sprintf('COM_VEL_FEEDRUN_PHASE_READ', gmdate('Y-m-d H:i', (int) $progress['read']['last']), number_format(((int) $progress['read']['read']) / 1048576, 1), (int) $progress['staged']),
            'collect'  => Text::_('COM_VEL_FEEDRUN_PHASE_COLLECT'),
            'records'  => isset($progress['read'])
                ? Text::sprintf('COM_VEL_FEEDRUN_PHASE_QUEUE', $current, $total, (int) ($progress['queued'] ?? $total))
                : Text::sprintf('COM_VEL_FEEDRUN_PHASE_RECORDS', $current, $total),
            'discover' => $total === null ? Text::_('COM_VEL_FEEDRUN_PHASE_DISCOVER_START') : Text::sprintf('COM_VEL_FEEDRUN_PHASE_DISCOVER', $current, $total),
            'enrich'   => $total === null ? Text::_('COM_VEL_FEEDRUN_PHASE_ENRICH_START') : Text::sprintf('COM_VEL_FEEDRUN_PHASE_ENRICH', $current, $total),
            default    => Text::_('COM_VEL_FEEDRUN_PHASE_DONE'),
        };

        $started = strtotime((string) $run->started . ' UTC');

        return [
            'id'          => (int) ($run->id ?? 0),
            'source'      => (string) $run->source,
            'label'       => Text::_('COM_VEL_FEED_SOURCE_' . strtoupper((string) $run->source)),
            'status'      => $status,
            'statusLabel' => Text::_('COM_VEL_FEED_RUN_STATUS_' . strtoupper($status)),
            'done'        => $done,
            'busy'        => $busy,
            'phase'       => $phase,
            'activity'    => $activity,
            'current'     => $current,
            'total'       => $total,
            'seen'        => (int) $run->items_seen,
            'new'         => (int) $run->items_new,
            'updated'     => (int) $run->items_updated,
            'enriched'    => (int) ($progress['enriched'] ?? 0),
            'message'     => (string) $run->message,
            'window'      => $run->window_from ? $run->window_from . ' → ' . ($run->window_to ?? '') : '',
            'elapsed'     => $started !== false ? max(0, time() - $started) : 0,
            'events'      => $this->events,
            // What this step asked of cvelistV5 / the NVD: method, url, range, status, bytes, ms, error.
            'requests'    => $this->requests['requests'],
            'requestsDropped' => $this->requests['dropped'],
        ];
    }

    /**
     * @param int $runId The run.
     *
     * @return object|null  The row.
     *
     * @since 4.0.0
     */
    public function load(int $runId): ?object
    {
        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_feed_runs'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $runId, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * @param int $runId The run.
     *
     * @return object
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function mustLoad(int $runId): object
    {
        return $this->load($runId) ?? throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_FEED_RUN_NOT_FOUND', $runId), 404);
    }

    /**
     * A started run carried through to its end within a budget: the scheduler's way.
     *
     * @param object $run           The run from {@see self::start()}.
     * @param int    $budgetSeconds Seconds.
     *
     * @return object  The run row.
     *
     * @since 4.0.0
     */
    private function runToEnd(object $run, int $budgetSeconds): object
    {
        if ((string) $run->status !== 'running' || empty($run->id)) {
            return $run;
        }

        $report = $this->step((int) $run->id, $budgetSeconds);

        if (!$report['done'] && !$report['busy']) {
            $this->stop((int) $run->id, 'time_budget');
        }

        return $this->load((int) $run->id) ?? $run;
    }

    /**
     * The run of this feed that is going, if one is.
     *
     * @param FeedSource $source Which feed.
     *
     * @return object|null
     *
     * @since 4.0.0
     */
    private function activeRun(FeedSource $source): ?object
    {
        $value = $source->value;

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_feed_runs'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('running'))
                ->bind(':source', $value)
                ->order($this->db->quoteName('id') . ' ASC'),
            0,
            1
        )->loadObject() ?: null;
    }

    /**
     * Join a run that is going, if it is the kind asked for.
     *
     * @param object $active The run.
     * @param string $mode   The mode asked for.
     *
     * @return object
     *
     * @throws RuntimeException  When it is the other kind (a seed while a delta run was asked for).
     *
     * @since 4.0.0
     */
    private function attach(object $active, string $mode): object
    {
        if ((string) ($this->decode($active)['mode'] ?? '') !== $mode) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_FEED_RUN_ACTIVE', Text::_('COM_VEL_FEED_SOURCE_' . strtoupper((string) $active->source))));
        }

        return $active;
    }

    /**
     * Close the runs of a feed that nobody is stepping any more: the page was closed, or the
     * run was started by the old one-request runner and killed by a gateway timeout.
     *
     * @param FeedSource $source Which feed.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function closeAbandoned(FeedSource $source): void
    {
        $value = $source->value;
        $ids   = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__vel_feed_runs'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('running'))
                ->bind(':source', $value)
        )->loadColumn() ?: [];

        $cutoff = $this->sqlTime(-self::ABANDON_SECONDS);

        foreach ($ids as $id) {
            $run      = $this->load((int) $id);
            $progress = $run !== null ? $this->decode($run) : null;

            if ($run === null || ($progress !== null && (string) ($progress['touched'] ?? '') >= $cutoff)) {
                continue;
            }

            // Never close one a live step is holding.
            if (!$this->claim((int) $id)) {
                continue;
            }

            $run      = $this->mustLoad((int) $id);
            $progress = $this->decode($run);

            if ($progress === null) {
                $this->fail($run, Text::_('COM_VEL_FEED_RUN_MSG_INTERRUPTED'), false);
            } else {
                $this->conclude($run, $progress, 'interrupted');
            }

            $this->save($run, null);
        }
    }

    /**
     * Take the step lock: set `heartbeat` unless a live step holds it.
     *
     * @param int $runId The run.
     *
     * @return bool  Whether this request now holds the run.
     *
     * @since 4.0.0
     */
    private function claim(int $runId): bool
    {
        $now   = $this->sqlTime();
        $stale = $this->sqlTime(-self::LOCK_SECONDS);

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_feed_runs'))
                ->set($this->db->quoteName('heartbeat') . ' = :now')
                ->where($this->db->quoteName('id') . ' = :id')
                ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('running'))
                ->where('(' . $this->db->quoteName('heartbeat') . ' IS NULL OR ' . $this->db->quoteName('heartbeat') . ' < :stale)')
                ->bind(':now', $now)
                ->bind(':id', $runId, ParameterType::INTEGER)
                ->bind(':stale', $stale)
        )->execute();

        return $this->db->getAffectedRows() > 0;
    }

    /**
     * Refresh the step lock during a long step.
     *
     * @param int $runId The run.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function beat(int $runId): void
    {
        $now = $this->sqlTime();

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_feed_runs'))
                ->set($this->db->quoteName('heartbeat') . ' = :now')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':now', $now)
                ->bind(':id', $runId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Write the run row back and release the lock.
     *
     * @param object     $run      The row.
     * @param array|null $progress The progress to keep, or null once the run is closed.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function save(object $run, ?array $progress): void
    {
        if ($progress !== null) {
            $progress['touched'] = $this->sqlTime();
        }

        $run->progress  = $progress === null
            ? null
            : json_encode($progress, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        $run->heartbeat = null;

        $this->db->updateObject('#__vel_feed_runs', $run, 'id', true);
    }

    /**
     * @param object $run The row.
     *
     * @return array|null  The progress, or null when there is none (a run of the old runner).
     *
     * @since 4.0.0
     */
    private function decode(object $run): ?array
    {
        $progress = json_decode((string) ($run->progress ?? ''), true);

        return \is_array($progress) && isset($progress['phase'], $progress['mode']) ? $progress : null;
    }

    /**
     * The source for a step, with the NVD throttle carried over from the last one.
     *
     * @param object $run      The row.
     * @param array  $progress The progress.
     *
     * @return FeedSourceInterface
     *
     * @since 4.0.0
     */
    private function feedFor(object $run, array $progress): FeedSourceInterface
    {
        $params = ComponentHelper::getParams('com_vel');

        $this->autoCreate = (int) $params->get('feed_auto_create', 1) === 1;
        $this->nvd        = new NvdClient(HttpFactory::getHttp(['timeout' => $this->httpTimeout]), (string) $params->get('nvd_api_key', ''), $this->httpTimeout);
        $this->nvd->resumeThrottle((float) ($progress['nvd_last'] ?? 0));

        $etag = $progress['mode'] === self::MODE_DELTA ? self::logPosition((string) $run->cursor)['etag'] : null;

        return self::source(FeedSource::from((string) $run->source), $etag, $this->httpTimeout, $this->nvd);
    }

    /**
     * One unit of work: at most one outbound request and what follows from it.
     *
     * @param object              $run      The row (its tallies are updated).
     * @param array               $progress The progress (advanced).
     * @param FeedSourceInterface $feed     The source.
     * @param callable            $deadline Budget check, for the units that page internally.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function advance(object $run, array &$progress, FeedSourceInterface $feed, callable $deadline): void
    {
        $phase = (string) $progress['phase'];

        if ($feed instanceof CnaFeedSource && $phase === 'read') {
            $this->nextSlice($run, $progress, $feed);

            return;
        }

        if ($feed instanceof CnaFeedSource && $phase === 'records' && isset($progress['read'])) {
            $this->nextQueued($run, $progress, $feed);

            return;
        }

        // A run started before the queue existed: one plan, processed as it was.
        if ($feed instanceof CnaFeedSource && $phase === 'plan') {
            $this->planned($progress, $feed->plan(
                new DateTimeImmutable((string) $run->window_from, new DateTimeZone('UTC')),
                new DateTimeImmutable((string) $progress['to'], new DateTimeZone('UTC'))
            ));

            return;
        }

        if ($feed instanceof CnaFeedSource && $phase === 'collect') {
            $known = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('external_id'))
                    ->from($this->db->quoteName('#__vel_feed_items'))
                    ->where($this->db->quoteName('source') . ' = ' . $this->db->quote(FeedSource::CNA->value))
            )->loadColumn() ?: [];

            $years = max(1, (int) ComponentHelper::getParams('com_vel')->get('cna_seed_years', 2));

            $this->planned($progress, $feed->seedPlan($known, $years, $deadline));

            return;
        }

        if ($feed instanceof CnaFeedSource && $phase === 'records') {
            $this->nextRecord($run, $progress, $feed);

            return;
        }

        if ($feed instanceof NvdFeedSource && $phase === 'discover') {
            $this->nextPage($run, $progress, $feed);

            return;
        }

        if ($phase === 'enrich') {
            $this->nextEnrichment($progress, $feed);

            return;
        }

        throw new \LogicException('Feed run ' . (int) $run->id . ': unknown phase "' . $phase . '".');
    }

    /**
     * Read the next slice of the delta log and stage the ids it lists. When the read has covered
     * the window, commit them to the queue and take this run's batch from it.
     *
     * @param object        $run      The row.
     * @param array         $progress The progress.
     * @param CnaFeedSource $feed     The source.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function nextSlice(object $run, array &$progress, CnaFeedSource $feed): void
    {
        [$progress['read'], $items] = $feed->readStep($progress['read']);

        $progress['staged'] += $this->backlog()->stage(FeedSource::CNA, (int) $run->id, $items);

        if (!$progress['read']['done']) {
            return;
        }

        // Loaded to the end: now, and only now, the ids join the queue.
        $this->backlog()->commit((int) $run->id);

        $progress['items']  = $this->backlog()->batch(FeedSource::CNA, $feed->maxRecords());
        $progress['queued'] = $this->backlog()->remaining(FeedSource::CNA);
        $progress['index']  = 0;
        $progress['status'] = CnaFeedSource::freshStatus();
        $progress['phase']  = $progress['items'] === [] ? 'done' : 'records';
    }

    /**
     * Fetch and store the next record of this run's batch from the queue. The id leaves the
     * queue unless the fetch failed.
     *
     * @param object        $run      The row.
     * @param array         $progress The progress.
     * @param CnaFeedSource $feed     The source.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function nextQueued(object $run, array &$progress, CnaFeedSource $feed): void
    {
        $item = $progress['items'][$progress['index']] ?? null;

        if (!\is_array($item)) {
            $progress['phase'] = 'done';

            return;
        }

        $progress['index']++;
        $progress['status']['fetched']++;

        [$outcome, $record, $error] = $feed->fetchOutcome($item);

        if ($outcome === 'failed') {
            $attempts = $this->backlog()->failed(FeedSource::CNA, (string) $item['id'], $error);

            Log::add(
                'VEL CNA feed: ' . $item['id'] . ': ' . $error . ($attempts >= FeedBacklog::MAX_ATTEMPTS ? ' - set aside after ' . $attempts . ' attempts' : ' - kept queued'),
                Log::WARNING,
                'com_vel'
            );
        } else {
            if ($record !== null) {
                // Stored first: should this fail, the id is still queued for the next run.
                $this->keep($run, $record);
            }

            $this->backlog()->done(FeedSource::CNA, (string) $item['id']);
        }

        if ($progress['index'] >= \count($progress['items'])) {
            $progress['phase'] = 'done';
        }
    }

    /**
     * @param object     $run      The row.
     * @param array|null $progress The progress.
     *
     * @return bool  Whether this is a CVE feed run that reads the log into the queue.
     *
     * @since 4.0.0
     */
    private function readsTheLog(object $run, ?array $progress): bool
    {
        return (string) $run->source === FeedSource::CNA->value && ($progress['mode'] ?? '') === self::MODE_DELTA && isset($progress['read']);
    }

    /**
     * The CVE feed's position in the delta log, as kept in `#__vel_feed_runs.cursor`: the ETag
     * of the last complete read and the `fetchTime` of the newest entry it saw, as JSON. A plain
     * string is an ETag written before the queue existed.
     *
     * Read: `logPosition($cursor)` returns `['etag' => ?string, 'top' => ?string]`.
     * Write: `logPosition(null, $etag, $top)` returns the column value.
     *
     * @param string|null $cursor The column value to read, or null to write one.
     * @param string|null $etag   To write.
     * @param string|null $top    To write (SQL datetime).
     *
     * @return array|string
     *
     * @since 4.0.0
     */
    public static function logPosition(?string $cursor, ?string $etag = null, ?string $top = null): array|string
    {
        if ($cursor === null) {
            $value = json_encode(['etag' => $etag, 'top' => $top], JSON_UNESCAPED_SLASHES);

            // The column holds 255; an ETag that long is not one worth keeping.
            return \strlen($value) <= 255 ? $value : json_encode(['etag' => null, 'top' => $top]);
        }

        if (str_starts_with($cursor, '{')) {
            $decoded = json_decode($cursor, true);

            if (\is_array($decoded)) {
                return [
                    'etag' => isset($decoded['etag']) && $decoded['etag'] !== '' ? (string) $decoded['etag'] : null,
                    'top'  => isset($decoded['top']) && strtotime((string) $decoded['top']) !== false ? (string) $decoded['top'] : null,
                ];
            }
        }

        return ['etag' => $cursor !== '' ? $cursor : null, 'top' => null];
    }

    /**
     * @return FeedBacklog
     *
     * @since 4.0.0
     */
    private function backlog(): FeedBacklog
    {
        return $this->backlog ??= new FeedBacklog($this->db);
    }

    /**
     * Keep a plan from {@see CnaFeedSource::plan()} or {@see CnaFeedSource::seedPlan()}.
     *
     * @param array $progress The progress.
     * @param array $plan     The plan.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function planned(array &$progress, array $plan): void
    {
        $progress['items']  = array_values($plan['items']);
        $progress['capped'] = (bool) $plan['capped'];
        $progress['status'] = $plan['status'];
        $progress['index']  = 0;
        $progress['oldest'] = null;
        $progress['phase']  = $progress['items'] === [] ? 'done' : 'records';
    }

    /**
     * Fetch and store the next listed CVE record.
     *
     * @param object        $run      The row.
     * @param array         $progress The progress.
     * @param CnaFeedSource $feed     The source.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function nextRecord(object $run, array &$progress, CnaFeedSource $feed): void
    {
        $item = $progress['items'][$progress['index']] ?? null;

        if (!\is_array($item)) {
            $progress['phase'] = 'done';

            return;
        }

        $progress['index']++;
        $progress['status']['fetched']++;
        $progress['oldest'] = CveRecordMapper::sql((string) ($item['fetchTime'] ?? '')) ?? $progress['oldest'];

        $record = $feed->fetchItem($item, $progress['mode'] === self::MODE_SEED ? 'VEL seed' : 'VEL CNA feed');

        if ($record !== null) {
            $this->keep($run, $record);
        }

        if ($progress['index'] >= \count($progress['items'])) {
            $progress['phase'] = 'done';
        }
    }

    /**
     * Read and store the next page of NVD discovery; windows of at most 120 days, as the API
     * requires.
     *
     * @param object        $run      The row.
     * @param array         $progress The progress.
     * @param NvdFeedSource $feed     The source.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function nextPage(object $run, array &$progress, NvdFeedSource $feed): void
    {
        $utc    = new DateTimeZone('UTC');
        $cursor = new DateTimeImmutable((string) $progress['cursor'], $utc);
        $to     = new DateTimeImmutable((string) $progress['to'], $utc);

        if ($cursor >= $to) {
            $progress['phase'] = 'enrich';

            return;
        }

        $end  = min($to, $cursor->modify('+' . NvdFeedSource::WINDOW_DAYS . ' days'));
        $page = $feed->page($cursor, $end, (int) $progress['start']);

        foreach ($page['records'] as $record) {
            $this->keep($run, $record);
        }

        // `total` is the results of the windows read so far plus this one's, for the page.
        $progress['start'] += max(1, $page['count']);
        $progress['read']  += $page['count'];
        $progress['total']  = (int) ($progress['base'] ?? 0) + $page['total'];

        if ($page['count'] === 0 || $progress['start'] >= $page['total']) {
            $progress['cursor'] = $end->format('Y-m-d H:i:s');
            $progress['start']  = 0;
            $progress['base']   = $progress['read'];

            if ($end >= $to) {
                $progress['phase'] = 'enrich';
            }
        }
    }

    /**
     * Enrichment, one advisory at a time: every published advisory with a CVE id and no CVSS
     * or no CWE gets the NVD's record queued as a merge offer. Most recent records come back
     * `Deferred` with nothing to offer; the queue says so. Not counted in the run's tallies.
     *
     * @param array               $progress The progress.
     * @param FeedSourceInterface $feed     The NVD source.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function nextEnrichment(array &$progress, FeedSourceInterface $feed): void
    {
        if (!isset($progress['enrich'])) {
            $progress['enrich']   = $this->enrichmentCandidates();
            $progress['eindex']   = 0;
            $progress['enriched'] = 0;

            if ($progress['enrich'] === []) {
                $progress['phase'] = 'done';
            }

            return;
        }

        $cveId = $progress['enrich'][$progress['eindex']] ?? null;

        if ($cveId === null) {
            $progress['phase'] = 'done';

            return;
        }

        $progress['eindex']++;

        try {
            $record = $feed->fetchOne((string) $cveId);

            if ($record !== null) {
                $this->store($record, false);
                $progress['enriched']++;
            }
        } catch (Throwable $e) {
            Log::add('VEL NVD enrichment: ' . $cveId . ': ' . $e->getMessage(), Log::WARNING, 'com_vel');
        }

        if ($progress['eindex'] >= \count($progress['enrich'])) {
            $progress['phase'] = 'done';
        }
    }

    /**
     * Store a record, count it, and note it for the page.
     *
     * @param object     $run    The row.
     * @param FeedRecord $record The record.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function keep(object $run, FeedRecord $record): void
    {
        $outcome = $this->store($record, $this->autoCreate);

        $this->tally($run, $outcome);

        if (($outcome === 'new' || $outcome === 'updated') && \count($this->events) < self::MAX_EVENTS) {
            $this->events[] = ['id' => $record->externalId, 'outcome' => $outcome, 'title' => $record->title()];
        }
    }

    /**
     * Close a run from its progress: status, message, and where the next run starts.
     *
     * @param object      $run      The row.
     * @param array       $progress The progress.
     * @param string|null $cut      Why it ends before its plan does, if it does: `time_budget`,
     *                              `stopped` or `interrupted`.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function conclude(object $run, array &$progress, ?string $cut = null): void
    {
        $phase = (string) $progress['phase'];
        $etag  = (string) $run->cursor !== '' ? (string) $run->cursor : null;

        if ($progress['mode'] === self::MODE_SEED) {
            $status = CnaFeedSource::close($progress['status'] ?? CnaFeedSource::freshStatus(), null, (bool) ($progress['capped'] ?? false), $cut);

            $run->status  = $status['complete'] ? 'ok' : 'partial';
            $run->message = $status['complete']
                ? Text::sprintf('COM_VEL_FEED_RUN_MSG_SEED_COMPLETE', (int) $status['fetched'])
                : Text::sprintf('COM_VEL_FEED_RUN_MSG_SEED_PARTIAL', $this->reasonText((string) $status['reason'], $status, $run), (int) $status['fetched']);

            // A completed seed sets the delta window to now; polling takes over from here.
            // Otherwise it is not a window at all: the next poll must not treat the seed as its
            // predecessor.
            $run->window_to = $status['complete'] ? (string) $progress['to'] : null;
        } elseif ($this->readsTheLog($run, $progress)) {
            $this->concludeQueued($run, $progress, $cut);
        } elseif ((string) $run->source === FeedSource::CNA->value) {
            $status = CnaFeedSource::close($progress['status'] ?? CnaFeedSource::freshStatus($etag), $progress['oldest'] ?? null, (bool) ($progress['capped'] ?? false), $cut);

            $run->cursor = (string) ($status['etag'] ?? '');

            if ($status['complete']) {
                $run->status = 'ok';
            } else {
                $run->status  = 'partial';
                $run->message = $this->reasonText((string) $status['reason'], $status, $run);

                // Resume from where this run stopped, never from its own end.
                $run->window_to = (string) ($status['resume_at'] ?? $run->window_from);
            }
        } elseif ($cut !== null && $phase === 'discover') {
            // NVD discovery did not cover the window: the next run starts it again.
            $run->status    = 'partial';
            $run->message   = $this->reasonText($cut, [], $run);
            $run->window_to = $run->window_from;
        } else {
            // NVD discovery covered the window; enrichment is best effort.
            $run->status = 'ok';
        }

        $run->finished     = $this->sqlTime();
        $progress['phase'] = 'done';
    }

    /**
     * Close a CVE feed run that read the log into the queue.
     *
     * Cut while still reading: nothing it staged is kept and its window is not one (`window_to`
     * NULL), so the next run reads from where the last complete read ended. After the read:
     * the window and ETag move on - the ids are safe in the queue - and the run is `partial`
     * while the queue still holds work.
     *
     * @param object      $run      The row.
     * @param array       $progress The progress.
     * @param string|null $cut      Why it ends early, if it does.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function concludeQueued(object $run, array $progress, ?string $cut): void
    {
        $read = (array) $progress['read'];

        if ($progress['phase'] === 'read') {
            $this->backlog()->discard((int) $run->id);

            $run->status    = 'partial';
            $run->message   = Text::sprintf('COM_VEL_FEED_RUN_MSG_READ_CUT', $this->reasonText($cut ?? 'interrupted', [], $run));
            $run->window_to = null;
            $run->cursor    = '';

            return;
        }

        $remaining = $this->backlog()->remaining(FeedSource::CNA);
        $setAside  = $this->backlog()->setAside(FeedSource::CNA);
        $notes     = [];
        $top       = (float) $read['top'];

        // The log has been read to `top`: the next run reads the entries after it.
        $run->cursor    = self::logPosition(null, isset($read['etag']) ? (string) $read['etag'] : null, CnaFeedSource::iso($top));
        $run->window_to = gmdate('Y-m-d H:i:s', (int) floor($top));

        if (!empty($read['gap'])) {
            $notes[] = Text::sprintf('COM_VEL_FEED_RUN_MSG_GAP_EXCEEDS_RETENTION', (string) $read['since']);
        }

        if ($cut === 'stopped' || $cut === 'interrupted') {
            $notes[] = $this->reasonText($cut, [], $run);
        }

        if ($remaining > 0) {
            $notes[] = Text::plural('COM_VEL_FEED_RUN_MSG_QUEUED', $remaining);
        }

        if ($setAside > 0) {
            $notes[] = Text::plural('COM_VEL_FEED_RUN_MSG_SET_ASIDE', $setAside, FeedBacklog::MAX_ATTEMPTS);
        }

        $run->status  = !empty($read['gap']) || $remaining > 0 ? 'partial' : 'ok';
        $run->message = implode(' ', $notes);
    }

    /**
     * Close a run as failed.
     *
     * @param object $run     The row.
     * @param string $message What went wrong.
     * @param bool   $seed    Whether it was a seed.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function fail(object $run, string $message, bool $seed): void
    {
        $run->status   = 'failed';
        $run->message  = mb_substr($seed ? Text::sprintf('COM_VEL_FEED_RUN_MSG_SEED_FAILED', $message) : $message, 0, 2000);
        $run->finished = $this->sqlTime();

        Log::add(($seed ? 'VEL seed failed: ' : 'VEL feed ' . $run->source . ' failed: ') . $message, Log::ERROR, 'com_vel');
    }

    /**
     * @param int $offsetSeconds Seconds from now.
     *
     * @return string  SQL datetime, UTC.
     *
     * @since 4.0.0
     */
    private function sqlTime(int $offsetSeconds = 0): string
    {
        return gmdate('Y-m-d H:i:s', time() + $offsetSeconds);
    }

    /**
     * @param FeedSource        $source Which feed.
     * @param DateTimeImmutable $now    Now.
     *
     * @return object
     *
     * @since 4.0.0
     */
    private function newRun(FeedSource $source, DateTimeImmutable $now): object
    {
        return (object) [
            'source'        => $source->value,
            'started'       => $now->format('Y-m-d H:i:s'),
            'finished'      => null,
            'window_from'   => null,
            'window_to'     => $now->format('Y-m-d H:i:s'),
            'items_seen'    => 0,
            'items_new'     => 0,
            'items_updated' => 0,
            'status'        => 'running',
            'message'       => '',
            'cursor'        => '',
            'progress'      => null,
            'heartbeat'     => null,
        ];
    }

    /**
     * @param object $run     The run.
     * @param string $outcome new | updated | unchanged | skipped.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function tally(object $run, string $outcome): void
    {
        $run->items_seen++;

        if ($outcome === 'new') {
            $run->items_new++;
        } elseif ($outcome === 'updated') {
            $run->items_updated++;
        }
    }

    /**
     * @param FeedSource $source Which feed.
     *
     * @return object|null  The last run that moved the window (ok or partial), or null.
     *
     * @since 4.0.0
     */
    private function lastRun(FeedSource $source): ?object
    {
        $value = $source->value;

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_feed_runs'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('status') . ' IN (' . $this->db->quote('ok') . ', ' . $this->db->quote('partial') . ')')
                ->where($this->db->quoteName('window_to') . ' IS NOT NULL')
                ->bind(':source', $value)
                ->order($this->db->quoteName('id') . ' DESC'),
            0,
            1
        )->loadObject() ?: null;
    }

    /**
     * Where this run starts: the last run's window end, or the look-back.
     *
     * @param FeedSource        $source Which feed.
     * @param DateTimeImmutable $now    Now.
     * @param object|null       $last   The last run.
     *
     * @return DateTimeImmutable
     *
     * @since 4.0.0
     */
    private function windowStart(FeedSource $source, DateTimeImmutable $now, ?object $last): DateTimeImmutable
    {
        $params   = ComponentHelper::getParams('com_vel');
        $lookback = max(1, (int) $params->get($source === FeedSource::CNA ? 'cna_lookback_days' : 'nvd_lookback_days', 7));
        $floor    = $now->modify('-' . $lookback . ' days');

        if ($last === null || empty($last->window_to)) {
            return $floor;
        }

        try {
            $lastTo = new DateTimeImmutable((string) $last->window_to, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return $floor;
        }

        // A little overlap, so a record updated in the same minute the last run ended is not
        // lost; the hash makes the overlap free. The CVE feed's delta entries are ~11 minutes
        // apart, so an hour is plenty.
        $lastTo = $lastTo->modify('-1 hour');

        return $lastTo < $floor ? $floor : $lastTo;
    }

    /**
     * Upsert a record into the queue.
     *
     * @param FeedRecord $record     The record.
     * @param bool       $autoCreate Create drafts for Joomla CNA records.
     *
     * @return string  new | updated | unchanged | skipped
     *
     * @since 4.0.0
     */
    public function store(FeedRecord $record, bool $autoCreate): string
    {
        $source = $record->source->value;
        $hash   = $record->hash();
        $now    = Factory::getDate()->toSql();

        // bind() takes its value by reference, and a reference to a readonly property is an
        // Error ("Cannot modify readonly property"): bind a copy.
        $externalId = $record->externalId;

        $existing = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_feed_items'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('external_id') . ' = :id')
                ->bind(':source', $source)
                ->bind(':id', $externalId)
        )->loadObject();

        $merger     = new AdvisoryMerger($this->db, $this->service);
        $advisory   = $merger->findExisting($record);
        $advisoryId = $advisory !== null ? (int) $advisory->id : null;

        // A rejected record matters only when it is ours or when we carry an advisory for it;
        // the rest of the world's rejections are noise.
        if ($record->classification === FeedClassification::REJECTED && $advisoryId === null && $existing === null
            && !self::classifier()->isOurs($record->normalised)) {
            return 'skipped';
        }

        $classification = ($record->classification ?? FeedClassification::UNCLASSIFIED)->value;

        if ($existing !== null) {
            if ((string) $existing->content_hash === $hash) {
                if ($advisoryId !== null && (int) ($existing->advisory_id ?? 0) === 0) {
                    $this->db->setQuery(
                        $this->db->getQuery(true)->update($this->db->quoteName('#__vel_feed_items'))
                            ->set($this->db->quoteName('advisory_id') . ' = :aid')
                            ->where($this->db->quoteName('id') . ' = :id')
                            ->bind(':aid', $advisoryId, ParameterType::INTEGER)
                            ->bind(':id', $existing->id, ParameterType::INTEGER)
                    )->execute();
                }

                return 'unchanged';
            }

            $existing->title                 = $record->title();
            $existing->assigner              = $record->assigner();
            $existing->fetched               = $now;
            $existing->external_updated      = $record->updated();
            $existing->content_hash          = $hash;
            $existing->payload               = json_encode($record->normalised, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $existing->classification        = $classification;
            $existing->classification_reason = $record->classificationReason;
            $existing->confidence            = $record->confidence;
            $existing->advisory_id           = $advisoryId ?? ($existing->advisory_id !== null ? (int) $existing->advisory_id : null);

            // A record that changed is worth a second look even if it was ignored before.
            if ((string) $existing->decision !== FeedDecision::CREATED->value) {
                $existing->decision     = FeedDecision::PENDING->value;
                $existing->decided_by   = null;
                $existing->decided_time = null;
            }

            $this->db->updateObject('#__vel_feed_items', $existing, 'id', true);

            if ($record->classification === FeedClassification::REJECTED && $advisoryId !== null) {
                $this->flagRejected($record, $advisoryId);
            }

            return 'updated';
        }

        $item = (object) [
            'source'                => $source,
            'external_id'           => $record->externalId,
            'assigner'              => $record->assigner(),
            'title'                 => $record->title(),
            'fetched'               => $now,
            'external_updated'      => $record->updated(),
            'content_hash'          => $hash,
            'payload'               => json_encode($record->normalised, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'classification'        => $classification,
            'classification_reason' => $record->classificationReason,
            'confidence'            => $record->confidence,
            'decision'              => FeedDecision::PENDING->value,
            'advisory_id'           => $advisoryId,
            'decided_by'            => null,
            'decided_time'          => null,
        ];

        // A Joomla CNA record with no advisory becomes a draft without waiting for anybody.
        if ($autoCreate && $advisoryId === null && $record->classification === FeedClassification::JOOMLA_CNA) {
            try {
                $item->advisory_id  = $merger->create($record, 0);
                $item->decision     = FeedDecision::CREATED->value;
                $item->decided_by   = 0;
                $item->decided_time = $now;
            } catch (Throwable $e) {
                Log::add('VEL feed: draft for ' . $record->externalId . ' not created: ' . $e->getMessage(), Log::WARNING, 'com_vel');
            }
        }

        $this->db->insertObject('#__vel_feed_items', $item, 'id');

        if ($record->classification === FeedClassification::REJECTED && $advisoryId !== null) {
            $this->flagRejected($record, $advisoryId);
        }

        return 'new';
    }

    /**
     * The operator-facing wording for a source's "not complete" reason. `partial` has several
     * meanings (feeds PRD §3.2) and the message must say which one applies.
     *
     * @param string $reason The source's reason code.
     * @param array  $status The source's status array.
     * @param object $run    The run row.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function reasonText(string $reason, array $status, object $run): string
    {
        $resumeAt = (string) ($status['resume_at'] ?? $run->window_from ?? '');

        return match ($reason) {
            'fetch_cap'             => Text::sprintf('COM_VEL_FEED_RUN_MSG_FETCH_CAP', $resumeAt),
            'range_under_sized'     => Text::_('COM_VEL_FEED_RUN_MSG_RANGE_UNDER_SIZED'),
            'gap_exceeds_retention' => Text::sprintf('COM_VEL_FEED_RUN_MSG_GAP_EXCEEDS_RETENTION', $resumeAt),
            'time_budget'           => Text::sprintf('COM_VEL_FEED_RUN_MSG_TIME_BUDGET', $resumeAt),
            'stopped'               => Text::_('COM_VEL_FEED_RUN_MSG_STOPPED'),
            'interrupted'           => Text::_('COM_VEL_FEED_RUN_MSG_INTERRUPTED'),
            default                 => $reason,
        };
    }

    /**
     * A CVE we carry has been rejected at the Program: the advisory's team must know.
     *
     * @param FeedRecord $record     The record.
     * @param int        $advisoryId The advisory.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function flagRejected(FeedRecord $record, int $advisoryId): void
    {
        $this->service->appendNote($advisoryId, Text::sprintf('COM_VEL_NOTE_FEED_REJECTED', $record->externalId, (string) ($record->normalised['description'] ?? '')));

        Log::add(\sprintf('Advisory %d: %s has been rejected at the CVE Program', $advisoryId, $record->externalId), Log::WARNING, 'com_vel');
    }

    /**
     * The advisories enrichment looks at: published or ready, with a CVE id, missing CVSS or
     * CWE, and not offered by the NVD in the last week. At most 25 per run.
     *
     * @return string[]  CVE ids.
     *
     * @since 4.0.0
     */
    private function enrichmentCandidates(): array
    {
        $published = AdvisoryState::PUBLISHED->value;
        $ready     = AdvisoryState::READY->value;

        $ids = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['a.cve_id']))
                ->from($this->db->quoteName('#__vel_advisories', 'a'))
                ->where($this->db->quoteName('a.cve_id') . ' IS NOT NULL')
                ->where($this->db->quoteName('a.state') . ' IN (:p, :r)')
                ->where('(' . $this->db->quoteName('a.cvss_vector') . ' IS NULL OR NOT EXISTS (SELECT 1 FROM '
                    . $this->db->quoteName('#__vel_advisory_cwes', 'c') . ' WHERE ' . $this->db->quoteName('c.advisory_id') . ' = ' . $this->db->quoteName('a.id') . '))')
                ->where('NOT EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__vel_feed_items', 'f')
                    . ' WHERE ' . $this->db->quoteName('f.source') . ' = ' . $this->db->quote(FeedSource::NVD->value)
                    . ' AND ' . $this->db->quoteName('f.external_id') . ' = ' . $this->db->quoteName('a.cve_id')
                    . ' AND ' . $this->db->quoteName('f.fetched') . ' > ' . $this->db->quote(Factory::getDate('now -7 days')->toSql()) . ')')
                ->bind(':p', $published)
                ->bind(':r', $ready)
                ->order($this->db->quoteName('a.date_updated') . ' DESC'),
            0,
            25
        )->loadColumn() ?: [];

        return array_map('strval', $ids);
    }
}
