<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Enum;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Where an abandonware case stands.
 *
 * 4.10's central point is that a report is not a state but a **process**: received → reviewed →
 * owner contacted → grace period expired → marked as abandoned → taken over again. This enum is
 * that sentence, with the transitions between the steps made explicit so the sequence cannot be
 * short-circuited by a controller that means well.
 *
 * **This is deliberately not a fifth listing state.** 4.10 and `P1-01` are both explicit: the four
 * carriers on `#__jed_extensions` - `state`, `approved`, `blocked`, `deleted` - stay unchanged. A
 * case is a *separate assessment* that may lead to a marker or to a block with a reason, and the
 * block it leads to is written by `P1-01`'s machinery through `blocked`, never by this one.
 *
 * @since 4.1.0
 */
enum CaseStatus: string
{
    /**
     * A signal arrived - a public report or one of the three automated ones - and nobody has
     * looked at it yet.
     *
     * @since 4.1.0
     */
    case RECEIVED = 'received';

    /**
     * Somebody on the JED team has picked it up.
     *
     * @since 4.1.0
     */
    case REVIEWING = 'reviewing';

    /**
     * The owner has been written to and the grace period is running. Step 3, and the one 4.10
     * calls both the most important and the most likely to be skipped.
     *
     * @since 4.1.0
     */
    case OWNER_CONTACTED = 'owner_contacted';

    /**
     * The grace period ran out with no response.
     *
     * @since 4.1.0
     */
    case GRACE_EXPIRED = 'grace_expired';

    /**
     * Concluded: no longer maintained. The only status that appears in the public list, and the
     * only one reachable exclusively through a recorded contact attempt.
     *
     * @since 4.1.0
     */
    case ABANDONED = 'abandoned';

    /**
     * Finished with an outcome that is not "abandoned": the extension changed hands, the
     * developer answered, or the assessment was wrong.
     *
     * @since 4.1.0
     */
    case RESOLVED = 'resolved';

    /**
     * Closed without a substantive outcome - a duplicate, or a report that was abuse. Separate
     * from `RESOLVED` so that "how many reports were abuse" stays an answerable question.
     *
     * @since 4.1.0
     */
    case DISMISSED = 'dismissed';

    /**
     * The statuses that count as an open case.
     *
     * `ABANDONED` is in here, which looks wrong for a fortnight and then does not: a marked
     * extension can still be adopted, and until it is, a second signal about it has to find the
     * existing case rather than open a parallel one. It leaves the set only when somebody records
     * an outcome. The generated column enforcing one open case per extension uses exactly this
     * list, so any change here has to change the schema too.
     *
     * @return self[]
     *
     * @since 4.1.0
     */
    public static function open(): array
    {
        return [self::RECEIVED, self::REVIEWING, self::OWNER_CONTACTED, self::GRACE_EXPIRED, self::ABANDONED];
    }

    /**
     * @return bool  Whether a case in this status is still live.
     *
     * @since 4.1.0
     */
    public function isOpen(): bool
    {
        return \in_array($this, self::open(), true);
    }

    /**
     * The statuses reachable from this one.
     *
     * Two rules are worth reading off the table rather than inferring:
     *
     *  - **`ABANDONED` is reachable only from `OWNER_CONTACTED` or `GRACE_EXPIRED`.** Both of
     *    those require a recorded contact attempt to have been reached at all, which is how
     *    "cannot be marked abandoned without a recorded contact attempt" survives contact with a
     *    hurried afternoon. {@see \Jed\Component\Abandonware\Administrator\Service\CaseService}
     *    checks the timestamp as well, because a status can be edited and a fact cannot.
     *  - **`ABANDONED` is not terminal.** Step 5 of the workflow is a new maintainer taking over,
     *    which resolves a case that was already public.
     *
     * @return self[]
     *
     * @since 4.1.0
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::RECEIVED        => [self::REVIEWING, self::OWNER_CONTACTED, self::RESOLVED, self::DISMISSED],
            self::REVIEWING       => [self::OWNER_CONTACTED, self::RESOLVED, self::DISMISSED],
            self::OWNER_CONTACTED => [self::GRACE_EXPIRED, self::ABANDONED, self::RESOLVED, self::DISMISSED],
            self::GRACE_EXPIRED   => [self::ABANDONED, self::RESOLVED, self::DISMISSED],
            self::ABANDONED       => [self::RESOLVED, self::DISMISSED],
            self::RESOLVED,
            self::DISMISSED => [],
        };
    }

    /**
     * @param self $next The status being moved to.
     *
     * @return bool  Whether that move is legal.
     *
     * @since 4.1.0
     */
    public function canMoveTo(self $next): bool
    {
        return \in_array($next, $this->allowedNext(), true);
    }

    /**
     * @return string  The language key for the label.
     *
     * @since 4.1.0
     */
    public function label(): string
    {
        return 'COM_ABANDONWARE_STATUS_' . strtoupper($this->value);
    }

    /**
     * @return string  A Bootstrap badge suffix for the backend list.
     *
     * @since 4.1.0
     */
    public function badge(): string
    {
        return match ($this) {
            self::RECEIVED        => 'secondary',
            self::REVIEWING       => 'info',
            self::OWNER_CONTACTED => 'primary',
            self::GRACE_EXPIRED   => 'warning',
            self::ABANDONED       => 'danger',
            self::RESOLVED        => 'success',
            self::DISMISSED       => 'dark',
        };
    }
}
