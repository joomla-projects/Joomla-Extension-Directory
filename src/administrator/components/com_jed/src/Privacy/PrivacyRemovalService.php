<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Privacy;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Queue\QueueService;
use Jed\Component\Jed\Administrator\Transfer\TransferService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Carries out a `com_privacy` removal request against the JED's tables.
 *
 * 8.12 settled the principle - **deletion must be real** - and left three conflicts open. This
 * class is where they are answered; {@see PrivacyDeterminations} is where the answers are stated
 * per table, and the results document records the reasoning. In short:
 *
 *  1. *Soft delete is not privacy deletion.* Nothing in here filters on `deleted`. A soft-deleted
 *     listing is exactly where data sits longest, so it is the last place that may be skipped.
 *  2. *An owner who withdraws leaves an unowned listing.* The listing stays - it is about
 *     software - but it is blocked under {@see self::BLOCK_REASON_OWNER_WITHDRAWN}, because
 *     `owner` is not part of the visibility rule in 4.8 and an erased owner would otherwise leave
 *     it online with nobody answerable for it. Blocked rather than transferred or reassigned:
 *     those need a volunteer or invent an owner, and this needs neither. `P1-19` can then offer it
 *     for adoption.
 *  3. *A ban is both personal data and evidence.* A ban still in force is retained, because
 *     erasing it would unban the person - which is not an erasure of data but a reversal of a
 *     measure. A ban that has run out has no such interest left, so the whole row goes.
 *
 * Reviews are the one determination left as a setting rather than a constant: anonymising keeps
 * the extension's score intact, deleting changes it, and 8.17 lists that as open. Both paths are
 * implemented, and deleting enqueues the score recalculation the aggregates then need.
 *
 * @since 4.0.0
 */
final class PrivacyRemovalService
{
    /**
     * The `#__jed_block_reasons` code a listing is blocked under when its owner is erased.
     *
     * Seeded by the component's install script, so the public block notice has wording and the
     * knowledge base has something to key an article to, exactly like every other block reason.
     *
     * @since 4.0.0
     */
    public const BLOCK_REASON_OWNER_WITHDRAWN = 'PV1';

    /**
     * Column names per table, so a scrub can skip what a table does not have.
     *
     * The audit columns are *nearly* uniform across this schema and not quite:
     * `#__jed_extensions_files` has no `checked_out`, `#__jed_url_checks` no `modified_by`. Asking
     * the database beats maintaining a per-table list that goes stale the first time a column is
     * added - a privacy removal that dies on an unknown column is a removal that did not happen.
     *
     * @var array<string, string[]>
     *
     * @since 4.0.0
     */
    private array $columnCache = [];

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Whether a ban record has to survive the erasure request.
     *
     * The rule is self-limiting on purpose. "Keep bans forever" would make every banned account
     * permanently unerasable; "erase bans on request" would make the ban system opt-out. Keeping
     * it exactly as long as it is in force is the only reading under which the retention interest
     * and the erasure claim are both answered - and a time-limited ban is already the design
     * (`#__jed_user_access` compares `banned_until` against now rather than relying on a job).
     *
     * @param array<string, mixed>|null $row The user's `#__jed_user_access` row, if there is one.
     * @param string                    $now The current time, in SQL format.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function banMustBeRetained(?array $row, string $now): bool
    {
        if ($row === null || (int) ($row['banned'] ?? 0) !== 1) {
            return false;
        }

        $until = (string) ($row['banned_until'] ?? '');

        // An open-ended ban has no end to wait for, so it is always still in force.
        if ($until === '' || $until === '0000-00-00 00:00:00') {
            return true;
        }

        return $until > $now;
    }

    /**
     * Erase or anonymise everything the JED holds about one person.
     *
     * @param int    $userId         The subject of the request.
     * @param string $reviewHandling {@see PrivacyDeterminations::ANONYMISE} or ::DELETE.
     * @param int    $actorId        The administrator processing the request, for the audit
     *                               columns. Zero when it is not run from a request cycle.
     *
     * @return array<string, int|string>  A per-data-set tally, for the action log and the tests.
     *
     * @since 4.0.0
     */
    public function remove(int $userId, string $reviewHandling = PrivacyDeterminations::ANONYMISE, int $actorId = 0): array
    {
        if ($userId <= 0) {
            return [];
        }

        // Read before writing: once `owner` is cleared there is no way back to "which listings
        // were this person's", and two later steps need exactly that list.
        $ownedExtensions = $this->ownedExtensionIds($userId);

        $report = ['review_handling' => $reviewHandling];

        // Open handovers first, while the transfer rows still point at a live account. Both
        // parties are told, which is the whole reason this goes through TransferService rather
        // than an UPDATE - the other party has a listing hanging on the outcome.
        $report['transfers_cancelled'] = $this->cancelOpenTransfers($userId, $actorId);

        // The person's own speech.
        $report += $this->handleReviews($userId, $reviewHandling);
        $report['review_responses_cleared'] = $this->clearDeveloperResponses($ownedExtensions);

        // Assignments and preferences: nothing here is about software, so nothing here is kept.
        $report['favourites_deleted']       = $this->deleteWhere('#__jed_favorites', 'user_id', $userId);
        $report['maintainer_roles_deleted'] = $this->deleteWhere('#__jed_extensions_maintainers', 'user_id', $userId);
        $report['transfer_lookups_deleted'] = $this->deleteWhere('#__jed_transfer_lookups', 'user_id', $userId);

        // The listings themselves: block what they owned, then take the person out of every row
        // that names them - live rows and revisions alike.
        $report['extensions_blocked']  = $this->blockOwnedListings($ownedExtensions);
        $report['extensions_scrubbed'] = $this->scrubListingTable('#__jed_extensions', $userId);
        $report['revisions_scrubbed']  = $this->scrubListingTable('#__jed_extensions_history', $userId);

        // Authorship columns on the remaining tables.
        $report['media_scrubbed']      = $this->scrubAuthorship('#__jed_extensions_images', $userId)
            + $this->scrubAuthorship('#__jed_extensions_files', $userId);
        $report['url_checks_scrubbed']  = $this->nullOut('#__jed_url_checks', ['checked_by' => 0], $userId, ['checked_by']);
        $report['queue_jobs_scrubbed']  = $this->nullOut('#__jed_queue_jobs', ['created_by' => 0], $userId, ['created_by']);
        $report['invitations_scrubbed'] = $this->nullOut(
            '#__jed_extensions_maintainers',
            ['invited_by' => null],
            $userId,
            ['invited_by']
        );
        $report['review_bans_scrubbed'] = $this->nullOut('#__jed_user_review_bans', ['set_by' => null], $userId, ['set_by']);

        // Privileges and bans, per the rule above.
        $report['user_access'] = $this->handleUserAccess($userId);

        return $report;
    }

    /**
     * Ids of every listing the person owns, whatever state it is in.
     *
     * @param int $userId The subject of the request.
     *
     * @return int[]
     *
     * @since 4.0.0
     */
    private function ownedExtensionIds(int $userId): array
    {
        return array_map('intval', (array) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__jed_extensions'))
                ->where($this->db->quoteName('owner') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadColumn());
    }

    /**
     * Call off every handover the person is still party to.
     *
     * @param int $userId  The subject of the request.
     * @param int $actorId Who is processing the request.
     *
     * @return int  How many were cancelled.
     *
     * @since 4.0.0
     */
    private function cancelOpenTransfers(int $userId, int $actorId): int
    {
        $open = (array) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__jed_extension_transfers'))
                ->where(
                    '(' . $this->db->quoteName('from_user_id') . ' = :from'
                    . ' OR ' . $this->db->quoteName('to_user_id') . ' = :to)'
                )
                ->whereIn($this->db->quoteName('state'), ['pending', 'from_confirmed', 'to_confirmed'], ParameterType::STRING)
                ->bind([':from', ':to'], $userId, ParameterType::INTEGER)
        )->loadColumn();

        $transferService = new TransferService($this->db);

        foreach ($open as $transferId) {
            $transferService->cancel(
                (int) $transferId,
                $actorId,
                Text::_('COM_JED_TRANSFER_CANCELLED_PRIVACY_REMOVAL')
            );
        }

        return \count($open);
    }

    /**
     * Anonymise or delete the reviews the person wrote.
     *
     * Anonymising is the default because a review is two things at once: the reviewer's words,
     * and one of the numbers the extension's public score is made of. Deleting it silently
     * changes a rating that other people relied on. Where the JED team decides otherwise, the
     * deletion path re-derives the aggregates rather than leaving `score_count` lying - which is
     * what the queue's `extension.score_recalc` handler is for.
     *
     * @param int    $userId   The subject of the request.
     * @param string $handling {@see PrivacyDeterminations::ANONYMISE} or ::DELETE.
     *
     * @return array<string, int>
     *
     * @since 4.0.0
     */
    private function handleReviews(int $userId, string $handling): array
    {
        if ($handling !== PrivacyDeterminations::DELETE) {
            return [
                'reviews_anonymised' => $this->nullOut(
                    '#__jed_reviews',
                    ['created_by' => 0, 'ip_address' => '', 'checked_out' => null, 'checked_out_time' => null],
                    $userId,
                    ['created_by']
                ),
            ];
        }

        // The extensions whose score is about to change, before the rows they are computed from
        // are gone.
        $affected = array_map('intval', (array) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('DISTINCT ' . $this->db->quoteName('extension_id'))
                ->from($this->db->quoteName('#__jed_reviews'))
                ->where($this->db->quoteName('created_by') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadColumn());

        $deleted = $this->deleteWhere('#__jed_reviews', 'created_by', $userId);

        $queueService = new QueueService($this->db);

        foreach ($affected as $extensionId) {
            $queueService->enqueue('extension.score_recalc', $extensionId, null, [], 0);
        }

        return ['reviews_deleted' => $deleted, 'score_recalcs_queued' => \count($affected)];
    }

    /**
     * Clear the replies the person wrote as the developer under other people's reviews.
     *
     * Bounded by current ownership, because that is the only link the schema has: a response has
     * no author column of its own, only the listing it hangs from. A response written before the
     * listing changed hands is therefore out of reach here - noted in the results document as the
     * one gap that needs a schema change rather than a query.
     *
     * @param int[] $extensionIds The listings the person owns.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function clearDeveloperResponses(array $extensionIds): int
    {
        if ($extensionIds === []) {
            return 0;
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_reviews'))
                ->set($this->db->quoteName('developer_response') . ' = NULL')
                ->set($this->db->quoteName('developer_responded_on') . ' = NULL')
                ->set($this->db->quoteName('developer_response_published') . ' = 0')
                ->whereIn($this->db->quoteName('extension_id'), $extensionIds)
                ->where($this->db->quoteName('developer_response') . ' IS NOT NULL')
        )->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * Block the listings whose owner has just been erased.
     *
     * Only listings that are not already blocked and not already soft-deleted. Overwriting an
     * existing block would replace a moderation decision - "licensing violation" - with a
     * bookkeeping one and lose the reason the team acted on; a soft-deleted listing already
     * answers 410 and does not need a second, weaker fact recorded against it.
     *
     * @param int[] $extensionIds The listings the person owns.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function blockOwnedListings(array $extensionIds): int
    {
        if ($extensionIds === []) {
            return 0;
        }

        $now  = Factory::getDate()->toSql();
        $code = self::BLOCK_REASON_OWNER_WITHDRAWN;

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jed_extensions'))
            ->set($this->db->quoteName('blocked') . ' = 1')
            ->set($this->db->quoteName('block_reason_code') . ' = :code')
            // Internal note deliberately left empty rather than naming the person: the whole
            // point of the operation is that no personal data stays in the row (8.7 keeps this
            // field internal anyway, so this is belt and braces).
            ->set($this->db->quoteName('block_reason_text') . ' = NULL')
            // No acting administrator is recorded. The block is a consequence of the erasure,
            // not a judgement somebody made about the listing.
            ->set($this->db->quoteName('blocked_by') . ' = NULL')
            ->set($this->db->quoteName('blocked_time') . ' = :now')
            ->whereIn($this->db->quoteName('id'), $extensionIds)
            ->where($this->db->quoteName('blocked') . ' = 0')
            ->where($this->db->quoteName('deleted') . ' = 0')
            ->bind(':code', $code)
            ->bind(':now', $now);

        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * Take the person out of a listing table - live rows or revisions, same columns.
     *
     * @param string $table  `#__jed_extensions` or `#__jed_extensions_history`.
     * @param int    $userId The subject of the request.
     *
     * @return int  Rows touched, counting a row once per column it was named in.
     *
     * @since 4.0.0
     */
    private function scrubListingTable(string $table, int $userId): int
    {
        // The listing's contact details go with the owner. They are the developer's address and
        // website, not the software's, and 8.12 names both as personal data.
        $touched = $this->nullOut(
            $table,
            ['owner' => 0, 'developer_email' => '', 'developer_url' => ''],
            $userId,
            ['owner']
        );

        $touched += $this->scrubAuthorship($table, $userId);

        foreach (['approved_by', 'blocked_by', 'deleted_by'] as $column) {
            $touched += $this->nullOut($table, [$column => null], $userId, [$column]);
        }

        return $touched;
    }

    /**
     * Clear the `created_by` / `modified_by` / `checked_out` trio wherever it names the person.
     *
     * `created_by` is `NOT NULL` throughout this schema and `modified_by` is not, which is the
     * only reason these are three statements rather than one.
     *
     * @param string $table  The table.
     * @param int    $userId The subject of the request.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function scrubAuthorship(string $table, int $userId): int
    {
        return $this->nullOut($table, ['created_by' => 0], $userId, ['created_by'])
            + $this->nullOut($table, ['modified_by' => null], $userId, ['modified_by'])
            + $this->nullOut($table, ['checked_out' => null, 'checked_out_time' => null], $userId, ['checked_out']);
    }

    /**
     * Keep a ban that is still in force, drop everything else.
     *
     * @param int $userId The subject of the request.
     *
     * @return string  What was decided, for the tally.
     *
     * @since 4.0.0
     */
    private function handleUserAccess(int $userId): string
    {
        $row = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jed_user_access'))
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadAssoc();

        if ($row === null) {
            return 'absent';
        }

        if (self::banMustBeRetained($row, Factory::getDate()->toSql())) {
            // `set_by` is a third party - the administrator who imposed it - so it stays. What
            // goes is nothing: the row *is* the measure, and a measure with its justification
            // removed is one nobody could later review.
            return 'retained';
        }

        $this->deleteWhere('#__jed_user_access', 'user_id', $userId);

        return 'deleted';
    }

    /**
     * `UPDATE <table> SET <columns> WHERE <match column> = <user>`, for each match column.
     *
     * Columns the table does not have are skipped rather than being an error, and a match column
     * the table does not have skips the statement entirely - see {@see self::$columnCache}.
     *
     * @param string               $table        The table.
     * @param array<string, mixed> $columns      Column => new value. `null` writes SQL NULL.
     * @param int                  $userId       The subject of the request.
     * @param string[]             $matchColumns The columns that identify the person.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function nullOut(string $table, array $columns, int $userId, array $matchColumns): int
    {
        $present = $this->columnsOf($table);
        $touched = 0;

        $columns = array_intersect_key($columns, array_flip($present));

        if ($columns === []) {
            return 0;
        }

        foreach ($matchColumns as $matchColumn) {
            if (!\in_array($matchColumn, $present, true)) {
                continue;
            }

            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName($table))
                ->where($this->db->quoteName($matchColumn) . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER);

            foreach ($columns as $column => $value) {
                $query->set(
                    $this->db->quoteName($column) . ' = ' . ($value === null ? 'NULL' : $this->db->quote($value))
                );
            }

            $this->db->setQuery($query)->execute();

            $touched += $this->db->getAffectedRows();
        }

        return $touched;
    }

    /**
     * The column names of a table, asked once.
     *
     * @param string $table The table.
     *
     * @return string[]
     *
     * @since 4.0.0
     */
    private function columnsOf(string $table): array
    {
        if (!isset($this->columnCache[$table])) {
            $this->columnCache[$table] = array_keys((array) $this->db->getTableColumns($table, true));
        }

        return $this->columnCache[$table];
    }

    /**
     * `DELETE FROM <table> WHERE <column> = <user>`.
     *
     * @param string $table  The table.
     * @param string $column The column naming the person.
     * @param int    $userId The subject of the request.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function deleteWhere(string $table, string $column, int $userId): int
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName($table))
                ->where($this->db->quoteName($column) . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->execute();

        return $this->db->getAffectedRows();
    }
}
