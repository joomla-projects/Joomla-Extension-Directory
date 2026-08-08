<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Transfer;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Where an ownership transfer stands.
 *
 * Stored as its string value rather than an integer so a row is readable without this file -
 * a transfer is the kind of record somebody reads in a database client while working out what
 * happened to a listing.
 *
 * @since 4.0.0
 */
enum TransferState: string
{
    /**
     * Requested, nobody has confirmed.
     *
     * @since 4.0.0
     */
    case PENDING = 'pending';

    /**
     * The current owner has confirmed; the recipient has not.
     *
     * @since 4.0.0
     */
    case FROM_CONFIRMED = 'from_confirmed';

    /**
     * The recipient has confirmed; the current owner has not.
     *
     * @since 4.0.0
     */
    case TO_CONFIRMED = 'to_confirmed';

    /**
     * Both confirmed; ownership has moved.
     *
     * @since 4.0.0
     */
    case COMPLETED = 'completed';

    /**
     * Nobody finished in time.
     *
     * @since 4.0.0
     */
    case EXPIRED = 'expired';

    /**
     * Called off by either party or by the JED team.
     *
     * @since 4.0.0
     */
    case CANCELLED = 'cancelled';

    /**
     * Moved by the JED team without the current owner's consent - the abandonware escape
     * hatch (8.8.1). Recorded as its own state rather than as `completed`, because how a
     * listing changed hands is exactly what a later reader needs to know.
     *
     * @since 4.0.0
     */
    case FORCED = 'forced';

    /**
     * Whether the transfer is still waiting on somebody.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function isOpen(): bool
    {
        return match ($this) {
            self::PENDING, self::FROM_CONFIRMED, self::TO_CONFIRMED => true,
            default                                                 => false,
        };
    }

    /**
     * The state reached when one more party confirms.
     *
     * @param bool $isRecipient Whether the confirming party is the recipient.
     *
     * @return TransferState
     *
     * @since 4.0.0
     */
    public function afterConfirmationBy(bool $isRecipient): self
    {
        if ($isRecipient) {
            return $this === self::FROM_CONFIRMED ? self::COMPLETED : self::TO_CONFIRMED;
        }

        return $this === self::TO_CONFIRMED ? self::COMPLETED : self::FROM_CONFIRMED;
    }
}
