<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Privacy;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Export and removal for the ticket system's two personal-data tables.
 *
 * The determination that separates this from `com_jed` is worth stating, because the two
 * components arrive at opposite defaults from the same rule. A listing survives an erasure
 * because it is a record *about software*, and a review survives it - anonymised - because a
 * public score is computed from it. A ticket is neither: it is correspondence *with* a person,
 * nothing aggregates from it, and once the person is erased it has no subject left. So the
 * default here is deletion, and anonymisation is the option rather than the other way round.
 *
 * What is never touched is somebody else's ticket. A person who worked on the JED team appears in
 * `allocated_to`, `modified_by` and as the author of replies on tickets that are not theirs;
 * those rows lose the attribution and keep the thread, because the requester of the ticket has a
 * conversation they are entitled to still have.
 *
 * @since 4.1.0
 */
final class TicketPrivacyService
{
    /**
     * Delete the person's tickets and their messages.
     *
     * @since 4.1.0
     */
    public const DELETE = 'delete';

    /**
     * Keep the threads, drop the attribution.
     *
     * @since 4.1.0
     */
    public const ANONYMISE = 'anonymise';

    /**
     * The determinations, in the same shape `com_jed` states its own.
     *
     * @var array<string, array{export: bool, handling: string, reason: string}>
     *
     * @since 4.1.0
     */
    public const IN_SCOPE = [
        '#__jed_tickets' => [
            'export'   => true,
            'handling' => self::DELETE,
            'reason'   => 'COM_TICKETS_PRIVACY_DETERMINATION_TICKETS',
        ],
        '#__jed_ticket_messages' => [
            'export'   => true,
            'handling' => self::DELETE,
            'reason'   => 'COM_TICKETS_PRIVACY_DETERMINATION_MESSAGES',
        ],
    ];

    /**
     * Configuration tables, maintained by the team, holding no data about a requester.
     *
     * They carry `created_by`, but so does `#__categories`, and core's privacy plugins leave that
     * alone for the same reason: it records who configured the site, not who used it.
     *
     * @var array<string, string>
     *
     * @since 4.1.0
     */
    public const OUT_OF_SCOPE = [
        '#__jed_ticket_categories'        => 'COM_TICKETS_PRIVACY_OUTOFSCOPE_CONFIGURATION',
        '#__jed_ticket_groups'            => 'COM_TICKETS_PRIVACY_OUTOFSCOPE_CONFIGURATION',
        '#__jed_ticket_linked_item_types' => 'COM_TICKETS_PRIVACY_OUTOFSCOPE_CONFIGURATION',
    ];

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Every ticket and message the person wrote or was assigned.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<string, array<int, array<string, mixed>>>  Domain name => rows.
     *
     * @since 4.1.0
     */
    public function collect(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return array_filter([
            'jed_tickets'         => $this->tickets($userId),
            'jed_ticket_messages' => $this->messages($userId),
        ]);
    }

    /**
     * Erase or anonymise the ticket data.
     *
     * @param int    $userId   The subject of the request.
     * @param string $handling {@see self::DELETE} or {@see self::ANONYMISE}.
     *
     * @return array<string, int|string>
     *
     * @since 4.1.0
     */
    public function remove(int $userId, string $handling = self::DELETE): array
    {
        if ($userId <= 0) {
            return [];
        }

        $report  = ['ticket_handling' => $handling];
        $ownIds  = $this->ownTicketIds($userId);

        if ($handling === self::ANONYMISE) {
            // The thread stays whole; only the name comes off it. Note that this leaves the free
            // text the person typed in place - if a requester signed their message, that
            // signature survives. It is why this is not the default.
            $report['tickets_anonymised']  = $this->update('#__jed_tickets', ['created_by' => 0], 'created_by', $userId);
            $report['messages_anonymised'] = $this->update('#__jed_ticket_messages', ['created_by' => 0], 'created_by', $userId);
        } else {
            $report['messages_deleted'] = $this->deleteMessagesOf($ownIds)
                + $this->delete('#__jed_ticket_messages', 'created_by', $userId);
            $report['tickets_deleted']  = $this->deleteTickets($ownIds);
        }

        // Staff roles on other people's tickets: the attribution goes, the ticket does not.
        $report['assignments_cleared'] = $this->update('#__jed_tickets', ['allocated_to' => 0], 'allocated_to', $userId)
            + $this->update('#__jed_tickets', ['modified_by' => null], 'modified_by', $userId)
            + $this->update('#__jed_tickets', ['checked_out' => null, 'checked_out_time' => null], 'checked_out', $userId);

        return $report;
    }

    /**
     * Tickets the person opened, was assigned or last touched.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.1.0
     */
    private function tickets(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jed_tickets'))
            ->where(
                '(' . $this->db->quoteName('created_by') . ' = :creator'
                . ' OR ' . $this->db->quoteName('allocated_to') . ' = :assignee'
                . ' OR ' . $this->db->quoteName('modified_by') . ' = :editor)'
            )
            ->bind([':creator', ':assignee', ':editor'], $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('id') . ' ASC');

        $rows = (array) $this->db->setQuery($query)->loadAssocList();

        // `internal_notes` is the team's working note on the case, not the requester's data, and
        // 8.7 keeps that kind of note internal. Withheld here for the same reason the audit
        // results are never public.
        foreach ($rows as &$row) {
            unset($row['internal_notes']);
        }

        unset($row);

        return $rows;
    }

    /**
     * Messages the person wrote, on any ticket.
     *
     * Internal messages are excluded: `internal = 1` marks a note the team wrote about a case
     * rather than a message to anybody, and it can quote a third party's case.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.1.0
     */
    private function messages(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jed_ticket_messages'))
            ->where($this->db->quoteName('created_by') . ' = :uid')
            ->where($this->db->quoteName('internal') . ' = 0')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('id') . ' ASC');

        return (array) $this->db->setQuery($query)->loadAssocList();
    }

    /**
     * Ids of the tickets the person opened, and of any ticket hanging off one of them.
     *
     * The child lookup is one level deep, which is what `parent_id` is used for - a follow-up
     * ticket split off an original. A deeper tree would need a recursive walk; there is none in
     * the data and inventing one would be inventing a feature.
     *
     * @param int $userId The subject of the request.
     *
     * @return int[]
     *
     * @since 4.1.0
     */
    private function ownTicketIds(int $userId): array
    {
        $ids = array_map('intval', (array) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__jed_tickets'))
                ->where($this->db->quoteName('created_by') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadColumn());

        if ($ids === []) {
            return [];
        }

        $children = array_map('intval', (array) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__jed_tickets'))
                ->whereIn($this->db->quoteName('parent_id'), $ids)
        )->loadColumn());

        return array_values(array_unique(array_merge($ids, $children)));
    }

    /**
     * Delete the tickets by id.
     *
     * @param int[] $ticketIds The tickets.
     *
     * @return int
     *
     * @since 4.1.0
     */
    private function deleteTickets(array $ticketIds): int
    {
        if ($ticketIds === []) {
            return 0;
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_tickets'))
                ->whereIn($this->db->quoteName('id'), $ticketIds)
        )->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * Delete every message on the given tickets, whoever wrote it.
     *
     * Including the team's replies. They were written to this person about this person's case;
     * leaving them behind attached to a ticket that no longer exists would keep the content while
     * losing the context that makes it readable.
     *
     * @param int[] $ticketIds The tickets.
     *
     * @return int
     *
     * @since 4.1.0
     */
    private function deleteMessagesOf(array $ticketIds): int
    {
        if ($ticketIds === []) {
            return 0;
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_ticket_messages'))
                ->whereIn($this->db->quoteName('ticket_id'), $ticketIds)
        )->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * `UPDATE <table> SET <columns> WHERE <column> = <user>`.
     *
     * @param string               $table   The table.
     * @param array<string, mixed> $columns Column => new value. `null` writes SQL NULL.
     * @param string               $match   The column naming the person.
     * @param int                  $userId  The subject of the request.
     *
     * @return int
     *
     * @since 4.1.0
     */
    private function update(string $table, array $columns, string $match, int $userId): int
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName($table))
            ->where($this->db->quoteName($match) . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER);

        foreach ($columns as $column => $value) {
            $query->set($this->db->quoteName($column) . ' = ' . ($value === null ? 'NULL' : $this->db->quote($value)));
        }

        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
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
     * @since 4.1.0
     */
    private function delete(string $table, string $column, int $userId): int
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
