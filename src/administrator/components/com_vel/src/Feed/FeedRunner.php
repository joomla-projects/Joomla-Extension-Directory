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
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * One pass of one feed (VEL2-PRD §6.6.3): a run row, a window, the records, the queue.
 *
 * Idempotent by `(source, external_id)` and the content hash: an unchanged record is a no-op, a
 * changed one goes back to `pending` with its advisory still linked so the merge is offered.
 * Nothing here publishes; the most a run does is create a *draft* for a Joomla CNA record when
 * the option allows it.
 *
 * @since 4.0.0
 */
final class FeedRunner
{
    /**
     * @param DatabaseInterface $db      The database driver.
     * @param VelService        $service The lifecycle service.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db, private readonly VelService $service)
    {
    }

    /**
     * The configured source for a feed.
     *
     * @param FeedSource $source Which feed.
     *
     * @return FeedSourceInterface
     *
     * @since 4.0.0
     */
    public static function source(FeedSource $source): FeedSourceInterface
    {
        $params     = ComponentHelper::getParams('com_vel');
        $http       = HttpFactory::getHttp(['timeout' => 60]);
        $classifier = new RecordClassifier(
            (string) $params->get('cna_short_name', 'Joomla'),
            (string) $params->get('cna_org_id', ''),
            (int) $params->get('feed_import_other_cnas', 1) === 1
        );

        return match ($source) {
            FeedSource::CNA => new CnaFeedSource(
                new CvelistClient($http, (string) $params->get('github_token', '')),
                CveServicesClient::fromOptions(),
                (string) $params->get('cna_source', 'cvelist_github') === 'cve_services',
                $classifier
            ),
            FeedSource::NVD => new NvdFeedSource(new NvdClient($http, (string) $params->get('nvd_api_key', '')), $classifier),
        };
    }

    /**
     * @param FeedSource $source        Which feed.
     * @param int        $budgetSeconds How long the run may take.
     *
     * @return object  The run row.
     *
     * @since 4.0.0
     */
    public function run(FeedSource $source, int $budgetSeconds = 120): object
    {
        $params  = ComponentHelper::getParams('com_vel');
        $enabled = $source === FeedSource::CNA ? (int) $params->get('cna_enabled', 1) === 1 : (int) $params->get('nvd_enabled', 1) === 1;
        $now     = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $run     = (object) [
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
        ];

        if (!$enabled) {
            $run->status   = 'ok';
            $run->finished = $run->started;
            $run->message  = 'disabled';

            return $run;
        }

        $from = $this->windowStart($source, $now);

        $run->window_from = $from->format('Y-m-d H:i:s');

        $this->db->insertObject('#__vel_feed_runs', $run, 'id');

        $start    = microtime(true);
        $deadline = static fn (): bool => (microtime(true) - $start) > $budgetSeconds;
        $autoCreate = (int) $params->get('feed_auto_create', 1) === 1;
        $partial    = false;

        try {
            $feed = self::source($source);

            foreach ($feed->fetch($from, $now, $deadline) as $record) {
                $run->items_seen++;

                try {
                    $outcome = $this->store($record, $autoCreate);
                } catch (Throwable $e) {
                    Log::add('VEL feed ' . $source->value . ': ' . $record->externalId . ': ' . $e->getMessage(), Log::WARNING, 'com_vel');
                    $partial = true;

                    continue;
                }

                if ($outcome === 'new') {
                    $run->items_new++;
                } elseif ($outcome === 'updated') {
                    $run->items_updated++;
                }
            }

            if ($deadline()) {
                $partial      = true;
                $run->message = 'time budget reached; the next run continues from ' . $run->window_from;
                // Keep the window open so the next run covers what was missed.
                $run->window_to = $run->window_from;
            }

            $run->status = $partial ? 'partial' : 'ok';
        } catch (Throwable $e) {
            $run->status  = 'failed';
            $run->message = mb_substr($e->getMessage(), 0, 2000);
            Log::add('VEL feed ' . $source->value . ' failed: ' . $e->getMessage(), Log::ERROR, 'com_vel');
        }

        $run->finished = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->db->updateObject('#__vel_feed_runs', $run, 'id');

        if ($source === FeedSource::NVD && !$deadline()) {
            $this->enrichOwn($feed ?? null, $deadline);
        }

        return $run;
    }

    /**
     * Where this run starts: the last run's window end, or the look-back.
     *
     * @param FeedSource        $source Which feed.
     * @param DateTimeImmutable $now    Now.
     *
     * @return DateTimeImmutable
     *
     * @since 4.0.0
     */
    private function windowStart(FeedSource $source, DateTimeImmutable $now): DateTimeImmutable
    {
        $params   = ComponentHelper::getParams('com_vel');
        $lookback = max(1, (int) $params->get($source === FeedSource::CNA ? 'cna_lookback_days' : 'nvd_lookback_days', 7));
        $floor    = $now->modify('-' . $lookback . ' days');
        $value    = $source->value;

        $last = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('window_to'))
                ->from($this->db->quoteName('#__vel_feed_runs'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('status') . ' IN (' . $this->db->quote('ok') . ', ' . $this->db->quote('partial') . ')')
                ->bind(':source', $value)
                ->order($this->db->quoteName('id') . ' DESC'),
            0,
            1
        )->loadResult();

        if ($last === null) {
            return $floor;
        }

        try {
            $lastTo = new DateTimeImmutable((string) $last, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return $floor;
        }

        // A little overlap, so a record updated in the same second as the last run ended is
        // not lost; the hash makes the overlap free.
        $lastTo = $lastTo->modify('-1 hour');

        return $lastTo < $floor ? $floor : $lastTo;
    }

    /**
     * Upsert a record into the queue.
     *
     * @param FeedRecord $record     The record.
     * @param bool       $autoCreate Create drafts for Joomla CNA records.
     *
     * @return string  new | updated | unchanged
     *
     * @since 4.0.0
     */
    public function store(FeedRecord $record, bool $autoCreate): string
    {
        $source = $record->source->value;
        $hash   = $record->hash();
        $now    = Factory::getDate()->toSql();

        $existing = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_feed_items'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('external_id') . ' = :id')
                ->bind(':source', $source)
                ->bind(':id', $record->externalId)
        )->loadObject();

        $advisory   = (new AdvisoryMerger($this->db, $this->service))->findExisting($record);
        $advisoryId = $advisory !== null ? (int) $advisory->id : null;

        if ($existing !== null) {
            if ((string) $existing->content_hash === $hash) {
                // Link the advisory if one appeared since.
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

            $existing->title            = $record->title();
            $existing->assigner         = $record->assigner();
            $existing->fetched          = $now;
            $existing->external_updated = $record->updated();
            $existing->content_hash     = $hash;
            $existing->payload          = json_encode($record->normalised, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $existing->classification   = ($record->classification ?? FeedClassification::UNKNOWN)->value;
            $existing->advisory_id      = $advisoryId ?? ($existing->advisory_id !== null ? (int) $existing->advisory_id : null);

            // A record that changed is worth a second look even if it was ignored before.
            if ((string) $existing->decision !== FeedDecision::CREATED->value) {
                $existing->decision     = FeedDecision::PENDING->value;
                $existing->decided_by   = null;
                $existing->decided_time = null;
            }

            $this->db->updateObject('#__vel_feed_items', $existing, 'id', true);

            return 'updated';
        }

        $item = (object) [
            'source'           => $source,
            'external_id'      => $record->externalId,
            'assigner'         => $record->assigner(),
            'title'            => $record->title(),
            'fetched'          => $now,
            'external_updated' => $record->updated(),
            'content_hash'     => $hash,
            'payload'          => json_encode($record->normalised, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'classification'   => ($record->classification ?? FeedClassification::UNKNOWN)->value,
            'decision'         => FeedDecision::PENDING->value,
            'advisory_id'      => $advisoryId,
            'decided_by'       => null,
            'decided_time'     => null,
        ];

        // A Joomla CNA record with no advisory becomes a draft without waiting for anybody.
        if ($autoCreate && $advisoryId === null && $record->classification === FeedClassification::JOOMLA_CNA) {
            try {
                $item->advisory_id  = (new AdvisoryMerger($this->db, $this->service))->create($record, 0);
                $item->decision     = FeedDecision::CREATED->value;
                $item->decided_by   = 0;
                $item->decided_time = $now;
            } catch (Throwable $e) {
                Log::add('VEL feed: draft for ' . $record->externalId . ' not created: ' . $e->getMessage(), Log::WARNING, 'com_vel');
            }
        }

        $this->db->insertObject('#__vel_feed_items', $item, 'id');

        return 'new';
    }

    /**
     * Enrichment: every published advisory with a CVE id and no CVSS or no CWE gets the NVD's
     * record queued as a merge offer.
     *
     * @param FeedSourceInterface|null $feed     The NVD source.
     * @param callable                 $deadline Budget check.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function enrichOwn(?FeedSourceInterface $feed, callable $deadline): void
    {
        if ($feed === null) {
            return;
        }

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

        foreach ($ids as $cveId) {
            if ($deadline()) {
                return;
            }

            try {
                $record = $feed->fetchOne((string) $cveId);

                if ($record !== null) {
                    $this->store($record, false);
                }
            } catch (Throwable $e) {
                Log::add('VEL NVD enrichment: ' . $cveId . ': ' . $e->getMessage(), Log::WARNING, 'com_vel');
            }
        }
    }
}
