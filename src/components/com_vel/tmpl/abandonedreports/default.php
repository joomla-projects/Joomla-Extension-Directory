<?php

/** @var \Jed\Component\Vel\Site\View\abandonedreports\HtmlView $this */
/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
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


$user        = $this->getCurrentUser();
$userId      = $user->id;
$listOrder   = $this->state->get('list.ordering', 'id');
$listDirn    = $this->state->get('list.direction', 'DESC');
$canCreate   = $user->authorise('core.create', 'com_vel');
$canEdit     = $user->authorise('core.edit', 'com_vel');
$canCheckin  = $user->authorise('core.manage', 'com_vel');
$canChange   = $user->authorise('core.edit.state', 'com_vel');
$canDelete   = $user->authorise('core.delete', 'com_vel');
$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();

// Import CSS
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useStyle('com_jed.list');
if (!$isLoggedIn) {
    try {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();
    } catch (Exception $e) {
        throw new Exception($e->getMessage(), $e->getCode());
    }

    $app->enqueueMessage(Text::_('COM_VEL_ABANDONEDREPORTS_NO_ACCESS'), 'success');
    $app->redirect($redirectURL);
} else {
    ?>

    <form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
          name="adminForm" id="adminForm">
        <?php echo '<fieldset class="velabandonedreports"><legend>' . Text::_('COM_VEL_MYABANDONEDREPORTS_LIST_TITLE') . '</legend>' . Text::_('COM_VEL_MYABANDONEDREPORTS_LIST_DESCR') . '</fieldset>'; ?>
        <?php if (!empty($this->filterForm)) {
            echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]);
        } ?>
        <div class="table-responsive">
            <table class="table table-striped" id="abandonedreportList">
                <thead>
                <tr>
                    <?php if (isset($this->items[0]->state)) : ?>
                    <?php endif; ?>

                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_FIELD_ID_LABEL', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_VEL_GENERAL_NAME_LABEL', 'a.reporter_fullname', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_VEL_GENERAL_EMAIL_LABEL', 'a.reporter_email', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_VEL_GENERAL_ORGANISATION_LABEL', 'a.reporter_organisation', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_VEL_EXTENSION', 'a.extension_name', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_VEL_GENERAL_DEVELOPER_NAME_LABEL', 'a.developer_name', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_VEL_GENERAL_VERSION_LABEL', 'a.extension_version', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_VEL_GENERAL_CONSENT_TO_PROCESS_LABEL', 'a.consent_to_process', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_VEL_GENERAL_PASSED_TO_VEL_LABEL', 'a.passed_to_vel', $listDirn, $listOrder); ?>
                    </th>

                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_VEL_GENERAL_DATE_SUBMITTED_LABEL', 'a.date_submitted', $listDirn, $listOrder); ?>
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
                <tbody>
                <?php foreach ($this->items as $i => $item) : ?>
                    <?php $canEdit = $user->authorise('core.edit', 'com_vel'); ?>

                    <?php if (!$canEdit && $user->authorise('core.edit.own', 'com_vel')) : ?>
                        <?php $canEdit = $this->getCurrentUser()->id == $item->created_by; ?>
                    <?php endif; ?>

                    <tr class="row<?php echo $i % 2; ?>">

                        <?php if (isset($this->items[0]->state)) : ?>
                            <?php $class = ($canChange) ? 'active' : 'disabled'; ?>

                        <?php endif; ?>

                        <td>

                            <?php echo $item->id; ?>
                        </td>
                        <td>

                            <a href="<?php echo Route::_('index.php?option=com_vel&view=abandonedreportform&id=' . (int) $item->id); ?>">
                                <?php echo $this->escape($item->reporter_fullname); ?></a>
                        </td>
                        <td>

                            <?php echo $item->reporter_email; ?>
                        </td>
                        <td>

                            <?php echo $item->reporter_organisation; ?>
                        </td>
                        <td>

                            <?php echo $item->extension_name; ?>
                        </td>
                        <td>

                            <?php echo $item->developer_name; ?>
                        </td>
                        <td>

                            <?php echo $item->extension_version; ?>
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




                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canCreate) : ?>
            <a href="<?php echo Route::_('index.php?option=com_vel&task=abandonedreportform.edit&id=0', false, 0); ?>"
               class="btn btn-success btn-small"><i
                        class="icon-plus"></i>
                <?php echo Text::_('COM_VEL_GENERAL_ADD_ITEM_LABEL'); ?></a>
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
