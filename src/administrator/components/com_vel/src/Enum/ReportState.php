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
 * Where a report stands. A report is an event, not a process; these are the outcomes the team
 * can record about it, and every one of them is terminal except the first three.
 *
 * @since 4.0.0
 */
enum ReportState: string
{
    /**
     * A guest submission whose confirmation link has not been opened yet (D-3). Invisible in
     * the queue; purged after `unverified_purge_days`.
     *
     * @since 4.0.0
     */
    case VERIFYING = 'verifying';

    case NEW       = 'new';
    case TRIAGED   = 'triaged';
    case ACCEPTED  = 'accepted';
    case DUPLICATE = 'duplicate';
    case REJECTED  = 'rejected';
    case SPAM      = 'spam';

    /**
     * @return self[]  The states the queue shows by default.
     *
     * @since 4.0.0
     */
    public static function open(): array
    {
        return [self::NEW, self::TRIAGED];
    }

    /**
     * @return bool
     *
     * @since 4.0.0
     */
    public function isOpen(): bool
    {
        return \in_array($this, self::open(), true);
    }

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_REPORT_STATE_' . strtoupper($this->value);
    }

    /**
     * @return string  Bootstrap badge colour.
     *
     * @since 4.0.0
     */
    public function badge(): string
    {
        return match ($this) {
            self::VERIFYING => 'secondary',
            self::NEW       => 'danger',
            self::TRIAGED   => 'info',
            self::ACCEPTED  => 'success',
            self::DUPLICATE => 'secondary',
            self::REJECTED  => 'dark',
            self::SPAM      => 'dark',
        };
    }
}
