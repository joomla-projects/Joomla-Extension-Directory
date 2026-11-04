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
 * The public classification of an advisory (brief §4.1): what a site administrator reading the
 * list needs to know first.
 *
 * Derived by default - `patched` when every affected product has a fixed version, `abandoned`
 * when the linked abandonware case concluded so - and overridable by an editor, because the
 * derivation cannot know that a vendor has promised a fix for Tuesday.
 *
 * @since 4.0.0
 */
enum AdvisoryStatus: string
{
    /**
     * No fix available. The default, and the one that should make a reader act.
     *
     * @since 4.0.0
     */
    case LIVE = 'live';

    /**
     * A fixed version exists for every affected product.
     *
     * @since 4.0.0
     */
    case PATCHED = 'patched';

    /**
     * The vendor is gone or silent; no fix is coming. Usually reached through the abandonware
     * bridge (VEL2-PRD §6.9.4).
     *
     * @since 4.0.0
     */
    case ABANDONED = 'abandoned';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_STATUS_' . strtoupper($this->value);
    }

    /**
     * @return string  Bootstrap badge colour.
     *
     * @since 4.0.0
     */
    public function badge(): string
    {
        return match ($this) {
            self::LIVE      => 'danger',
            self::PATCHED   => 'success',
            self::ABANDONED => 'warning text-dark',
        };
    }
}
