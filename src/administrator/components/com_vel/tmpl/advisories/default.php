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

use Jed\Component\Vel\Administrator\Enum\AdvisorySource;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\Severity;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Administrator\View\Advisories\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')->useScript('multiselect');

$user      = Factory::getApplication()->getIdentity();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$now       = time();
?>

<form action="<?php echo Route::_('index.php?option=com_vel&view=advisories'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table table-striped" id="advisoryList">
                        <caption class="visually-hidden"><?php echo Text::_('COM_VEL_ADVISORIES_TITLE'); ?></caption>
                        <thead>
                        <tr>
                            <td class="w-1 text-center">
                                <?php echo HTMLHelper::_('grid.checkall'); ?>
                            </td>
                            <th scope="col" class="w-5 d-none d-md-table-cell">
                                <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                            </th>
                            <th scope="col">
                                <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                            </th>
                            <th scope="col" class="w-15 d-none d-md-table-cell"><?php echo Text::_('COM_VEL_HEADING_PRODUCTS'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_VEL_HEADING_CVE'); ?></th>
                            <th scope="col" class="w-8">
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_VEL_HEADING_STATE', 'a.state', $listDirn, $listOrder); ?>
                            </th>
                            <th scope="col" class="w-8"><?php echo Text::_('COM_VEL_HEADING_STATUS'); ?></th>
                            <th scope="col" class="w-8">
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_VEL_HEADING_SEVERITY', 'a.cvss_score', $listDirn, $listOrder); ?>
                            </th>
                            <th scope="col" class="w-10 d-none d-md-table-cell">
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_VEL_HEADING_PUBLISHED', 'a.date_published', $listDirn, $listOrder); ?>
                            </th>
                            <th scope="col" class="w-10 d-none d-md-table-cell">
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_VEL_HEADING_UPDATED', 'a.modified', $listDirn, $listOrder); ?>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php
                            $state      = AdvisoryState::tryFrom((string) $item->state) ?? AdvisoryState::DRAFT;
                            $status     = AdvisoryStatus::tryFrom((string) $item->status) ?? AdvisoryStatus::LIVE;
                            $severity   = $item->severity ? Severity::tryFrom((string) $item->severity) : null;
                            $source     = AdvisorySource::tryFrom((string) $item->source) ?? AdvisorySource::MANUAL;
                            $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $user->id || is_null($item->checked_out);
                            $embargo    = !$state->isPublic() && !empty($item->date_public) && strtotime((string) $item->date_public) < $now;
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="font-monospace"><?php echo \sprintf('VEL-%04d', (int) $item->id); ?></span>
                                </td>
                                <th scope="row">
                                    <?php if ($item->checked_out) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor_name, $item->checked_out_time, 'advisories.', $canCheckin); ?>
                                    <?php endif; ?>

                                    <?php if ($this->can['edit']) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_vel&task=advisory.edit&id=' . (int) $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?>">
                                            <?php echo $this->escape($item->title); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->title); ?>
                                    <?php endif; ?>

                                    <div class="small text-muted">
                                        <?php echo $this->escape((string) $item->vendors); ?>
                                        <?php if ($source !== AdvisorySource::MANUAL) : ?>
                                            &middot; <span class="badge bg-light text-dark"><?php echo Text::_($source->label()); ?></span>
                                        <?php endif; ?>
                                        <?php if ((int) $item->report_count > 0) : ?>
                                            &middot; <?php echo Text::plural('COM_VEL_N_REPORTS', (int) $item->report_count); ?>
                                        <?php endif; ?>
                                        <?php if ($embargo) : ?>
                                            &middot; <span class="badge bg-warning text-dark"><?php echo Text::_('COM_VEL_BADGE_EMBARGO_PASSED'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </th>
                                <td class="d-none d-md-table-cell small"><?php echo $this->escape((string) $item->products); ?></td>
                                <td class="small font-monospace">
                                    <?php echo $item->cve_id ? $this->escape($item->cve_id) : '<span class="text-muted">&mdash;</span>'; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $state->badge(); ?>"><?php echo Text::_($state->label()); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status->badge(); ?>"><?php echo Text::_($status->label()); ?></span>
                                </td>
                                <td>
                                    <?php if ($severity !== null) : ?>
                                        <span class="badge bg-<?php echo $severity->badge(); ?>"><?php echo Text::_($severity->label()); ?></span>
                                        <?php if ($item->cvss_score !== null) : ?>
                                            <span class="small text-muted"><?php echo number_format((float) $item->cvss_score, 1); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell small">
                                    <?php echo $item->date_published ? HTMLHelper::_('date', $item->date_published, Text::_('DATE_FORMAT_LC4')) : '&mdash;'; ?>
                                </td>
                                <td class="d-none d-md-table-cell small">
                                    <?php echo $item->modified ? HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC4')) : HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?>
                                </td>
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
