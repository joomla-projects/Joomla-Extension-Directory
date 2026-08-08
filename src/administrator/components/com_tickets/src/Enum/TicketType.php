<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Enum;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The kind of item a ticket is about.
 *
 * @since 4.0.0
 */
enum TicketType: int
{
    case Extension           = 1;
    case Review              = 2;
    // 3 was used for wrong scoring code
    // 4, 5, 6 were used for VEL (com_vel) report types, removed along with com_vel
    case Other               = 7;
    case DeveloperResponse   = 8;
    // Opened by the periodic link check for the developer whose link has stopped answering
    // (P1-09). A new value rather than recycling 3: it is not yet established whether legacy
    // rows still carry that one, and a ticket type that quietly means two things is worse than
    // a gap in the numbering. `linked_item_id` is the extension, as for Extension above.
    case LinkCheck           = 9;
    // The JED team's ticket for an abandonware case (P1-19). `linked_item_id` is the **case**,
    // not the extension: a case can exist for something the JED never listed, and what the team
    // needs in front of them is the case with all its signals.
    //
    // P1-19 was written expecting to reuse `AbandonedreportTicketHandler`'s value 4. That is
    // stale - P0-04 decision 6 removed 4, 5 and 6 with com_vel and settled that they stay
    // removed. A new value for the same reason value 9 was new: a ticket type that quietly means
    // two things is worse than a gap in the numbering, and here the old meaning was a *VEL*
    // report, which this deliberately is not.
    case Abandonware         = 10;
}
