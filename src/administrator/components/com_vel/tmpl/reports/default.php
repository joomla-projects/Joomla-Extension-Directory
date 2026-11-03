<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\ReportKind;
use Jed\Component\Vel\Administrator\Enum\ReportState;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Administrator\View\Reports\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')->useScript('multiselect');
?>

<form action="<?php echo Route::_('index.php?option=com_vel&view=reports'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table table-striped" id="reportList">
                        <caption class="visually-hidden"><?php echo Text::_('COM_VEL_REPORTS_TITLE'); ?></caption>
                        <thead>
                        <tr>
                            <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                            <th scope="col" class="w-8"><?php echo Text::_('COM_VEL_HEADING_REFERENCE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_EXTENSION'); ?></th>
                            <th scope="col" class="w-15"><?php echo Text::_('COM_VEL_HEADING_REPORTER'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_VEL_HEADING_KIND'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('JSTATUS'); ?></th>
                            <th scope="col" class="w-15 d-none d-md-table-cell"><?php echo Text::_('COM_VEL_HEADING_ADVISORY'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_VEL_HEADING_RECEIVED'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php
                            $state = ReportState::tryFrom((string) $item->state) ?? ReportState::NEW;
                            $kind  = ReportKind::tryFrom((string) $item->kind) ?? ReportKind::VULNERABILITY;
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->extension_name); ?></td>
                                <td>
                                    <a class="font-monospace" href="<?php echo Route::_('index.php?option=com_vel&task=report.edit&id=' . (int) $item->id); ?>"><?php echo \sprintf('VELR-%05d', (int) $item->id); ?></a>
                                </td>
                                <th scope="row">
                                    <?php echo $this->escape($item->extension_name) ?: '<span class="text-muted">' . Text::_('COM_VEL_UNNAMED_EXTENSION') . '</span>'; ?>
                                    <span class="text-muted small"><?php echo $this->escape($item->extension_version); ?></span>
                                    <div class="small text-muted">
                                        <?php if ($item->vulnerability_class) : ?><span class="font-monospace"><?php echo $this->escape($item->vulnerability_class); ?></span> &middot; <?php endif; ?>
                                        <?php if ((string) $item->actively_exploited === 'yes') : ?><span class="badge bg-danger"><?php echo Text::_('COM_VEL_EXPLOITED_YES'); ?></span> <?php endif; ?>
                                        <?php if ((int) $item->publicly_disclosed === 1) : ?><span class="badge bg-warning text-dark"><?php echo Text::_('COM_VEL_BADGE_PUBLIC'); ?></span> <?php endif; ?>
                                        <?php if ((int) $item->attachment_count > 0) : ?><span class="icon-paperclip" aria-hidden="true"></span> <?php echo (int) $item->attachment_count; ?><?php endif; ?>
                                        <span class="badge bg-light text-dark"><?php echo Text::_('COM_VEL_CHANNEL_' . strtoupper((string) $item->channel)); ?></span>
                                    </div>
                                </th>
                                <td class="small">
                                    <?php if ($this->can['communicate']) : ?>
                                        <?php echo $this->escape($item->reporter_name); ?>
                                        <div class="text-muted"><?php echo $this->escape($item->reporter_email); ?></div>
                                    <?php else : ?>
                                        <span class="text-muted"><?php echo Text::_('COM_VEL_REPORTER_HIDDEN'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo Text::_($kind->label()); ?></td>
                                <td><span class="badge bg-<?php echo $state->badge(); ?>"><?php echo Text::_($state->label()); ?></span></td>
                                <td class="d-none d-md-table-cell small">
                                    <?php if ($item->advisory_id) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_vel&task=advisory.edit&id=' . (int) $item->advisory_id); ?>">
                                            <?php echo \sprintf('VEL-%04d', (int) $item->advisory_id); ?> <?php echo $this->escape($item->advisory_title); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
