<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * Runs the one time migration of old JED accounts to portal GUIDs.
 *
 * Reads the accounts, plans with {@see MigrationPlanner}, and either reports the plan (the default)
 * or writes it. Writing is one account at a time, each in its own transaction, and checks again
 * just before: the username must still be what the plan saw and the GUID must still be free. A run
 * that is interrupted can simply be started again; accounts already done show up as already linked.
 *
 * @since 4.0.0
 */
final class AccountMigrator
{
    /**
     * @param DatabaseInterface $db The database.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Plan the migration of an export, without writing anything.
     *
     * @param array<int, array{guid: string, email: string}> $portal The parsed export.
     *
     * @return array{rename: array, skipped: array}
     *
     * @since 4.0.0
     */
    public function plan(array $portal): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'username', 'email', 'block']))
            ->from($this->db->quoteName('#__users'));

        return MigrationPlanner::plan($this->db->setQuery($query)->loadAssocList() ?: [], $portal);
    }

    /**
     * Write a plan.
     *
     * @param array<int, array{user_id: int, from: string, guid: string}> $renames The rename list of a plan.
     *
     * @return array{done: int, failed: array<int, array{user_id: int, guid: string, why: string}>}
     *
     * @since 4.0.0
     */
    public function apply(array $renames): array
    {
        $links  = new IdentityLinks($this->db);
        $report = ['done' => 0, 'failed' => []];

        foreach ($renames as $rename) {
            $why = $this->applyOne($links, (int) $rename['user_id'], (string) $rename['from'], (string) $rename['guid']);

            if ($why === null) {
                $report['done']++;
            } else {
                $report['failed'][] = ['user_id' => (int) $rename['user_id'], 'guid' => $rename['guid'], 'why' => $why];
            }
        }

        return $report;
    }

    /**
     * Rename one account and tie it to its GUID.
     *
     * @param IdentityLinks $links  The link store.
     * @param int           $userId The account.
     * @param string        $from   The username the plan saw.
     * @param string        $guid   The GUID to take.
     *
     * @return string|null Null on success, otherwise why it was not done.
     *
     * @since 4.0.0
     */
    private function applyOne(IdentityLinks $links, int $userId, string $from, string $guid): ?string
    {
        $guid = Guid::normalise($guid);

        $this->db->transactionStart();

        try {
            if ($this->usernameTaken($guid)) {
                $this->db->transactionRollback();

                return 'the GUID is already a username';
            }

            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__users'))
                    ->set($this->db->quoteName('username') . ' = :guid')
                    ->where($this->db->quoteName('id') . ' = :uid')
                    ->where($this->db->quoteName('username') . ' = :from')
                    ->bind(':guid', $guid)
                    ->bind(':uid', $userId, ParameterType::INTEGER)
                    ->bind(':from', $from)
            )->execute();

            if ($this->db->getAffectedRows() !== 1) {
                $this->db->transactionRollback();

                return 'the account changed since the plan was made';
            }

            if (!$links->link($userId, $guid, IdentityLinks::SOURCE_MIGRATION)) {
                $this->db->transactionRollback();

                return 'the account or the GUID is already linked';
            }

            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();

            return $e->getMessage();
        }

        return null;
    }

    /**
     * Whether any account already uses this value as its username.
     *
     * @param string $username The username.
     *
     * @return boolean
     *
     * @since 4.0.0
     */
    private function usernameTaken(string $username): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('username') . ' = :username')
            ->bind(':username', $username)
            ->setLimit(1);

        return (bool) $this->db->setQuery($query)->loadResult();
    }
}
