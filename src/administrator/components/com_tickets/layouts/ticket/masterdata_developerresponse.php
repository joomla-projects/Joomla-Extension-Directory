<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Master data for a `TicketType::DeveloperResponse` ticket - see
 * Jed\Component\Tickets\Administrator\Ticket\DeveloperresponseTicketHandler::getMasterData().
 *
 * @var object|null $displayData
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Language\Text;

$review = $displayData;
?>
<?php if (!$review) : ?>
    <p class="text-muted"><?php echo Text::_('COM_TICKETS_MASTERDATA_NOT_FOUND'); ?></p>
<?php else : ?>
    <dl class="row mb-0">
        <dt class="col-sm-3"><?php echo Text::_('JGLOBAL_TITLE'); ?></dt>
        <dd class="col-sm-9"><?php echo htmlspecialchars($review->title ?? '', ENT_QUOTES, 'UTF-8'); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_DEVELOPERRESPONSE_REVIEW_CREATOR_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo htmlspecialchars($review->review_creator ?? '', ENT_QUOTES, 'UTF-8'); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_DEVELOPERRESPONSE_RESPONDED_ON_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo htmlspecialchars($review->developer_responded_on ?? '', ENT_QUOTES, 'UTF-8'); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_DEVELOPERRESPONSE_RESPONSE_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($review->developer_response ?? '', ENT_QUOTES, 'UTF-8')); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('JSTATUS'); ?></dt>
        <dd class="col-sm-9"><?php echo JedHelper::displayFieldValue('state', (int) ($review->developer_response_published ?? 0) === 1 ? 1 : 0); ?></dd>
    </dl>
<?php endif; ?>
