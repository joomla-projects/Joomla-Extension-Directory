<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Access;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * The one gate every write path asks before letting a user act.
 *
 * 7.1 calls the absence of this "the biggest overlooked gap": without it the JED team's only
 * tool against a problematic user is blocking their Joomla account, which also cuts off the
 * legitimate half of what they do.
 *
 * Two rules are worth stating because getting either wrong is silent:
 *
 *  - **An absent row means full privileges and no ban.** Most people never get a row, and
 *    requiring one would mean every new registration starts unable to do anything.
 *  - **A ban is evaluated against now, not read as a boolean.** `banned = 1` with a
 *    `banned_until` in the past is not a ban. Making that depend on a cleanup job would leave
 *    people banned whenever the job failed - the wrong way round for something to fail.
 *
 * @since 4.0.0
 */
final class JedAccessHelper
{
    /**
     * Per-request cache of the access rows already read.
     *
     * A single review submission asks about the same user several times over; the row does not
     * change inside one request.
     *
     * @var array<int, array|null>
     *
     * @since 4.0.0
     */
    private static array $cache = [];

    /**
     * Whether a user currently holds a privilege.
     *
     * @param int       $userId    The user.
     * @param Privilege $privilege What they want to do.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function may(int $userId, Privilege $privilege): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $row = self::row($userId);

        if ($row === null) {
            return true;
        }

        if (self::banIsInForce($row)) {
            return false;
        }

        return (int) ($row[$privilege->value] ?? 1) === 1;
    }

    /**
     * The same question, answered by throwing.
     *
     * For the write paths, where the alternative is every caller repeating the same three lines
     * and one of them eventually getting the negation the wrong way round.
     *
     * The message names the reason when there is one: 8.8 asks for a banned user to be told why
     * rather than shown a generic error, because "you cannot do that" with no reason produces a
     * support ticket every time.
     *
     * @param int       $userId    The user.
     * @param Privilege $privilege What they want to do.
     *
     * @return void
     *
     * @throws RuntimeException  When the privilege is not held.
     *
     * @since 4.0.0
     */
    public static function assertMay(int $userId, Privilege $privilege): void
    {
        if (self::may($userId, $privilege)) {
            return;
        }

        $row    = self::row($userId);
        $reason = trim((string) ($row['banned_reason'] ?? ''));

        if ($row !== null && self::banIsInForce($row)) {
            throw new RuntimeException(
                $reason !== ''
                    ? Text::sprintf('COM_JED_ACCESS_BANNED_WITH_REASON', $reason)
                    : Text::_('COM_JED_ACCESS_BANNED')
            );
        }

        throw new RuntimeException(Text::_($privilege->deniedMessage()));
    }

    /**
     * Whether a user's submissions skip the moderation gate.
     *
     * The trusted status from 7.1. `P1-02` still records the approval and would still log it -
     * an automatic approval is an approval, and the record is how anyone finds out later that it
     * happened without a person looking.
     *
     * A banned user is never trusted, whatever the flag says. The two are set independently and
     * the combination is reachable.
     *
     * @param int  $userId      The user.
     * @param bool $forReviews  True for reviews, false for listings.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function isTrusted(int $userId, bool $forReviews = false): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $row = self::row($userId);

        if ($row === null || self::banIsInForce($row)) {
            return false;
        }

        return (int) ($row[$forReviews ? 'auto_approve_reviews' : 'auto_approve_extensions'] ?? 0) === 1;
    }

    /**
     * Whether a user is barred from reviewing this particular extension.
     *
     * Two ways in: barred from the extension's owner, or from any category it sits in. Checked
     * against the category *map*, not only `catid`, because a listing can be in several and a
     * ban on one of them should hold.
     *
     * @param int $userId      The user.
     * @param int $extensionId The extension they want to review.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function isBarredFromReviewing(int $userId, int $extensionId): bool
    {
        if ($userId <= 0 || $extensionId <= 0) {
            return false;
        }

        $db = self::db();

        $count = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__jed_user_review_bans', 'b'))
                ->where($db->quoteName('b.user_id') . ' = :uid')
                ->where(
                    '('
                    . '(' . $db->quoteName('b.target_type') . ' = ' . $db->quote('developer')
                    . ' AND ' . $db->quoteName('b.target_id') . ' = ('
                    . 'SELECT ' . $db->quoteName('owner') . ' FROM ' . $db->quoteName('#__jed_extensions')
                    . ' WHERE ' . $db->quoteName('id') . ' = :eid1))'
                    . ' OR '
                    . '(' . $db->quoteName('b.target_type') . ' = ' . $db->quote('category')
                    . ' AND ' . $db->quoteName('b.target_id') . ' IN ('
                    . 'SELECT ' . $db->quoteName('catid') . ' FROM ' . $db->quoteName('#__jed_extensions_category_map')
                    . ' WHERE ' . $db->quoteName('extension_id') . ' = :eid2'
                    . ' UNION SELECT ' . $db->quoteName('catid') . ' FROM ' . $db->quoteName('#__jed_extensions')
                    . ' WHERE ' . $db->quoteName('id') . ' = :eid3))'
                    . ')'
                )
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':eid1', $extensionId, ParameterType::INTEGER)
                ->bind(':eid2', $extensionId, ParameterType::INTEGER)
                ->bind(':eid3', $extensionId, ParameterType::INTEGER)
        )->loadResult();

        return $count > 0;
    }

    /**
     * The review gate, as one call: may this user review, and this listing in particular?
     *
     * @param int $userId      The user.
     * @param int $extensionId The extension.
     *
     * @return void
     *
     * @throws RuntimeException  When they may not.
     *
     * @since 4.0.0
     */
    public static function assertMayReview(int $userId, int $extensionId): void
    {
        self::assertMay($userId, Privilege::REVIEW);

        if (self::isBarredFromReviewing($userId, $extensionId)) {
            throw new RuntimeException(Text::_('COM_JED_ACCESS_DENIED_REVIEW_TARGET'));
        }
    }

    /**
     * Whether an address falls in a range somebody flagged.
     *
     * Advisory (item 8): callers record the answer, they do not act on it. A shared NAT range is
     * not evidence, and blocking on one would lock out everybody behind it.
     *
     * @param string $ip The address.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function isSuspectAddress(string $ip): bool
    {
        $ip = trim($ip);

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $db = self::db();

        return (bool) $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_suspect_ip_ranges'))
                ->where($db->quoteName('state') . ' = 1')
                ->where('INET6_ATON(:ip1) BETWEEN ' . $db->quoteName('range_start') . ' AND ' . $db->quoteName('range_end'))
                ->bind(':ip1', $ip),
            0,
            1
        )->loadResult();
    }

    /**
     * Forget the cached rows. For tests and for code that has just written one.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Whether this row's ban applies right now.
     *
     * `banned_from` in the future means it has not started; `banned_until` in the past means it
     * has run out. A null on either side means "no boundary that way", which is how a permanent
     * ban is expressed.
     *
     * @param array $row The access row.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private static function banIsInForce(array $row): bool
    {
        if ((int) ($row['banned'] ?? 0) !== 1) {
            return false;
        }

        $now  = time();
        $from = $row['banned_from'] ?? null;
        $till = $row['banned_until'] ?? null;

        if ($from !== null && strtotime((string) $from) > $now) {
            return false;
        }

        if ($till !== null && strtotime((string) $till) < $now) {
            return false;
        }

        return true;
    }

    /**
     * The access row for a user, or null when there is none.
     *
     * @param int $userId The user.
     *
     * @return array|null
     *
     * @since 4.0.0
     */
    private static function row(int $userId): ?array
    {
        if (\array_key_exists($userId, self::$cache)) {
            return self::$cache[$userId];
        }

        $db = self::db();

        return self::$cache[$userId] = $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__jed_user_access'))
                ->where($db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadAssoc();
    }

    /**
     * @return DatabaseInterface
     *
     * @since 4.0.0
     */
    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
