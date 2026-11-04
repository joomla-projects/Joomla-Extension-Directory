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
use Joomla\CMS\Log\Log;

/**
 * The CVE Program feed (VEL2-PRD §6.6.1): the Joomla CNA's own records, and other CNAs'
 * records that name a Joomla extension.
 *
 * Default source is the cvelistV5 delta log, which needs no credentials: every id changed in
 * the window is fetched, mapped and handed to the classifier, which drops the unrelated ones.
 * The alternative source is CVE Services' authenticated list, which returns only the
 * organisation's own records.
 *
 * @since 4.0.0
 */
final class CnaFeedSource implements FeedSourceInterface
{
    /**
     * @param CvelistClient           $cvelist  The GitHub mirror client.
     * @param CveServicesClient|null  $services The CVE Services client, when that source is chosen.
     * @param bool                    $useServices Whether to use CVE Services rather than the mirror.
     * @param RecordClassifier        $classifier  Decides what is worth keeping.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly CvelistClient $cvelist,
        private readonly ?CveServicesClient $services,
        private readonly bool $useServices,
        private readonly RecordClassifier $classifier
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
        if ($this->useServices && $this->services !== null && $this->services->configured()) {
            yield from $this->fetchFromServices($from, $deadline);

            return;
        }

        yield from $this->fetchFromMirror($from, $to, $deadline);
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
    private function fetchFromMirror(DateTimeImmutable $from, DateTimeImmutable $to, callable $deadline): iterable
    {
        $log = $this->cvelist->deltaLog();
        $ids = [];

        foreach ($log as $delta) {
            if (!\is_array($delta)) {
                continue;
            }

            $fetchTime = CveRecordMapper::sql($delta['fetchTime'] ?? null);

            if ($fetchTime !== null && $fetchTime < $from->format('Y-m-d H:i:s')) {
                continue;
            }

            foreach (['new', 'updated'] as $bucket) {
                foreach ((array) ($delta[$bucket] ?? []) as $entry) {
                    $id = strtoupper((string) ($entry['cveId'] ?? ''));

                    if (preg_match('/^CVE-\d{4}-\d{4,}$/', $id)) {
                        $ids[$id] = true;
                    }
                }
            }
        }

        foreach (array_keys($ids) as $id) {
            if ($deadline()) {
                return;
            }

            try {
                $record = $this->cvelist->record($id);
            } catch (\Throwable $e) {
                Log::add('VEL CNA feed: ' . $id . ': ' . $e->getMessage(), Log::WARNING, 'com_vel');

                continue;
            }

            if ($record === null) {
                continue;
            }

            $mapped = CveRecordMapper::map($record);

            if ($mapped === null) {
                continue;
            }

            $mapped->classification = $this->classifier->classify($mapped);

            if ($this->classifier->worthKeeping($mapped->classification)) {
                yield $mapped;
            }
        }
    }

    /**
     * @param DateTimeImmutable $from     Window start.
     * @param callable          $deadline Budget check.
     *
     * @return iterable<FeedRecord>
     *
     * @since 4.0.0
     */
    private function fetchFromServices(DateTimeImmutable $from, callable $deadline): iterable
    {
        $page = 1;

        do {
            if ($deadline()) {
                return;
            }

            $response = $this->services->listModified($from->format('Y-m-d\TH:i:s.v\Z'), $page);
            $records  = (array) ($response['cveRecords'] ?? $response['records'] ?? []);

            foreach ($records as $record) {
                if (!\is_array($record)) {
                    continue;
                }

                $mapped = CveRecordMapper::map($record);

                if ($mapped === null) {
                    continue;
                }

                $mapped->classification = $this->classifier->classify($mapped);

                if ($this->classifier->worthKeeping($mapped->classification)) {
                    yield $mapped;
                }
            }

            $next = $response['nextPage'] ?? null;
            $page = \is_int($next) && $next > $page ? $next : 0;
        } while ($page > 0 && $records !== []);
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
            $mapped->classification = $this->classifier->classify($mapped);
        }

        return $mapped;
    }
}
