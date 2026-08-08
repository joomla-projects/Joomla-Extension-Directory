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
 * What the public site does with a listing URL, given the listing's state.
 *
 * The four carriers are independent (4.8): `approved` belongs to the JED team, `state` to the
 * developer, `blocked` to the JED team again, `deleted` to the owner or the team. This enum is
 * where they are collapsed into the one answer a request needs, so the precedence is written
 * down once instead of being re-derived at every call site.
 *
 * @since 4.0.0
 */
enum ListingAccess
{
    /**
     * Render the listing.
     *
     * @since 4.0.0
     */
    case VISIBLE;

    /**
     * Render the public block notice instead of the listing, with 200 and `noindex`.
     *
     * 200 rather than 404 is deliberate (4.8): the point of a block is to tell visitors why
     * the listing is not usable, and on a site with this much organic traffic a 404 would
     * throw that information away.
     *
     * @since 4.0.0
     */
    case BLOCKED;

    /**
     * 410 Gone - the listing was soft-deleted.
     *
     * @since 4.0.0
     */
    case GONE;

    /**
     * 404 - never approved, or the developer took it offline. No reason is given either way:
     * "this developer unpublished their extension" is not the public's business.
     *
     * @since 4.0.0
     */
    case NOT_FOUND;

    /**
     * The HTTP status this outcome is served with.
     *
     * @return int
     *
     * @since 4.0.0
     */
    public function statusCode(): int
    {
        return match ($this) {
            self::VISIBLE, self::BLOCKED => 200,
            self::GONE                   => 410,
            self::NOT_FOUND              => 404,
        };
    }

    /**
     * Decide the outcome for one loaded listing row.
     *
     * Precedence, and the reasoning for it:
     *
     *  1. `deleted` wins over everything, including the owner's own view. A soft-deleted listing
     *     is gone from the frontend; it stays readable in the backend and nowhere else.
     *  2. Never approved is a 404 even for the owner's *public* URL - the owner reaches their
     *     pending submission through the dashboard and the edit form, not through the catalogue.
     *     $isPrivileged therefore only relaxes rule 3.
     *  3. `blocked` outranks the developer's `state`. If it did not, a developer could hide the
     *     block notice by taking the listing offline, and the block is the JED team's public
     *     statement - it is not the developer's to suppress. The block itself stays either way,
     *     so this changes what visitors see, never whether the block holds.
     *  4. Offline is the developer's decision and yields a plain 404.
     *
     * @param object $item         The loaded listing row.
     * @param bool   $isPrivileged Whether the current user owns or maintains this listing.
     *
     * @return ListingAccess
     *
     * @since 4.0.0
     */
    public static function forItem(object $item, bool $isPrivileged = false): self
    {
        if ((int) ($item->deleted ?? 0) === 1) {
            return self::GONE;
        }

        if ((int) ($item->approved ?? 0) !== 1) {
            return self::NOT_FOUND;
        }

        if ((int) ($item->blocked ?? 0) === 1) {
            return self::BLOCKED;
        }

        if ((int) ($item->state ?? 0) === 1) {
            return self::VISIBLE;
        }

        return $isPrivileged ? self::VISIBLE : self::NOT_FOUND;
    }
}
