<?php
/**
 * @package       JED
 *
 * @subpackage    VEL
 *
 * @copyright     (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;


use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Jed\Component\Jed\Administrator\Helper\JedHelper;

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/src/Helper/');
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');


$user      = JedHelper::getUser();
$userId    = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn  = $this->state->get('list.direction');
$canOrder  = $user->authorise('core.edit.state', 'com_jed');
$saveOrder = $listOrder == 'a.`ordering`';

if ($saveOrder)
{
	$saveOrderingUrl = 'index.php?option=com_jed&task=velreports.saveOrderAjax&tmpl=component';
	HTMLHelper::_('sortablelist.sortable', 'velreportList', 'adminForm', strtolower($listDirn), $saveOrderingUrl);
}

// $sortFields = $this->getSortFields();
?>

<form action="<?php echo Route::_('index.php?option=com_jed&view=velreports'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
				<?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="velreportList">
                    <thead>
                    <tr>
						<?php if (isset($this->items[0]->ordering)): ?>
                            <th width="1%" class="nowrap center hidden-phone">
								<?php echo HTMLHelper::_('searchtools.sort', '', 'a.`ordering`', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-menu-2'); ?>
                            </th>
						<?php endif; ?>
                        <th width="1%">
                            <input type="checkbox" name="checkall-toggle" value=""
                                   title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                        </th>
						<?php if (isset($this->items[0]->state)): ?>

						<?php endif; ?>

                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_FIELD_ID_LABEL', 'a.`id`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_FIELD_NAME_LABEL', 'a.`reporter_fullname`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_FIELD_EMAIL_LABEL', 'a.`reporter_email`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_FIELD_ORGANISATION_LABEL', 'a.`reporter_organisation`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_FIELD_PASS_DETAILS_OK_LABEL', 'a.`pass_details_ok`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_FIELD_VULNERABILITY_TYPE_LABEL', 'a.`vulnerability_type`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_FIELD_ITEM_NAME_LABEL', 'a.`vulnerable_item_name`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_FIELD_ITEM_VERSION_LABEL', 'a.`vulnerable_item_version`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_FIELD_EXPLOIT_TYPE_LABEL', 'a.`exploit_type`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_FIELD_CONSENT_TO_PROCESS_LABEL', 'a.`consent_to_process`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_FIELD_PASSED_TO_VEL_LABEL', 'a.`passed_to_vel`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_VEL_GENERAL_FIELD_DATA_SOURCE_LABEL', 'a.`data_source`', $listDirn, $listOrder); ?>
                        </th>
                        <th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_FIELD_DATE_SUBMITTED_LABEL', 'a.`date_submitted`', $listDirn, $listOrder); ?>
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
					<?php foreach ($this->items as $i => $item) :
						$ordering = ($listOrder == 'a.ordering');
						$canCreate = $user->authorise('core.create', 'com_jed');
						$canEdit = $user->authorise('core.edit', 'com_jed');
						$canCheckin = $user->authorise('core.manage', 'com_jed');
						$canChange = $user->authorise('core.edit.state', 'com_jed');
						?>
                        <tr class="row<?php echo $i % 2; ?>">

							<?php if (isset($this->items[0]->ordering)) : ?>
                                <td class="order nowrap center hidden-phone">
									<?php if ($canChange) :
										$disableClassName = '';
										$disabledLabel = '';

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
							<?php if (isset($this->items[0]->state)): ?>

							<?php endif; ?>

                            <td>

								<?php echo $item->id; ?>
                            </td>
                            <td>
								<?php if (isset($item->checked_out) && $item->checked_out && ($canEdit || $canChange)) : ?>
									<?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, 'velreports.', $canCheckin); ?>
								<?php endif; ?>
								<?php if ($canEdit) : ?>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&task=velreport.edit&id=' . (int) $item->id); ?>">
										<?php echo $this->escape($item->reporter_fullname); ?></a>
								<?php else : ?>
									<?php echo $this->escape($item->reporter_fullname); ?>
								<?php endif; ?>

                            </td>
                            <td>

								<?php echo $item->reporter_email; ?>
                            </td>
                            <td>

								<?php echo $item->reporter_organisation; ?>
                            </td>
                            <td>

								<?php echo $item->pass_details_ok; ?>
                            </td>
                            <td>

								<?php echo $item->vulnerability_type; ?>
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
<script>
    window.Joomla = window.Joomla || {};

    (function (window, Joomla) {
        Joomla.toggleField = function (id, task, field) {

            let f = document.adminForm, i = 0, cbx, cb = f[id];

            if (!cb) return false;

            while (true) {
                cbx = f['cb' + i];

                if (!cbx) break;

                cbx.checked = false;
                i++;
            }

            let inputField = document.createElement('input');

            inputField.type = 'hidden';
            inputField.name = 'field';
            inputField.value = field;
            f.appendChild(inputField);

            cb.checked = true;
            f.boxchecked.value = 1;
            Joomla.submitform(task);

            return false;
        };
    })(window, Joomla);
</script>