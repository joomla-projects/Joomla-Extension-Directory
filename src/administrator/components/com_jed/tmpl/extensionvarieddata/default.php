<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
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

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/src/Helper/');
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
use Jed\Component\Jed\Administrator\Helper\JedHelper;

// Import CSS
try {
    $wa = $this->getDocument()->getWebAssetManager();
} catch (Exception $e) {
}
$wa->useStyle('com_jed.admin')
    ->useScript('com_jed.admin');

$user      = $this->getCurrentUser();
$userId    = $user->id;
$listOrder = $this->state->get('list.ordering');
$listDirn  = $this->state->get('list.direction');
$canOrder  = $user->authorise('core.edit.state', 'com_jed');
$saveOrder = $listOrder == 'a.ordering';

if ($saveOrder) {
    $saveOrderingUrl = 'index.php?option=com_jed&task=extensionvarieddata.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}

?>

<form action="<?php echo Route::_('index.php?option=com_jed&view=extensionvarieddata'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
            <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="extensionvarieddatumList">
                    <thead>
                    <tr>
                        <th width="1%" >
                            <input type="checkbox" name="checkall-toggle" value=""
                                   title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                        </th>
                        <?php if (isset($this->items[0]->ordering)) : ?>
                        <th width="1%" class="nowrap center hidden-phone">
                            <?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-menu-2'); ?>
                        </th>
                        <?php endif; ?>

                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_FIELD_ID_LABEL', 'a.id', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION', 'a.extension_id', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_SUPPLY_OPTION_LABEL', 'a.supply_option_id', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_INTRO_TEXT_LABEL', 'a.intro_text', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_DESCRIPTION_LABEL', 'a.description', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'JTAG', 'a.tags', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_HOMEPAGE_LINK_LABEL', 'a.homepage_link', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_DOWNLOAD_LINK_LABEL', 'a.download_link', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_DEMO_LINK_LABEL', 'a.demo_link', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_SUPPORT_LINK_LABEL', 'a.support_link', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_DOCUMENTATION_LINK_LABEL', 'a.documentation_link', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_LICENSE_LINK_LABEL', 'a.license_link', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_TRANSLATION_LINK_LABEL', 'a.translation_link', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_UPDATE_URL_LABEL', 'a.update_url', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_UPDATE_URL_LABEL_OK', 'a.update_url_ok', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_DOWNLOAD_INTEGRATION_TYPE_LABEL', 'a.download_integration_type', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_IS_DEFAULT_DATA_LABEL', 'a.is_default_data', $listDirn, $listOrder); ?>
                        </th>
                        <th width="5%" class="nowrap center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_FIELD_CREATED_BY_LABEL', 'a.created_by', $listDirn, $listOrder); ?>
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
                        <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>
                            <td >
                                <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                            </td>

                            <?php if (isset($this->items[0]->ordering)) : ?>
                            <td class="order nowrap center hidden-phone">
                                <?php
                                $iconClass = '';
                                if (!$canChange) {
                                    $iconClass = ' inactive';
                                } elseif (!$saveOrder) {
                                    $iconClass = ' inactive" title="' . Text::_('JORDERINGDISABLED');
                                }
                                ?>
                            <span class="sortable-handler<?php echo $iconClass ?>">
                                <span class="icon-ellipsis-v" aria-hidden="true"></span>
                            </span>
                                <?php if ($canChange && $saveOrder) : ?>
                                <input type="text" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order hidden">
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <td>
                                <?php echo $item->id; ?>
                            </td>
                            <td>
                                <?php echo $item->extension_id; ?>
                            </td>
                            <td>
                                <?php echo $item->supply_option_id; ?>
                            </td>
                            <td>
                                <?php if (isset($item->checked_out) && $item->checked_out && ($canEdit || $canChange)) : ?>
                                    <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, 'extensionvarieddata.', $canCheckin); ?>
                                <?php endif; ?>
                                <?php if ($canEdit) : ?>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&task=extensionvarieddatum.edit&id=' . (int) $item->id); ?>">
                                        <?php echo $this->escape($item->intro_text); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo $this->escape($item->intro_text); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $item->description; ?>
                            </td>
                            <td>
                                <?php echo $item->tags; ?>
                            </td>
                            <td>
                                <?php echo $item->homepage_link; ?>
                            </td>
                            <td>
                                <?php echo $item->download_link; ?>
                            </td>
                            <td>
                                <?php echo $item->demo_link; ?>
                            </td>
                            <td>
                                <?php echo $item->support_link; ?>
                            </td>
                            <td>
                                <?php echo $item->documentation_link; ?>
                            </td>
                            <td>
                                <?php echo $item->license_link; ?>
                            </td>
                            <td>
                                <?php echo $item->translation_link; ?>
                            </td>
                            <td>
                                <?php echo $item->update_url; ?>
                            </td>
                            <td>
                                <?php echo $item->update_url_ok; ?>
                            </td>
                            <td>
                                <?php echo $item->download_integration_type; ?>
                            </td>
                            <td>
                                <?php echo $item->is_default_data; ?>
                            </td>
                            <td>
                                <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'extensionvarieddata.', $canChange, 'cb'); ?>
                            </td>
                            <td>
                                <?php echo $item->created_by; ?>
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
