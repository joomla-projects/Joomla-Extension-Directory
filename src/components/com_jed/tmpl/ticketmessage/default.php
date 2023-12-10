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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Jed\Component\Jed\Administrator\Helper\JedHelper;

$canEdit = Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_jed');

if (!$canEdit && Factory::getApplication()->getIdentity()->authorise('core.edit.own', 'com_jed')) {
    $canEdit = Factory::getApplication()->getIdentity()->id == $this->item->created_by;
}
?>

<div class="item_fields">

    <table class="table">


        <tr>
            <th><?php echo Text::_('COM_JED_TICKETMESSAGE_FIELD_SUBJECT_LABEL'); ?></th>
            <td><?php echo $this->item->subject; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETMESSAGE_FIELD_MESSAGE_LABEL'); ?></th>
            <td><?php echo nl2br($this->item->message); ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_TICKETMESSAGE_FIELD_TICKET_ID_LABEL'); ?></th>
            <td><?php echo $this->item->ticket_id; ?></td>
        </tr>

    </table>

</div>

<?php $canCheckin = Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jed.' . $this->item->id) || $this->item->checked_out == Factory::getApplication()->getIdentity()->id; ?>
<?php if ($canEdit && $this->item->checked_out == 0) : ?>
    <a class="btn btn-outline-primary"
       href="<?php echo Route::_('index.php?option=com_jed&task=ticketmessage.edit&id=' . $this->item->id); ?>"><?php echo Text::_("JGLOBAL_EDIT"); ?></a>
<?php elseif ($canCheckin && $this->item->checked_out > 0) : ?>
    <a class="btn btn-outline-primary"
       href="<?php echo Route::_('index.php?option=com_jed&task=ticketmessage.checkin&id=' . $this->item->id . '&' . Session::getFormToken() . '=1'); ?>"><?php echo Text::_("JLIB_HTML_CHECKIN"); ?></a>

<?php endif; ?>

