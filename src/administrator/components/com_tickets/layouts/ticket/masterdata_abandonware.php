<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Master data for a `TicketType::Abandonware` ticket - see
 * Jed\Component\Tickets\Administrator\Ticket\AbandonwareTicketHandler::getMasterData().
 *
 * Reads the case row directly rather than through com_abandonware's enums, so the ticket view
 * still renders when that component is absent. Nothing here identifies a reporter: the report
 * table is not read at all, only counted.
 *
 * @var object|null $displayData
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$case = $displayData;
?>
<?php if (!$case) : ?>
    <p class="text-muted"><?php echo Text::_('COM_TICKETS_MASTERDATA_NOT_FOUND'); ?></p>
<?php else : ?>
    <dl class="row mb-0">
        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_ABANDONWARE_EXTENSION_LABEL'); ?></dt>
        <dd class="col-sm-9">
            <?php if ((int) ($case->extension_id ?? 0) > 0) : ?>
                <a href="<?php echo Route::_('index.php?option=com_jed&task=extension.edit&id=' . (int) $case->extension_id); ?>">
                    <?php echo htmlspecialchars((string) ($case->extension_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php else : ?>
                <?php echo htmlspecialchars((string) ($case->extension_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                <span class="badge bg-secondary"><?php echo Text::_('COM_TICKETS_ABANDONWARE_NOT_LISTED'); ?></span>
            <?php endif; ?>
        </dd>

        <dt class="col-sm-3"><?php echo Text::_('JSTATUS'); ?></dt>
        <dd class="col-sm-9"><?php echo Text::_('COM_ABANDONWARE_STATUS_' . strtoupper((string) ($case->status ?? ''))); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_ABANDONWARE_SIGNALS_LABEL'); ?></dt>
        <dd class="col-sm-9">
            <?php if (empty($case->decoded_signals)) : ?>
                <span class="text-muted"><?php echo Text::_('JNONE'); ?></span>
            <?php else : ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($case->decoded_signals as $signal) : ?>
                        <li>
                            <span class="badge bg-info"><?php echo Text::_('COM_ABANDONWARE_SOURCE_' . strtoupper((string) ($signal['source'] ?? ''))); ?></span>
                            <?php echo htmlspecialchars((string) ($signal['detail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            <span class="small text-muted"><?php echo htmlspecialchars((string) ($signal['time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_ABANDONWARE_CONTACT_LABEL'); ?></dt>
        <dd class="col-sm-9">
            <?php if (empty($case->contact_time)) : ?>
                <span class="badge bg-warning text-dark"><?php echo Text::_('COM_TICKETS_ABANDONWARE_NO_CONTACT'); ?></span>
            <?php else : ?>
                <?php echo htmlspecialchars((string) $case->contact_time, ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($case->grace_until)) : ?>
                    &ndash; <?php echo Text::sprintf('COM_TICKETS_ABANDONWARE_GRACE_UNTIL', htmlspecialchars((string) $case->grace_until, ENT_QUOTES, 'UTF-8')); ?>
                <?php endif; ?>
            <?php endif; ?>
        </dd>

        <dt class="col-sm-3"><?php echo Text::_('COM_TICKETS_ABANDONWARE_REPORTS_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo (int) ($case->report_count ?? 0); ?></dd>
    </dl>
<?php endif; ?>
