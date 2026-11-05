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

use Joomla\CMS\Access\Access;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Which accounts hold staff powers.
 *
 * Portal sign-in is meant for ordinary users: the portal's addresses are not verified, and its own
 * second factor policy is not the JED's. So an account that can log in to the backend, administer
 * Joomla, or manage the JED is never moved onto a portal identity automatically and never erased by
 * a ping. Those are handled by a person.
 *
 * @since 4.0.0
 */
final class PrivilegedAccounts
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
     * Whether one account holds staff powers.
     *
     * @param int $userId The account.
     *
     * @return boolean
     *
     * @since 4.0.0
     */
    public function isPrivileged(int $userId): bool
    {
        return isset($this->among([$userId])[$userId]);
    }

    /**
     * The accounts among a list that hold staff powers. With no list, every account.
     *
     * @param int[]|null $userIds The accounts to look at, or null for all.
     *
     * @return array<int, true> Keyed by user id.
     *
     * @since 4.0.0
     */
    public function among(?array $userIds = null): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['user_id', 'group_id']))
            ->from($this->db->quoteName('#__user_usergroup_map'));

        if ($userIds !== null) {
            if (!$userIds) {
                return [];
            }

            $userIds = array_map('intval', $userIds);
            $query->whereIn($this->db->quoteName('user_id'), $userIds, ParameterType::INTEGER);
        }

        $privilegedGroup = [];
        $result          = [];

        foreach ($this->db->setQuery($query)->loadAssocList() ?: [] as $row) {
            $group = (int) $row['group_id'];

            $privilegedGroup[$group] ??= self::groupIsPrivileged($group);

            if ($privilegedGroup[$group]) {
                $result[(int) $row['user_id']] = true;
            }
        }

        return $result;
    }

    /**
     * Whether a group can log in to the backend, administer Joomla, or manage the JED.
     *
     * @param int $groupId The group.
     *
     * @return boolean
     *
     * @since 4.0.0
     */
    private static function groupIsPrivileged(int $groupId): bool
    {
        return Access::checkGroup($groupId, 'core.login.admin')
            || Access::checkGroup($groupId, 'core.admin')
            || Access::checkGroup($groupId, 'core.manage', 'com_jed')
            || Access::checkGroup($groupId, 'core.admin', 'com_jed');
    }
}
