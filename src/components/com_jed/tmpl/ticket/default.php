<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Jed\Component\Jed\Administrator\Helper\JedHelper;

$canEdit = $this->getCurrentUser()->authorise('core.edit', 'com_jed');

if (!$canEdit && $this->getCurrentUser()->authorise('core.edit.own', 'com_jed')) {
    $canEdit = $this->getCurrentUser()->id == $this->item->created_by;
}
$wa = $this->getDocument()->getWebAssetManager();

$wa->getRegistry()->addExtensionRegistryFile('com_jed');
$wa->useStyle('com_jed.oldjed');
HTMLHelper::_('bootstrap.tooltip');
?>

<div class="item_fields">

    <table class="table">


        <tr>
            <th><?php echo Text::_('JGLOBAL_FIELD_ID_LABEL'); ?></th>
            <td><?php echo $this->item->id; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_TICKET_ORIGIN_LABEL'); ?></th>
            <td><?php echo $this->item->ticket_origin; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_TYPE_LABEL'); ?></th>
            <td><?php echo $this->item->ticket_category_type; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_SUBJECT_LABEL'); ?></th>
            <td><?php echo $this->item->ticket_subject; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_TICKET_TEXT_LABEL'); ?></th>
            <td><?php echo nl2br($this->item->ticket_text); ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_INTERNAL_NOTES_LABEL'); ?></th>
            <td><?php echo nl2br($this->item->internal_notes); ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_UPLOADED_FILES_PREVIEW_LABEL'); ?></th>
            <td><?php echo $this->item->uploaded_files_preview; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_UPLOADED_FILES_LOCATION_LABEL'); ?></th>
            <td><?php echo $this->item->uploaded_files_location; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_ALLOCATED_GROUP_LABEL'); ?></th>
            <td><?php echo $this->item->allocated_group; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_ALLOCATED_TO_LABEL'); ?></th>
            <td><?php echo $this->item->allocated_to_name; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_LINKED_ITEM_TYPE_LABEL'); ?></th>
            <td><?php echo $this->item->linked_item_type; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_LINKED_ITEM_ID_LABEL'); ?></th>
            <td><?php echo $this->item->linked_item_id; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('JSTATUS'); ?></th>
            <td><?php echo $this->item->ticket_status; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETS_PARENT_ID_LABEL'); ?></th>
            <td><?php echo $this->item->parent_id; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_STATE_LABEL'); ?></th>
            <td>
                <i class="icon-<?php echo ($this->item->state == 1) ? 'publish' : 'unpublish'; ?>"></i></td>
        </tr>

        <tr>
            <th><?php echo Text::_('JGLOBAL_FIELD_CREATED_BY_LABEL'); ?></th>
            <td><?php echo $this->item->created_by_name; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'); ?></th>
            <td><?php echo $this->item->created_on; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_BY_LABEL'); ?></th>
            <td><?php echo $this->item->modified_by_name; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_MODIFIED_ON_LABEL'); ?></th>
            <td><?php echo $this->item->modified_on; ?></td>
        </tr>

    </table>

</div>

<?php $canCheckin = $this->getCurrentUser()->authorise('core.manage', 'com_jed.' . $this->item->id) || $this->item->checked_out == $this->getCurrentUser()->id; ?>
<?php if ($canEdit && $this->item->checked_out == 0) : ?>
    <a class="btn btn-outline-primary"
       href="<?php echo Route::_('index.php?option=com_jed&task=ticket.edit&id=' . $this->item->id); ?>"><?php echo Text::_("JGLOBAL_EDIT"); ?></a>
<?php elseif ($canCheckin && $this->item->checked_out > 0) : ?>
    <a class="btn btn-outline-primary"
       href="<?php echo Route::_('index.php?option=com_jed&task=ticket.checkin&id=' . $this->item->id . '&' . Session::getFormToken() . '=1'); ?>"><?php echo Text::_("JLIB_HTML_CHECKIN"); ?></a>

<?php endif; ?>

