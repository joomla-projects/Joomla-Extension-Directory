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
 * Who may read a message on the case page. `team` is internal; `all` is every participant.
 *
 * @since 4.0.0
 */
enum MessageVisibility: string
{
    case TEAM     = 'team';
    case REPORTER = 'reporter';
    case VENDOR   = 'vendor';
    case ALL      = 'all';

    /**
     * @param ParticipantRole $role The reader's role.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function readableBy(ParticipantRole $role): bool
    {
        return match ($this) {
            self::TEAM     => $role === ParticipantRole::TEAM,
            self::REPORTER => \in_array($role, [ParticipantRole::TEAM, ParticipantRole::REPORTER], true),
            self::VENDOR   => \in_array($role, [ParticipantRole::TEAM, ParticipantRole::VENDOR], true),
            self::ALL      => true,
        };
    }

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_VISIBILITY_' . strtoupper($this->value);
    }
}
