<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Master data for a `TicketType::Other` ticket (or any unrecognised linked item
 * type) - there is none, this is the fallback used by
 * Jed\Component\Tickets\Administrator\Ticket\OtherTicketHandler.
 *
 * @var object|null $displayData
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;

?>
<p class="text-muted"><?php echo Text::_('COM_TICKETS_MASTERDATA_NONE'); ?></p>
