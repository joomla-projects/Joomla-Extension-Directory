<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Enum;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The editorial state of an advisory (brief §3.1.A).
 *
 * draft -> ready -> published -> retired, with the two reverse moves a team actually needs: a
 * "ready" advisory that turns out to need more work goes back to draft, and a retired one that was
 * retired by mistake can be published again. Only `published` is ever visible outside the
 * backend - by HTML, by JSON, by the check API, by anything.
 *
 * The transitions are explicit here and enforced in {@see \Jed\Component\Vel\Administrator\Service\VelService}
 * so the sequence cannot be short-circuited by a controller that means well. Publishing is a
 * separate permission (`vel.publish`) from editing for the reason abandonware.mark is: it puts a
 * statement about somebody's product in public.
 *
 * @since 4.0.0
 */
enum AdvisoryState: string
{
    /**
     * Being written. The default for anything created by a report, a feed, or by hand.
     *
     * @since 4.0.0
     */
    case DRAFT = 'draft';

    /**
     * Complete and validated; waiting for somebody with `vel.publish`.
     *
     * @since 4.0.0
     */
    case READY = 'ready';

    /**
     * Public.
     *
     * @since 4.0.0
     */
    case PUBLISHED = 'published';

    /**
     * Withdrawn. Still reachable at its URL with a notice, out of the lists and feeds, and in
     * the dataset as withdrawn for long enough that every checker drops it.
     *
     * @since 4.0.0
     */
    case RETIRED = 'retired';

    /**
     * @return self[]
     *
     * @since 4.0.0
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::DRAFT     => [self::READY],
            self::READY     => [self::DRAFT, self::PUBLISHED],
            self::PUBLISHED => [self::RETIRED],
            self::RETIRED   => [self::PUBLISHED],
        };
    }

    /**
     * @param self $next Where it would go.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function canMoveTo(self $next): bool
    {
        return \in_array($next, $this->allowedNext(), true);
    }

    /**
     * Whether a move into this state needs `vel.publish` rather than `core.edit.state`.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function requiresPublishPermission(): bool
    {
        return $this === self::PUBLISHED || $this === self::RETIRED;
    }

    /**
     * @return bool  Whether anything outside the backend may see an advisory in this state.
     *
     * @since 4.0.0
     */
    public function isPublic(): bool
    {
        return $this === self::PUBLISHED;
    }

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_STATE_' . strtoupper($this->value);
    }

    /**
     * @return string  Bootstrap badge colour.
     *
     * @since 4.0.0
     */
    public function badge(): string
    {
        return match ($this) {
            self::DRAFT     => 'secondary',
            self::READY     => 'info',
            self::PUBLISHED => 'success',
            self::RETIRED   => 'dark',
        };
    }
}
