<?php

/** @var \Jed\Component\Tickets\Site\View\Tickets\HtmlView $this */
/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$user        = $this->getCurrentUser();
$userId      = $user->id;
$listOrder   = $this->state->get('list.ordering', 'id');
$listDirn    = $this->state->get('list.direction', 'DESC');
?>
<form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post" name="adminForm" id="adminForm">
    <fieldset class="mytickets">
        <legend><?php echo Text::_('COM_TICKETS_TICKETS_LIST_HEADER'); ?></legend>
        <?php echo Text::_('COM_TICKETS_TICKETS_LIST_DESCR'); ?>
    </fieldset>
    <?php if (!empty($this->filterForm)) {
        echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]);
    } ?>
    <div class="table-responsive">
        <table class="table table-striped" id="ticketList">
            <thead>
            <tr>

                <th class='left'>
                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_TICKETS_GENERAL_TYPE_LABEL', 'a.`ticket_category_type`', $listDirn, $listOrder); ?>
                </th>
                <th class='left'>
                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_TICKETS_GENERAL_SUBJECT_LABEL', 'a.`ticket_subject`', $listDirn, $listOrder); ?>
                </th>

                <th class='left'>
                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_TICKETS_GENERAL_CREATED_ON_LABEL', 'a.`created_on`', $listDirn, $listOrder); ?>
                </th>

                <th class='left'>
                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.`ticket_status`', $listDirn, $listOrder); ?>
                </th>
                <th class='left'>
                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_TICKETS_TICKETS_ALLOCATED_GROUP_LABEL', 'a.`allocated_group`', $listDirn, $listOrder); ?>
                </th>


                <?php if ($user->id) : ?>
                    <th class="center">
                        <?php echo Text::_('COM_TICKETS_GENERAL_ACTIONS_LABEL'); ?>
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
                <tr class="row<?php echo $i % 2; ?>">
                    <td>
                        <?php echo $item->categorytype_string; ?>
                    </td>
                    <td>
                        <a href="<?php echo Route::_('index.php?option=com_tickets&view=ticket&id=' . (int) $item->id); ?>">
                            <?php echo $this->escape($item->ticket_subject); ?>
                        </a>
                    </td>
                    <td>
                        <?php echo (new DateTime($item->created_on))->format("d M y H:i"); ?>
                    </td>
                    <td>
                        <?php echo $item->ticket_status; ?>
                    </td>
                    <td>
                        <?php echo $item->ticketallocatedgroup_string; ?>
                    </td>
                    <?php if ($user->id) : ?>
                        <td class="center">
                            <a
                                href="<?php echo Route::_('index.php?option=com_tickets&view=ticket&id=' . $item->id); ?>"
                                class="btn btn-mini" type="button"><i class="icon-edit"></i></a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>"/>
    <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
