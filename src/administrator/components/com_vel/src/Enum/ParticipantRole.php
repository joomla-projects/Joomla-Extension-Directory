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
 * The role of a person in a case.
 *
 * @since 4.0.0
 */
enum ParticipantRole: string
{
    case REPORTER = 'reporter';
    case VENDOR   = 'vendor';
    case TEAM     = 'team';
    case OTHER    = 'other';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_PARTICIPANT_ROLE_' . strtoupper($this->value);
    }
}
