<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Abandonware\Administrator\View\Reports\HtmlView $this */
?>

<form action="<?php echo Route::_('index.php?option=com_abandonware&view=reports'); ?>" method="post" name="adminForm" id="adminForm">
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
                        <caption class="visually-hidden"><?php echo Text::_('COM_ABANDONWARE_REPORTS_TITLE'); ?></caption>
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_ABANDONWARE_HEADING_EXTENSION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_ABANDONWARE_HEADING_REASON'); ?></th>
                            <th scope="col" class="w-20"><?php echo Text::_('COM_ABANDONWARE_HEADING_REPORTER'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_ABANDONWARE_HEADING_CASE'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('JDATE'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $item) : ?>
                            <tr>
                                <th scope="row">
                                    <?php echo $this->escape($item->extension_name) ?: Text::_('COM_ABANDONWARE_UNNAMED_EXTENSION'); ?>
                                    <?php if ($item->extension_version) : ?>
                                        <span class="small text-muted"><?php echo $this->escape($item->extension_version); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item->developer_name) : ?>
                                        <div class="small text-muted"><?php echo $this->escape($item->developer_name); ?></div>
                                    <?php endif; ?>
                                    <?php if ($item->legacy_form_id) : ?>
                                        <span class="badge bg-secondary"><?php echo Text::sprintf('COM_ABANDONWARE_BADGE_LEGACY', (int) $item->legacy_form_id); ?></span>
                                    <?php endif; ?>
                                </th>

                                <td class="small"><?php echo nl2br($this->escape(mb_substr((string) $item->reason, 0, 400))); ?></td>

                                <td class="small">
                                    <?php echo $this->escape($item->reporter_name); ?>
                                    <div class="text-muted"><?php echo $this->escape($item->reporter_email); ?></div>
                                    <?php if ((int) $item->consent_to_process !== 1) : ?>
                                        <span class="badge bg-warning text-dark"><?php echo Text::_('COM_ABANDONWARE_NO_CONSENT_RECORDED'); ?></span>
                                    <?php endif; ?>
                                    <?php
                                    /*
                                     * The abuse signal 4.10 asks for, in the one place it is
                                     * visible: how many reports this account has filed in total.
                                     * One is a citizen doing their bit; a run of them against one
                                     * developer is what the `report` privilege gets withdrawn for.
                                     */
                                    ?>
                                    <?php if ((int) $item->reporter_user_id > 0 && (int) $item->reporter_total > 1) : ?>
                                        <div><span class="badge bg-info"><?php echo Text::plural('COM_ABANDONWARE_REPORTER_TOTAL_N', (int) $item->reporter_total); ?></span></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ((int) $item->case_id > 0) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_abandonware&task=case.edit&id=' . (int) $item->case_id); ?>">
                                            #<?php echo (int) $item->case_id; ?>
                                        </a>
                                        <?php if ($item->case_status) : ?>
                                            <div class="small text-muted"><?php echo Text::_('COM_ABANDONWARE_STATUS_' . strtoupper((string) $item->case_status)); ?></div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>

                                <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?></td>
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
