<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Master data for a `TicketType::Review` ticket - see
 * Jed\Component\Tickets\Administrator\Ticket\ReviewTicketHandler::getMasterData().
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

        <dt class="col-sm-3"><?php echo Text::_('JGLOBAL_FIELD_CREATED_BY_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo htmlspecialchars($review->review_creator ?? '', ENT_QUOTES, 'UTF-8'); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_JED_GENERAL_VERSION_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo htmlspecialchars($review->version ?? '', ENT_QUOTES, 'UTF-8'); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_REVIEWS_FUNCTIONALITY_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo number_format((float) ($review->functionality ?? 0), 1); ?> &ndash; <?php echo nl2br(htmlspecialchars($review->functionality_comment ?? '', ENT_QUOTES, 'UTF-8')); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_REVIEWS_EASE_OF_USE_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo number_format((float) ($review->ease_of_use ?? 0), 1); ?> &ndash; <?php echo nl2br(htmlspecialchars($review->ease_of_use_comment ?? '', ENT_QUOTES, 'UTF-8')); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_GENERAL_SUPPORT_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo number_format((float) ($review->support ?? 0), 1); ?> &ndash; <?php echo nl2br(htmlspecialchars($review->support_comment ?? '', ENT_QUOTES, 'UTF-8')); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_EXTENSION_DOCUMENTATION_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo number_format((float) ($review->documentation ?? 0), 1); ?> &ndash; <?php echo nl2br(htmlspecialchars($review->documentation_comment ?? '', ENT_QUOTES, 'UTF-8')); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_REVIEWS_VALUE_FOR_MONEY_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo number_format((float) ($review->value_for_money ?? 0), 1); ?> &ndash; <?php echo nl2br(htmlspecialchars($review->value_for_money_comment ?? '', ENT_QUOTES, 'UTF-8')); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_REVIEWS_OVERALL_SCORE_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo number_format((float) ($review->overall_score ?? 0), 1); ?> / 5</dd>

        <dt class="col-sm-3"><?php echo Text::_('JGLOBAL_FIELD_TEXT_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($review->body ?? '', ENT_QUOTES, 'UTF-8')); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('JSTATUS'); ?></dt>
        <dd class="col-sm-9"><?php echo JedHelper::displayFieldValue('state', (int) ($review->published ?? 0) === 1 ? 1 : 0); ?></dd>
    </dl>
<?php endif; ?>
