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
 * The rules for claiming an old JED account, apart from the database and the mail.
 *
 * @since 4.0.0
 */
final class ClaimRules
{
    public const NOT_FOUND     = 'not_found';
    public const SAME_ACCOUNT  = 'same_account';
    public const PORTAL_USER   = 'already_portal_account';
    public const BLOCKED       = 'blocked';
    public const NO_EMAIL      = 'no_email';
    public const LINKED        = 'already_linked';
    public const NO_CLAIM      = 'no_claim';
    public const EXPIRED       = 'expired';
    public const WRONG_TOKEN   = 'wrong_token';

    /**
     * Whether an old account may be claimed by the new one.
     *
     * @param array{id: int, username: string, email: string, block?: int}|null $old      The old account.
     * @param int                                                               $newUserId The account claiming it.
     * @param boolean                                                           $linked    Whether the old account is already tied to a GUID.
     *
     * @return string|null Null when it may be claimed, otherwise why not.
     *
     * @since 4.0.0
     */
    public static function oldAccountProblem(?array $old, int $newUserId, bool $linked): ?string
    {
        if ($old === null) {
            return self::NOT_FOUND;
        }

        if ((int) $old['id'] === $newUserId) {
            return self::SAME_ACCOUNT;
        }

        if (Guid::isValid((string) $old['username'])) {
            return self::PORTAL_USER;
        }

        if ($linked) {
            return self::LINKED;
        }

        if (!empty($old['block'])) {
            return self::BLOCKED;
        }

        if (trim((string) $old['email']) === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            return self::NO_EMAIL;
        }

        return null;
    }

    /**
     * Whether a link that was opened is good.
     *
     * @param array{token_hash: string, expires: string}|null $claim The open claim of the person opening it.
     * @param string                                          $token The token from the link.
     * @param string                                          $now   The current UTC time, Y-m-d H:i:s.
     *
     * @return string|null Null when it is good, otherwise why not.
     *
     * @since 4.0.0
     */
    public static function tokenProblem(?array $claim, string $token, string $now): ?string
    {
        if ($claim === null) {
            return self::NO_CLAIM;
        }

        if ($claim['expires'] < $now) {
            return self::EXPIRED;
        }

        if ($token === '' || !hash_equals((string) $claim['token_hash'], self::hashToken($token))) {
            return self::WRONG_TOKEN;
        }

        return null;
    }

    /**
     * The form in which a token is stored.
     *
     * @param string $token The token.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
