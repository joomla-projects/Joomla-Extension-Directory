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

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Everything the JED holds about one person, as rows.
 *
 * Deliberately not a privacy plugin: the plugin turns these arrays into `com_privacy` domains and
 * does nothing else. Which tables hold personal data, which columns identify a person, and what
 * has to be withheld are decisions about the JED's data model, and they belong next to that model
 * where they can be unit-tested without a request cycle.
 *
 * **No visibility filtering anywhere in here.** Soft-deleted listings, blocked listings, revisions
 * of listings that were never approved - all of it is exported. 8.12 is explicit that the
 * soft delete must not become a place where data quietly sits outside the export and deletion
 * path, and that is exactly where it would end up if these queries reused the browse conditions.
 *
 * @since 4.0.0
 */
final class PrivacyExportService
{
    /**
     * Columns never handed back, per table.
     *
     * Two reasons only, and both are narrow. A hash of a *third party's* address is not the
     * requester's data and returning it would turn an export into an oracle; a token hash is a
     * credential, which is why the core user plugin withholds `password` in the same way.
     *
     * @var array<string, string[]>
     *
     * @since 4.0.0
     */
    private const WITHHELD = [
        '#__jed_extension_transfers' => ['from_token_hash', 'to_token_hash'],
    ];

    /**
     * Column names per table, asked once. See {@see self::media()} for why this is needed.
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
     * Collect every data set the JED holds for a user.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<string, array<int, array<string, mixed>>>  Domain name => rows.
     *
     * @since 4.0.0
     */
    public function collect(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return array_filter([
            'jed_extensions'          => $this->extensions($userId),
            'jed_extension_revisions' => $this->revisions($userId),
            'jed_maintainer_roles'    => $this->maintainerRoles($userId),
            'jed_extension_media'     => $this->media($userId),
            'jed_reviews'             => $this->reviews($userId),
            'jed_review_responses'    => $this->reviewResponses($userId),
            'jed_favourites'          => $this->favourites($userId),
            'jed_privileges_and_bans' => $this->privileges($userId),
            'jed_review_bans'         => $this->reviewBans($userId),
            'jed_ownership_transfers' => $this->transfers($userId),
            'jed_url_checks'          => $this->urlChecks($userId),
        ]);
    }

    /**
     * Listings the person owns, created or last edited - soft-deleted ones included.
     *
     * The three relationships are one data set rather than three, because a listing is one thing
     * and a person can stand in more than one relationship to it at once. The synthetic
     * `jed_relationship` column says which, so the export answers "why is this here" without the
     * reader having to compare user ids across columns.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function extensions(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jed_extensions'))
            ->where(
                '(' . $this->db->quoteName('owner') . ' = :owner'
                . ' OR ' . $this->db->quoteName('created_by') . ' = :creator'
                . ' OR ' . $this->db->quoteName('modified_by') . ' = :editor)'
            )
            ->bind([':owner', ':creator', ':editor'], $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('id') . ' ASC');

        return $this->annotateRelationship($this->rows($query, '#__jed_extensions'), $userId);
    }

    /**
     * Revisions of those listings that carry the person in one of the same three columns.
     *
     * The revision table mirrors the live row, so a `developer_email` erased from the live row but
     * left in twenty revisions would not be erased at all.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function revisions(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jed_extensions_history'))
            ->where(
                '(' . $this->db->quoteName('owner') . ' = :owner'
                . ' OR ' . $this->db->quoteName('created_by') . ' = :creator'
                . ' OR ' . $this->db->quoteName('modified_by') . ' = :editor)'
            )
            ->bind([':owner', ':creator', ':editor'], $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('id') . ' ASC');

        return $this->annotateRelationship($this->rows($query, '#__jed_extensions_history'), $userId);
    }

    /**
     * Maintainer invitations and accepted maintainer roles, in both directions.
     *
     * `invited_by` is included because being the person who invited somebody is also a record
     * about the inviter, and it is the only trace of an invitation that was declined.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function maintainerRoles(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select(['m.*', $this->db->quoteName('e.name', 'extension_name')])
            ->from($this->db->quoteName('#__jed_extensions_maintainers', 'm'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__jed_extensions', 'e'),
                $this->db->quoteName('e.id') . ' = ' . $this->db->quoteName('m.extension_id')
            )
            ->where(
                '(' . $this->db->quoteName('m.user_id') . ' = :maintainer'
                . ' OR ' . $this->db->quoteName('m.invited_by') . ' = :inviter)'
            )
            ->bind([':maintainer', ':inviter'], $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('m.extension_id') . ' ASC');

        return $this->rows($query, '#__jed_extensions_maintainers');
    }

    /**
     * Screenshots and files uploaded under the person's account.
     *
     * The two tables answer the same question - what did this person upload - so they are one
     * data set with a `kind` column rather than two domains a reader has to correlate.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function media(int $userId): array
    {
        $out = [];

        foreach (['image' => '#__jed_extensions_images', 'file' => '#__jed_extensions_files'] as $kind => $table) {
            // `#__jed_extensions_files` has no `modified_by`, and an installation older than the
            // current schema can be missing more than that. An export that dies on a column that
            // is not there returns nothing at all, which is the worst of the available outcomes -
            // so the condition is built from the columns the table actually has.
            $columns = array_values(array_intersect(['created_by', 'modified_by'], $this->columnsOf($table)));

            if ($columns === []) {
                continue;
            }

            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName($table))
                ->order($this->db->quoteName('id') . ' ASC');

            $conditions = [];

            foreach ($columns as $index => $column) {
                $conditions[] = $this->db->quoteName($column) . ' = :media' . $index;
                $query->bind(':media' . $index, $userId, ParameterType::INTEGER);
            }

            $query->where('(' . implode(' OR ', $conditions) . ')');

            foreach ($this->rows($query, $table) as $row) {
                $out[] = ['kind' => $kind] + $row;
            }
        }

        return $out;
    }

    /**
     * Reviews the person wrote, published or not.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function reviews(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select(['r.*', $this->db->quoteName('e.name', 'extension_name')])
            ->from($this->db->quoteName('#__jed_reviews', 'r'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__jed_extensions', 'e'),
                $this->db->quoteName('e.id') . ' = ' . $this->db->quoteName('r.extension_id')
            )
            ->where($this->db->quoteName('r.created_by') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('r.id') . ' ASC');

        return $this->rows($query, '#__jed_reviews');
    }

    /**
     * Replies the person wrote as a developer, under reviews of listings they own.
     *
     * A separate data set from the reviews above because it is separate speech: the review is
     * somebody else's, only the response column is the requester's, and returning the whole
     * review row here would hand back a third party's words.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function reviewResponses(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('r.id', 'review_id'),
                $this->db->quoteName('r.extension_id'),
                $this->db->quoteName('e.name', 'extension_name'),
                $this->db->quoteName('r.developer_response'),
                $this->db->quoteName('r.developer_responded_on'),
                $this->db->quoteName('r.developer_response_published'),
            ])
            ->from($this->db->quoteName('#__jed_reviews', 'r'))
            ->join(
                'INNER',
                $this->db->quoteName('#__jed_extensions', 'e'),
                $this->db->quoteName('e.id') . ' = ' . $this->db->quoteName('r.extension_id')
            )
            ->where($this->db->quoteName('e.owner') . ' = :uid')
            ->where($this->db->quoteName('r.developer_response') . ' IS NOT NULL')
            ->where($this->db->quoteName('r.developer_response') . ' <> ' . $this->db->quote(''))
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('r.id') . ' ASC');

        return $this->rows($query, '#__jed_reviews');
    }

    /**
     * Bookmarked listings.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function favourites(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select(['f.*', $this->db->quoteName('e.name', 'extension_name')])
            ->from($this->db->quoteName('#__jed_favorites', 'f'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__jed_extensions', 'e'),
                $this->db->quoteName('e.id') . ' = ' . $this->db->quoteName('f.extension_id')
            )
            ->where($this->db->quoteName('f.user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('f.id') . ' ASC');

        return $this->rows($query, '#__jed_favorites');
    }

    /**
     * Per-user privileges and the ban record, if either was ever decided for this account.
     *
     * An absent row is the normal case and means "default privileges, not banned" - there is
     * nothing to export then, which is why this can come back empty.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function privileges(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jed_user_access'))
            ->where($this->db->quoteName('user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER);

        return $this->rows($query, '#__jed_user_access');
    }

    /**
     * Bans from reviewing a particular developer or category.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function reviewBans(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jed_user_review_bans'))
            ->where($this->db->quoteName('user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('target_type') . ' ASC');

        return $this->rows($query, '#__jed_user_review_bans');
    }

    /**
     * Handovers the person was party to, in either direction, at any stage.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function transfers(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select(['t.*', $this->db->quoteName('e.name', 'extension_name')])
            ->from($this->db->quoteName('#__jed_extension_transfers', 't'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__jed_extensions', 'e'),
                $this->db->quoteName('e.id') . ' = ' . $this->db->quoteName('t.extension_id')
            )
            ->where(
                '(' . $this->db->quoteName('t.from_user_id') . ' = :from'
                . ' OR ' . $this->db->quoteName('t.to_user_id') . ' = :to'
                . ' OR ' . $this->db->quoteName('t.initiated_by') . ' = :by)'
            )
            ->bind([':from', ':to', ':by'], $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('t.id') . ' ASC');

        return $this->rows($query, '#__jed_extension_transfers');
    }

    /**
     * URL checks the person triggered by typing into a form.
     *
     * @param int $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function urlChecks(int $userId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jed_url_checks'))
            ->where($this->db->quoteName('checked_by') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('id') . ' ASC');

        return $this->rows($query, '#__jed_url_checks');
    }

    /**
     * Run a query and strip whatever that table withholds.
     *
     * @param \Joomla\Database\QueryInterface $query The query to run.
     * @param string                          $table The table it reads, for the withheld list.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function rows($query, string $table): array
    {
        $rows = $this->db->setQuery($query)->loadAssocList() ?: [];

        foreach (self::WITHHELD[$table] ?? [] as $column) {
            foreach ($rows as &$row) {
                unset($row[$column]);
            }

            unset($row);
        }

        return $rows;
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
     * Say in the export why a listing row is in it.
     *
     * @param array<int, array<string, mixed>> $rows   The listing or revision rows.
     * @param int                              $userId The subject of the request.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function annotateRelationship(array $rows, int $userId): array
    {
        foreach ($rows as &$row) {
            $roles = [];

            if ((int) ($row['owner'] ?? 0) === $userId) {
                $roles[] = 'owner';
            }

            if ((int) ($row['created_by'] ?? 0) === $userId) {
                $roles[] = 'created';
            }

            if ((int) ($row['modified_by'] ?? 0) === $userId) {
                $roles[] = 'modified';
            }

            $row = ['jed_relationship' => implode(', ', $roles)] + $row;
        }

        unset($row);

        return $rows;
    }
}
