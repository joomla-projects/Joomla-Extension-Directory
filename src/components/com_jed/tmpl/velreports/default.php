<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

$user        = Factory::getApplication()->getIdentity();
$userId      = $user->id;
$listOrder   = $this->state->get('list.ordering');
$listDirn    = $this->state->get('list.direction');
$canCreate   = $user->authorise('core.create', 'com_jed');
$canEdit     = $user->authorise('core.edit', 'com_jed');
$canCheckin  = $user->authorise('core.manage', 'com_jed');
$canChange   = $user->authorise('core.edit.state', 'com_jed');
$canDelete   = $user->authorise('core.delete', 'com_jed');
$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();

// Import CSS

try {
    $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
    $wa->useStyle('com_jed.list');
} catch (Exception $e) {
}

if (!$isLoggedIn) {
    try {
        $app = Factory::getApplication();
        $app->enqueueMessage(Text::_('COM_JED_VEL_REPORTS_NO_ACCESS'), 'success');
        $app->redirect($redirectURL);
    } catch (Exception $e) {
    }
} else {
    ?>

    <form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
          name="adminForm" id="adminForm">
        <?php echo '<fieldset class="velreports"><legend>' . Text::_('COM_JED_VEL_MYREPORTS_LIST_TITLE') . '</legend>' . Text::_('COM_JED_VEL_MYREPORTS_LIST_DESCR') . '</fieldset>'; ?>
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
        <div class="table-responsive">
            <table class="table table-striped" id="velreportList">
                <thead>
                <tr>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_FIELD_ID_LABEL', 'a.id', $listDirn, $listOrder); ?>
                    </th>

                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JED_VEL_GENERAL_ITEM_NAME_LABEL', 'a.vulnerable_item_name', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JED_VEL_GENERAL_ITEM_VERSION_LABEL', 'a.vulnerable_item_version', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JED_VEL_GENERAL_EXPLOIT_TYPE_LABEL', 'a.exploit_type', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JED_VEL_GENERAL_CONSENT_TO_PROCESS_LABEL', 'a.consent_to_process', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JED_VEL_GENERAL_PASSED_TO_VEL_LABEL', 'a.passed_to_vel', $listDirn, $listOrder); ?>
                    </th>

                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JED_GENERAL_DATE_SUBMITTED_LABEL', 'a.date_submitted', $listDirn, $listOrder); ?>
                    </th>


                    <?php if ($canEdit || $canDelete) : ?>
                        <th class="center">
                            <?php echo Text::_('COM_JED_VEL_REPORTS_LIST_ACTIONS'); ?>
                        </th>
                    <?php endif; ?>

                </tr>
                </thead>
                <tfoot>
                <tr>
                    <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                        <?php echo $this->pagination->getListFooter(); ?>
                    </td>
                </tr>
                </tfoot>
                <tbody>
                <?php foreach ($this->items as $i => $item) : ?>
                    <?php $canEdit = $user->authorise('core.edit', 'com_jed'); ?>

                    <?php if (!$canEdit && $user->authorise('core.edit.own', 'com_jed')) : ?>
                        <?php $canEdit = Factory::getApplication()->getIdentity()->id == $item->created_by; ?>
                    <?php endif; ?>

                    <tr class="row<?php echo $i % 2; ?>">

                        <?php if (isset($this->items[0]->state)) : ?>
                            <?php $class = ($canChange) ? 'active' : 'disabled'; ?>

                        <?php endif; ?>

                        <td>
                            <?php if (isset($item->checked_out) && $item->checked_out) : ?>
                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, 'velreports.', $canCheckin); ?>
                            <?php endif; ?>
                            <a href="<?php echo Route::_('index.php?option=com_jed&view=velreport&id=' . (int) $item->id); ?>">
                                <?php echo $item->id; ?></a>
                        </td>

                        <td>

                            <?php echo $item->vulnerable_item_name; ?>
                        </td>
                        <td>

                            <?php echo $item->vulnerable_item_version; ?>
                        </td>
                        <td>

                            <?php echo $item->exploit_type; ?>
                        </td>
                        <td>

                            <?php echo $item->consent_to_process; ?>
                        </td>
                        <td>

                            <?php echo $item->passed_to_vel; ?>
                        </td>

                        <td>

                            <?php
                            $date = $item->date_submitted;
                            echo $date > 0 ? HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC6')) : '-';
                            ?>                </td>


                        <?php if ($canEdit || $canDelete) : ?>
                            <td class="center">
                                <?php if ($canEdit) : ?>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&task=velreport.edit&id=' . $item->id, false, 2); ?>"
                                       class="btn btn-mini" type="button"><i class="icon-edit"></i></a>
                                <?php endif; ?>
                                <?php if ($canDelete) : ?>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&task=velreportform.remove&id=' . $item->id, false, 2); ?>"
                                       class="btn btn-mini delete-button" type="button"><i class="icon-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canCreate) : ?>
            <a href="<?php echo Route::_('index.php?option=com_jed&task=velreportform.edit&id=0', false, 0); ?>"
               class="btn btn-success btn-small"><i
                        class="icon-plus"></i>
                <?php echo Text::_('COM_JED_GENERAL_ADD_ITEM_LABEL'); ?></a>
        <?php endif; ?>

        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="boxchecked" value="0"/>
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>"/>
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>"/>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>


    <?php
}
?>
