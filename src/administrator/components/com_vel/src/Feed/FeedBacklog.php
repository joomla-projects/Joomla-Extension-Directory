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

use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * The CVE feed's work queue (`#__vel_feed_backlog`): the ids the delta log has listed whose
 * records have not been dealt with yet.
 *
 * Reading the log and fetching records are separate so that neither can lose the other's work:
 *
 *  - a read **stages** the ids it finds under its run id, and they are **committed** only when the
 *    read has loaded the whole window; a read that fails, is stopped or is interrupted is
 *    **discarded** and leaves the queue as it was;
 *  - an id leaves the queue only when its record has been handled - stored, found not worth
 *    keeping, or gone at the source. A fetch that fails keeps it queued, and after
 *    {@see self::MAX_ATTEMPTS} failures it is set aside (logged, counted in the run message)
 *    until the log lists it again.
 *
 * @since 4.0.0
 */
final class FeedBacklog
{
    /**
     * Failed fetches after which an id is set aside.
     *
     * @since 4.0.0
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Rows per INSERT / IN () list.
     *
     * @since 4.0.0
     */
    private const CHUNK = 200;

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Stage ids found by a read that is still in progress. Ids already queued are left as they
     * are, except that one set aside after failing is given another go: it has changed again.
     *
     * @param FeedSource                                                  $source Which feed.
     * @param int                                                         $runId  The reading run.
     * @param array<int, array{id: string, url?: string, fetchTime?: string}> $items  Newest first.
     *
     * @return int  How many ids were new to the queue.
     *
     * @since 4.0.0
     */
    public function stage(FeedSource $source, int $runId, array $items): int
    {
        // The first (newest) mention of an id wins.
        $byId = [];

        foreach ($items as $item) {
            $id = strtoupper((string) ($item['id'] ?? ''));

            if ($id !== '' && !isset($byId[$id])) {
                $byId[$id] = $item;
            }
        }

        $value = $source->value;
        $now   = gmdate('Y-m-d H:i:s');
        $added = 0;

        foreach (array_chunk(array_keys($byId), self::CHUNK) as $ids) {
            $existing = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName(['external_id', 'attempts']))
                    ->from($this->db->quoteName('#__vel_feed_backlog'))
                    ->where($this->db->quoteName('source') . ' = :source')
                    ->whereIn($this->db->quoteName('external_id'), $ids, ParameterType::STRING)
                    ->bind(':source', $value)
            )->loadAssocList('external_id', 'attempts') ?: [];

            $retry = array_keys(array_filter($existing, static fn ($attempts): bool => (int) $attempts >= self::MAX_ATTEMPTS));

            if ($retry !== []) {
                $this->db->setQuery(
                    $this->db->getQuery(true)
                        ->update($this->db->quoteName('#__vel_feed_backlog'))
                        ->set($this->db->quoteName('attempts') . ' = 0')
                        ->set($this->db->quoteName('last_error') . ' = ' . $this->db->quote(''))
                        ->where($this->db->quoteName('source') . ' = :source')
                        ->whereIn($this->db->quoteName('external_id'), $retry, ParameterType::STRING)
                        ->bind(':source', $value)
                )->execute();
            }

            $new = array_values(array_diff($ids, array_map('strval', array_keys($existing))));

            if ($new === []) {
                continue;
            }

            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__vel_feed_backlog'))
                ->columns($this->db->quoteName(['source', 'external_id', 'url', 'fetch_time', 'queued', 'attempts', 'last_error', 'staged_by']));

            foreach ($new as $id) {
                $item      = $byId[$id];
                $fetchTime = CveRecordMapper::sql((string) ($item['fetchTime'] ?? ''));

                $query->values(implode(',', [
                    $this->db->quote($value),
                    $this->db->quote($id),
                    $this->db->quote(mb_substr((string) ($item['url'] ?? ''), 0, 255)),
                    $fetchTime === null ? 'NULL' : $this->db->quote($fetchTime),
                    $this->db->quote($now),
                    '0',
                    $this->db->quote(''),
                    (string) $runId,
                ]));
            }

            $this->db->setQuery($query)->execute();

            $added += \count($new);
        }

        return $added;
    }

    /**
     * The read of a run loaded the whole window: its staged ids join the queue.
     *
     * @param int $runId The run.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function commit(int $runId): void
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_feed_backlog'))
                ->set($this->db->quoteName('staged_by') . ' = NULL')
                ->where($this->db->quoteName('staged_by') . ' = :run')
                ->bind(':run', $runId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * The read of a run did not load to the end: forget what it staged.
     *
     * @param int $runId The run.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function discard(int $runId): void
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__vel_feed_backlog'))
                ->where($this->db->quoteName('staged_by') . ' = :run')
                ->bind(':run', $runId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * The next ids to fetch: committed, not set aside; ones that have not failed before first,
     * then newest first.
     *
     * @param FeedSource $source Which feed.
     * @param int        $limit  How many.
     *
     * @return list<array{id: string, url: string, fetchTime: string}>
     *
     * @since 4.0.0
     */
    public function batch(FeedSource $source, int $limit): array
    {
        $value = $source->value;
        $rows  = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['external_id', 'url', 'fetch_time']))
                ->from($this->db->quoteName('#__vel_feed_backlog'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('staged_by') . ' IS NULL')
                ->where($this->db->quoteName('attempts') . ' < ' . self::MAX_ATTEMPTS)
                ->bind(':source', $value)
                ->order([$this->db->quoteName('attempts') . ' ASC', $this->db->quoteName('fetch_time') . ' DESC', $this->db->quoteName('id') . ' ASC']),
            0,
            max(1, $limit)
        )->loadAssocList() ?: [];

        return array_map(static fn (array $row): array => [
            'id'        => (string) $row['external_id'],
            'url'       => (string) $row['url'],
            'fetchTime' => (string) ($row['fetch_time'] ?? ''),
        ], $rows);
    }

    /**
     * An id has been dealt with: it leaves the queue.
     *
     * @param FeedSource $source     Which feed.
     * @param string     $externalId The id.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function done(FeedSource $source, string $externalId): void
    {
        $value = $source->value;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__vel_feed_backlog'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('external_id') . ' = :id')
                ->bind(':source', $value)
                ->bind(':id', $externalId)
        )->execute();
    }

    /**
     * Fetching an id failed: it stays queued, one attempt further on.
     *
     * @param FeedSource $source     Which feed.
     * @param string     $externalId The id.
     * @param string     $error      What went wrong.
     *
     * @return int  The attempts so far.
     *
     * @since 4.0.0
     */
    public function failed(FeedSource $source, string $externalId, string $error): int
    {
        $value = $source->value;
        $error = mb_substr($error, 0, 255);

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_feed_backlog'))
                ->set($this->db->quoteName('attempts') . ' = ' . $this->db->quoteName('attempts') . ' + 1')
                ->set($this->db->quoteName('last_error') . ' = :error')
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('external_id') . ' = :id')
                ->bind(':error', $error)
                ->bind(':source', $value)
                ->bind(':id', $externalId)
        )->execute();

        return (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('attempts'))
                ->from($this->db->quoteName('#__vel_feed_backlog'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('external_id') . ' = :id')
                ->bind(':source', $value)
                ->bind(':id', $externalId)
        )->loadResult();
    }

    /**
     * @param FeedSource $source Which feed.
     *
     * @return int  Ids waiting to be fetched.
     *
     * @since 4.0.0
     */
    public function remaining(FeedSource $source): int
    {
        return $this->count($source, ' < ');
    }

    /**
     * @param FeedSource $source Which feed.
     *
     * @return int  Ids set aside after {@see self::MAX_ATTEMPTS} failed fetches.
     *
     * @since 4.0.0
     */
    public function setAside(FeedSource $source): int
    {
        return $this->count($source, ' >= ');
    }

    /**
     * @param FeedSource $source   Which feed.
     * @param string     $operator Against MAX_ATTEMPTS.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function count(FeedSource $source, string $operator): int
    {
        $value = $source->value;

        return (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__vel_feed_backlog'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('staged_by') . ' IS NULL')
                ->where($this->db->quoteName('attempts') . $operator . self::MAX_ATTEMPTS)
                ->bind(':source', $value)
        )->loadResult();
    }
}
