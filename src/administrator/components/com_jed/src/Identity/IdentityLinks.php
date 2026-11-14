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
 * The tie between a JED account and a portal GUID.
 *
 * One to one in both directions: an account has at most one GUID and a GUID belongs to at most one
 * account. Both are unique keys in the table, so a race between two requests cannot break it; the
 * methods here report a refusal as `false` instead of letting the database error escape.
 *
 * @since 4.0.0
 */
final class IdentityLinks
{
    public const SOURCE_LOGIN     = 'login';
    public const SOURCE_MIGRATION = 'migration';
    public const SOURCE_CLAIM     = 'claim';
    public const SOURCE_MANUAL    = 'manual';

    /**
     * @param DatabaseInterface $db The database.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * The account a GUID belongs to.
     *
     * @param string $guid The portal GUID.
     *
     * @return int|null
     *
     * @since 4.0.0
     */
    public function userIdForGuid(string $guid): ?int
    {
        $guid  = Guid::normalise($guid);
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('user_id'))
            ->from($this->db->quoteName('#__jed_identity_links'))
            ->where($this->db->quoteName('guid') . ' = :guid')
            ->bind(':guid', $guid);

        $id = $this->db->setQuery($query)->loadResult();

        return $id === null ? null : (int) $id;
    }

    /**
     * The GUID an account belongs to.
     *
     * @param int $userId The JED user id.
     *
     * @return string|null
     *
     * @since 4.0.0
     */
    public function guidForUser(int $userId): ?string
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('guid'))
            ->from($this->db->quoteName('#__jed_identity_links'))
            ->where($this->db->quoteName('user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER);

        $guid = $this->db->setQuery($query)->loadResult();

        return $guid === null ? null : (string) $guid;
    }

    /**
     * Tie an account to a GUID.
     *
     * Linking the pair that is already linked is a success, so callers can repeat it. Anything
     * that would give an account a second GUID, or a GUID a second account, is refused.
     *
     * @param int    $userId The JED user id.
     * @param string $guid   The portal GUID.
     * @param string $source One of the SOURCE_ constants.
     *
     * @return boolean
     *
     * @since 4.0.0
     */
    public function link(int $userId, string $guid, string $source): bool
    {
        $guid = Guid::normalise($guid);

        if ($userId <= 0 || !Guid::isValid($guid)) {
            return false;
        }

        $owner = $this->userIdForGuid($guid);
        $has   = $this->guidForUser($userId);

        if ($owner === $userId && $has === $guid) {
            return true;
        }

        if ($owner !== null || $has !== null) {
            return false;
        }

        $row = (object) [
            'user_id' => $userId,
            'guid'    => $guid,
            'source'  => $source,
            'linked'  => gmdate('Y-m-d H:i:s'),
        ];

        try {
            $this->db->insertObject('#__jed_identity_links', $row);
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Remove an account's tie, for example when an admin unlinks it by hand.
     *
     * @param int $userId The JED user id.
     *
     * @return boolean Whether a row was removed.
     *
     * @since 4.0.0
     */
    public function unlink(int $userId): bool
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_identity_links'))
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->execute();

        return $this->db->getAffectedRows() > 0;
    }
}
