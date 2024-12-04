<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Jed\Component\Jed\Administrator\Helper\JedHelper;

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/src/Helper/');
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');


$user      = Factory::getApplication()->getIdentity();
$userId    = $user->id;
$listOrder = $this->state->get('list.ordering');
$listDirn  = $this->state->get('list.direction');
$canOrder  = $user->authorise('core.edit.state', 'com_jed');
$saveOrder = $listOrder == 'a.`ordering`';

if ($saveOrder) {
    $saveOrderingUrl = 'index.php?option=com_jed&task=velabandonedreports.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}

// $sortFields = $this->getSortFields();
?>

<form action="<?php echo Route::_('index.php?option=com_jed&view=velabandonedreports'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="velabandonedreportList">
                    <thead>
                    <tr>
                        <?php if (isset($this->items[0]->ordering)) : ?>
                            <th width="1%" class="nowrap center hidden-phone">
                                <?php echo HTMLHelper::_('searchtools.sort', '', 'a.`ordering`', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-menu-2'); ?>
                            </th>
                        <?php endif; ?>
                        <th width="1%">
                            <input type="checkbox" name="checkall-toggle" value=""
                                   title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                        </th>
                        <?php if (isset($this->items[0]->state)) : ?>
                        <?php endif; ?>

                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_FIELD_ID_LABEL', 'a.`id`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_ITEM_NAME_LABEL', 'a.`extension_name`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_DEVELOPER_NAME_LABEL', 'a.`developer_name`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_ITEM_VERSION_LABEL', 'a.`extension_version`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_NAME_LABEL', 'a.`reporter_fullname`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_EMAIL_LABEL', 'a.`reporter_email`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_ORGANISATION_LABEL', 'a.`reporter_organisation`', $listDirn, $listOrder); ?>
                        </th>

                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_CONSENT_TO_PROCESS_LABEL', 'a.`consent_to_process`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_PASSED_TO_VEL_LABEL', 'a.`passed_to_vel`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_DATA_SOURCE_LABEL', 'a.`data_source`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_DATE_SUBMITTED_LABEL', 'a.`date_submitted`', $listDirn, $listOrder); ?>
                        </th>


                    </tr>
                    </thead>
                    <tfoot>
                    <tr>
                        <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                            <?php echo $this->pagination->getListFooter(); ?>
                        </td>
                    </tr>
                    </tfoot>
                    <tbody <?php if ($saveOrder) :
                        ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" <?php
                           endif; ?>>
                    <?php foreach ($this->items as $i => $item) :
                        $ordering   = ($listOrder == 'a.ordering');
                        $canCreate  = $user->authorise('core.create', 'com_jed');
                        $canEdit    = $user->authorise('core.edit', 'com_jed');
                        $canCheckin = $user->authorise('core.manage', 'com_jed');
                        $canChange  = $user->authorise('core.edit.state', 'com_jed');
                        ?>
                        <tr class="row<?php echo $i % 2; ?>">

                            <?php if (isset($this->items[0]->ordering)) : ?>
                                <td class="order nowrap center hidden-phone">
                                    <?php if ($canChange) :
                                        $disableClassName = '';
                                        $disabledLabel    = '';

                                        if (!$saveOrder) :
                                            $disabledLabel    = Text::_('JORDERINGDISABLED');
                                            $disableClassName = 'inactive tip-top';
                                        endif; ?>
                                        <span class="sortable-handler hasTooltip <?php echo $disableClassName ?>"
                                              title="<?php echo $disabledLabel ?>">
                                    <i class="icon-menu"></i>
                                </span>
                                        <input type="text" style="display:none" name="order[]" size="5"
                                               value="<?php echo $item->ordering; ?>"
                                               class="width-20 text-area-order "/>
                                    <?php else : ?>
                                        <span class="sortable-handler inactive">
                                    <i class="icon-menu"></i>
                                </span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                            </td>
                            <?php if (isset($this->items[0]->state)) : ?>
                            <?php endif; ?>

                            <td>

                                <?php echo $item->id; ?>
                            </td>
                            <td>
                                <?php if (isset($item->checked_out) && $item->checked_out && ($canEdit || $canChange)) : ?>
                                    <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, 'velabandonedreports.', $canCheckin); ?>
                                <?php endif; ?>
                                <?php if ($canEdit) : ?>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&task=velabandonedreport.edit&id=' . (int) $item->id); ?>">
                                        <?php echo $item->extension_name; ?></a>
                                <?php else : ?>
                                    <?php echo $this->escape($item->extension_name); ?>
                                <?php endif; ?>
                            </td>
                            <td>

                                <?php echo $item->developer_name; ?>
                            </td>
                            <td>

                                <?php echo $item->extension_version; ?>
                            </td>


                            <td>

                                <?php echo $this->escape($item->reporter_fullname); ?>


                            </td>
                            <td>

                                <?php echo $item->reporter_email; ?>
                            </td>
                            <td>

                                <?php echo $item->reporter_organisation; ?>
                            </td>
                            <td>

                                <?php echo $item->consent_to_process; ?>
                            </td>
                            <td>

                                <?php echo $item->passed_to_vel; ?>
                            </td>
                            <td>

                                <?php echo $item->data_source; ?>
                            </td>
                            <td>

                                <?php
                                $date = $item->date_submitted;
                                echo $date > 0 ? HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC6')) : '-';
                                ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <input type="hidden" name="list[fullorder]" value="<?php echo $listOrder; ?> <?php echo $listDirn; ?>"/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
