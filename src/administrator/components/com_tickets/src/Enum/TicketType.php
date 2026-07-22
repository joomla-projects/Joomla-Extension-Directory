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
    case AbandonedExtension  = 4;
    case VELReport           = 5;
    case VulnerableExtension = 6;
    case Other               = 7;
    case DeveloperResponse   = 8;
}
