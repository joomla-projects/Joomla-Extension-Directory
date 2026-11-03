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

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\FeedClassification;
use Jed\Component\Vel\Administrator\Enum\FeedDecision;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Administrator\View\Feeditems\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')->useScript('multiselect');
?>
<form action="<?php echo Route::_('index.php?option=com_vel&view=feeditems'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table table-striped" id="feedItemList">
                        <caption class="visually-hidden"><?php echo Text::_('COM_VEL_FEEDITEMS_TITLE'); ?></caption>
                        <thead>
                        <tr>
                            <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_VEL_HEADING_CVE'); ?></th>
                            <th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_VEL_HEADING_ASSIGNER'); ?></th>
                            <th scope="col" class="w-12"><?php echo Text::_('COM_VEL_HEADING_CLASSIFICATION'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_VEL_HEADING_DECISION'); ?></th>
                            <th scope="col" class="w-15 d-none d-md-table-cell"><?php echo Text::_('COM_VEL_HEADING_ADVISORY'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_VEL_HEADING_UPDATED'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php
                            $classification = FeedClassification::tryFrom((string) $item->classification) ?? FeedClassification::UNKNOWN;
                            $decision       = FeedDecision::tryFrom((string) $item->decision) ?? FeedDecision::PENDING;
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->external_id); ?></td>
                                <td>
                                    <a class="font-monospace" href="<?php echo Route::_('index.php?option=com_vel&task=feeditem.edit&id=' . (int) $item->id); ?>"><?php echo $this->escape($item->external_id); ?></a>
                                    <div class="small text-muted"><?php echo Text::_('COM_VEL_FEED_SOURCE_' . strtoupper((string) $item->source)); ?></div>
                                </td>
                                <th scope="row"><?php echo $this->escape($item->title); ?></th>
                                <td class="small"><?php echo $this->escape($item->assigner); ?></td>
                                <td><span class="badge bg-<?php echo match ($classification) { FeedClassification::JOOMLA_CNA => 'primary', FeedClassification::JOOMLA_EXTENSION_OTHER_CNA => 'info', FeedClassification::JOOMLA_CORE => 'secondary', default => 'light text-dark' }; ?>"><?php echo Text::_($classification->label()); ?></span></td>
                                <td><span class="badge bg-<?php echo match ($decision) { FeedDecision::PENDING => 'warning text-dark', FeedDecision::CREATED, FeedDecision::MERGED => 'success', default => 'secondary' }; ?>"><?php echo Text::_($decision->label()); ?></span></td>
                                <td class="d-none d-md-table-cell small">
                                    <?php if ($item->advisory_id) : ?>
                                        <?php $aState = AdvisoryState::tryFrom((string) $item->advisory_state) ?? AdvisoryState::DRAFT; ?>
                                        <a href="<?php echo Route::_('index.php?option=com_vel&task=advisory.edit&id=' . (int) $item->advisory_id); ?>"><?php echo \sprintf('VEL-%04d', (int) $item->advisory_id); ?></a>
                                        <span class="badge bg-<?php echo $aState->badge(); ?>"><?php echo Text::_($aState->label()); ?></span>
                                    <?php else : ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?php echo $item->external_updated ? HTMLHelper::_('date', $item->external_updated, Text::_('DATE_FORMAT_LC4')) : HTMLHelper::_('date', $item->fetched, Text::_('DATE_FORMAT_LC4')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <input type="hidden" name="source" id="feed-source" value=""/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
