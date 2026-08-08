<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Listing;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Where a listing stands, as the developer who owns it needs to read it.
 *
 * {@see ListingAccess} answers what a *visitor's* request gets. This answers what the *owner*
 * sees on their dashboard, which is a different question with more outcomes: a listing waiting
 * for review and one that was turned down are both invisible to the public and both `approved`
 * = 0, but telling a developer "not published" for either is exactly the gap 13.6 records.
 *
 * The verdict convention is the one `P1-02` settled: `approved_time` is null while nobody has
 * decided, and set once someone has - together with `approved` = 1 for a yes and 0 for a no.
 *
 * @since 4.0.0
 */
enum ListingStatus: string
{
    /**
     * Submitted, nobody has looked at it yet.
     *
     * @since 4.0.0
     */
    case AWAITING_APPROVAL = 'awaiting-approval';

    /**
     * Reviewed and turned down. Still the developer's to revise and resubmit.
     *
     * @since 4.0.0
     */
    case REJECTED = 'rejected';

    /**
     * Approved and public.
     *
     * @since 4.0.0
     */
    case ONLINE = 'online';

    /**
     * Approved, but the developer has taken it offline.
     *
     * @since 4.0.0
     */
    case OFFLINE = 'offline';

    /**
     * Blocked by the JED team.
     *
     * @since 4.0.0
     */
    case BLOCKED = 'blocked';

    /**
     * Soft-deleted.
     *
     * @since 4.0.0
     */
    case DELETED = 'deleted';

    /**
     * The language key for the label shown to the developer.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_JED_LISTING_STATUS_' . strtoupper(str_replace('-', '_', $this->value));
    }

    /**
     * The Bootstrap badge class the dashboard renders it with.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::ONLINE            => 'bg-success',
            self::AWAITING_APPROVAL => 'bg-info',
            self::OFFLINE           => 'bg-secondary',
            self::REJECTED          => 'bg-danger',
            self::BLOCKED           => 'bg-danger',
            self::DELETED           => 'bg-dark',
        };
    }

    /**
     * Work out the status of one listing row.
     *
     * Ordered most-decisive first, the same way {@see ListingAccess::forItem()} is: deletion and
     * blocking are the JED team's word and outrank anything the developer has set, and the
     * approval verdict outranks the developer's online/offline switch because a listing that was
     * never approved is not "offline", it is waiting or refused.
     *
     * @param object $item A row carrying deleted, blocked, approved, approved_time and state.
     *
     * @return ListingStatus
     *
     * @since 4.0.0
     */
    public static function forItem(object $item): self
    {
        if ((int) ($item->deleted ?? 0) === 1) {
            return self::DELETED;
        }

        if ((int) ($item->blocked ?? 0) === 1) {
            return self::BLOCKED;
        }

        if ((int) ($item->approved ?? 0) !== 1) {
            return ($item->approved_time ?? null) ? self::REJECTED : self::AWAITING_APPROVAL;
        }

        return (int) ($item->state ?? 0) === 1 ? self::ONLINE : self::OFFLINE;
    }
}
