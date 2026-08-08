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

use Jed\Component\Abandonware\Administrator\Enum\CaseSource;
use Jed\Component\Abandonware\Administrator\Enum\CaseStatus;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Abandonware\Administrator\View\Cases\HtmlView $this */

$now = time();
?>

<form action="<?php echo Route::_('index.php?option=com_abandonware&view=cases'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table table-striped" id="caseList">
                        <caption class="visually-hidden"><?php echo Text::_('COM_ABANDONWARE_CASES_TITLE'); ?></caption>
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_ABANDONWARE_HEADING_EXTENSION'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('JSTATUS'); ?></th>
                            <th scope="col" class="w-15"><?php echo Text::_('COM_ABANDONWARE_HEADING_SIGNALS'); ?></th>
                            <th scope="col" class="w-15"><?php echo Text::_('COM_ABANDONWARE_HEADING_CONTACT'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_ABANDONWARE_HEADING_ASSIGNED'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('JDATE'); ?></th>
                            <th scope="col" class="w-5 d-none d-md-table-cell"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $item) : ?>
                            <?php
                            $status = CaseStatus::tryFrom((string) $item->status) ?? CaseStatus::RECEIVED;
                            $source = CaseSource::tryFrom((string) $item->source) ?? CaseSource::MANUAL;

                            // A grace period that has run out but has not been aged yet - the
                            // scheduled pass has not been round since. Showing it as still running
                            // would be a lie by one day, which is exactly the sort of lie that
                            // makes somebody wait another week before acting.
                            $graceOver = !empty($item->grace_until) && strtotime((string) $item->grace_until) < $now;
                            ?>
                            <tr>
                                <th scope="row">
                                    <?php if ($this->canEdit) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_abandonware&task=case.edit&id=' . (int) $item->id); ?>">
                                            <?php echo $this->escape($item->extension_name) ?: Text::_('COM_ABANDONWARE_UNNAMED_EXTENSION'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->extension_name) ?: Text::_('COM_ABANDONWARE_UNNAMED_EXTENSION'); ?>
                                    <?php endif; ?>

                                    <div class="small">
                                        <?php if ((int) $item->extension_id > 0) : ?>
                                            <a class="text-muted" href="<?php echo Route::_('index.php?option=com_jed&task=extension.edit&id=' . (int) $item->extension_id); ?>">
                                                <?php echo Text::sprintf('COM_ABANDONWARE_LISTING_NUMBER', (int) $item->extension_id); ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="badge bg-secondary"><?php echo Text::_('COM_ABANDONWARE_NOT_LISTED'); ?></span>
                                        <?php endif; ?>

                                        <?php if ((int) $item->published === 1) : ?>
                                            <span class="badge bg-danger"><?php echo Text::_('COM_ABANDONWARE_BADGE_PUBLIC'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </th>

                                <td>
                                    <span class="badge bg-<?php echo $status->badge(); ?>"><?php echo Text::_($status->label()); ?></span>
                                    <?php if ($item->resolution) : ?>
                                        <div class="small text-muted"><?php echo Text::_('COM_ABANDONWARE_RESOLUTION_' . strtoupper((string) $item->resolution)); ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="badge bg-info"><?php echo Text::_($source->label()); ?></span>
                                    <?php if ((int) $item->report_count > 0) : ?>
                                        <div class="small text-muted"><?php echo Text::plural('COM_ABANDONWARE_N_REPORTS', (int) $item->report_count); ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (empty($item->contact_time)) : ?>
                                        <span class="badge bg-warning text-dark"><?php echo Text::_('COM_ABANDONWARE_NO_CONTACT_YET'); ?></span>
                                    <?php else : ?>
                                        <?php echo HTMLHelper::_('date', $item->contact_time, Text::_('DATE_FORMAT_LC4')); ?>
                                        <?php if (!empty($item->grace_until)) : ?>
                                            <div class="small <?php echo $graceOver ? 'text-danger' : 'text-muted'; ?>">
                                                <?php echo Text::sprintf(
                                                    $graceOver ? 'COM_ABANDONWARE_GRACE_ENDED' : 'COM_ABANDONWARE_GRACE_UNTIL',
                                                    HTMLHelper::_('date', $item->grace_until, Text::_('DATE_FORMAT_LC4'))
                                                ); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php echo $item->assignee_name
                                        ? $this->escape($item->assignee_name)
                                        : '<span class="text-muted">' . Text::_('COM_ABANDONWARE_UNASSIGNED') . '</span>'; ?>
                                </td>

                                <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?></td>
                                <td class="d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
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
