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
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Joomla\CMS\Language\Text;

/**
 * The NVD feed (VEL2-PRD §6.6.2): discovery by keyword, enrichment by id.
 *
 * The classifier recognises our own records by the NVD's `sourceIdentifier`
 * (`security@joomla.org`). What the NVD contributes for them is enrichment - CVSS, CWE, CPE
 * bounds - done by {@see self::fetchOne()} for every advisory with a CVE id, and it comes back
 * empty for most recent records (`Deferred`: 209 of the 326 records of 2026). Discovery keeps
 * other CNAs' records that name a Joomla extension and queues mere mentions for a person.
 *
 * Errors: the NVD answers a bad request with 404 and a `message` header; `NvdClient` turns
 * that into an exception, and this source lets it end the run loudly rather than as "nothing
 * found".
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
     * The widest window the API accepts, less a day of margin.
     *
     * @since 4.0.0
     */
    public const WINDOW_DAYS = 119;

    /**
     * Results per discovery page.
     *
     * @since 4.0.0
     */
    public const PER_PAGE = 500;

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
            $end   = min($to, $cursor->modify('+' . self::WINDOW_DAYS . ' days'));
            $start = 0;

            do {
                if ($deadline()) {
                    return;
                }

                $page = $this->page($cursor, $end, $start);

                yield from $page['records'];

                $start += max(1, $page['count']);
            } while ($page['count'] > 0 && $start < $page['total']);

            $cursor = $end;
        }
    }

    /**
     * One page of discovery: a single request, so a run split into short steps can do one at
     * a time and remember `startIndex` in between.
     *
     * @param DateTimeImmutable $from  Window start (at most {@see self::WINDOW_DAYS} before `$to`).
     * @param DateTimeImmutable $to    Window end.
     * @param int               $start `startIndex`.
     *
     * @return array{records: list<FeedRecord>, count: int, total: int}
     *         `records` holds the ones worth keeping; `count` is how many the page carried in
     *         all, which is what advances `startIndex`.
     *
     * @throws \RuntimeException  When the NVD refuses or fails the request.
     *
     * @since 4.0.0
     */
    public function page(DateTimeImmutable $from, DateTimeImmutable $to, int $start): array
    {
        try {
            $page = $this->client->search($this->keyword, $from, $to, $start, self::PER_PAGE);
        } catch (\Throwable $e) {
            // A refused request is our bug (404 + message) or a rate limit; either way
            // the run must say so, not report a clean empty window.
            throw new \RuntimeException(Text::sprintf('COM_VEL_ERROR_NVD_DISCOVERY', $e->getMessage()), 0, $e);
        }

        $items   = (array) ($page['vulnerabilities'] ?? []);
        $records = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $mapped = NvdRecordMapper::map($item);

            if ($mapped === null) {
                continue;
            }

            $this->classifier->classify($mapped);

            if ($this->classifier->worthKeeping($mapped->classification)) {
                $records[] = $mapped;
            }
        }

        return ['records' => $records, 'count' => \count($items), 'total' => (int) ($page['totalResults'] ?? 0)];
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
            $this->classifier->classify($mapped);
        }

        return $mapped;
    }
}
