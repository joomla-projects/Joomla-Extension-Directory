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

/**
 * Decides which old JED accounts take which portal GUID, before anything is written.
 *
 * The rule is the one agreed with the portal team: an old account is matched by email address, and
 * the match becomes a change of username to the GUID. The account keeps its id, so everything that
 * points at it (listings, reviews, favourites, tickets, transfers) stays attached.
 *
 * The planner is deliberately cautious. Anything that is not a clear one-to-one match is left out
 * with a reason, because a wrong match hands one person's listings to another and cannot be told
 * apart from a right one afterwards. Left out is not lost: the person signs in, gets a new account,
 * and can still claim the old one with the mailed link.
 *
 * @since 4.0.0
 */
final class MigrationPlanner
{
    public const NO_MATCH             = 'no_match';
    public const ALREADY_LINKED       = 'already_linked';
    public const DUPLICATE_GUID       = 'duplicate_guid';
    public const DUPLICATE_EMAIL      = 'duplicate_portal_email';
    public const AMBIGUOUS_JED_EMAIL  = 'ambiguous_jed_email';
    public const GUID_TAKEN           = 'guid_taken';
    public const OTHER_GUID           = 'account_has_other_guid';

    /**
     * Work out the renames.
     *
     * @param array<int, array{id: int, username: string, email: string, block?: int}> $users  The JED accounts.
     * @param array<int, array{guid: string, email: string}>                          $portal The portal export.
     *
     * @return array{rename: array<int, array{user_id: int, from: string, guid: string, email: string, blocked: bool}>, skipped: array<int, array{reason: string, guid: string, email: string, user_id: int|null}>}
     *
     * @since 4.0.0
     */
    public static function plan(array $users, array $portal): array
    {
        $guidCount  = [];
        $emailCount = [];

        foreach ($portal as $row) {
            $guidCount[$row['guid']]   = ($guidCount[$row['guid']] ?? 0) + 1;
            $emailCount[$row['email']] = ($emailCount[$row['email']] ?? 0) + 1;
        }

        $byEmail    = [];
        $byUsername = [];

        foreach ($users as $user) {
            $byEmail[strtolower((string) $user['email'])][]     = $user;
            $byUsername[strtolower((string) $user['username'])] = $user;
        }

        $plan = ['rename' => [], 'skipped' => []];
        $used = [];

        foreach ($portal as $row) {
            $skip = static function (string $reason, ?int $userId = null) use (&$plan, $row): void {
                $plan['skipped'][] = ['reason' => $reason, 'guid' => $row['guid'], 'email' => $row['email'], 'user_id' => $userId];
            };

            if ($guidCount[$row['guid']] > 1) {
                $skip(self::DUPLICATE_GUID);

                continue;
            }

            if ($emailCount[$row['email']] > 1) {
                $skip(self::DUPLICATE_EMAIL);

                continue;
            }

            $holder = $byUsername[$row['guid']] ?? null;

            if ($holder !== null) {
                $skip(strtolower((string) $holder['email']) === $row['email'] ? self::ALREADY_LINKED : self::GUID_TAKEN, (int) $holder['id']);

                continue;
            }

            $matches = $byEmail[$row['email']] ?? [];

            if (!$matches) {
                $skip(self::NO_MATCH);

                continue;
            }

            if (\count($matches) > 1) {
                $skip(self::AMBIGUOUS_JED_EMAIL);

                continue;
            }

            $user = $matches[0];

            if (Guid::isValid((string) $user['username'])) {
                $skip(self::OTHER_GUID, (int) $user['id']);

                continue;
            }

            if (isset($used[(int) $user['id']])) {
                $skip(self::AMBIGUOUS_JED_EMAIL, (int) $user['id']);

                continue;
            }

            $used[(int) $user['id']] = true;
            $plan['rename'][]        = [
                'user_id' => (int) $user['id'],
                'from'    => (string) $user['username'],
                'guid'    => $row['guid'],
                'email'   => $row['email'],
                'blocked' => !empty($user['block']),
            ];
        }

        return $plan;
    }
}
