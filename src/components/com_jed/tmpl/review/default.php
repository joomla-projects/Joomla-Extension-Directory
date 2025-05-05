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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

$canEdit = $this->getCurrentUser()->authorise('core.edit', 'com_jed');

if (!$canEdit && $this->getCurrentUser()->authorise('core.edit.own', 'com_jed')) {
    $canEdit = $this->getCurrentUser()->id == $this->item->created_by;
}
?>

<div class="item_fields">

    <table class="table">


        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_ID_LABEL'); ?></th>
            <td><?php echo $this->item->id; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_EXTENSION'); ?></th>
            <td><?php echo $this->item->extension_id; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_EXTENSION_SUPPLY_OPTION_ID_LABEL'); ?></th>
            <td><?php echo $this->item->supply_option_id; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_TITLE_LABEL'); ?></th>
            <td><?php echo $this->item->title; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('JALIAS'); ?></th>
            <td><?php echo $this->item->alias; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_BODY_LABEL'); ?></th>
            <td><?php echo nl2br($this->item->body); ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_FUNCTIONALITY_LABEL'); ?></th>
            <td><?php echo $this->item->functionality; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_FUNCTIONALITY_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->functionality_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_EASE_OF_USE_LABEL'); ?></th>
            <td><?php echo $this->item->ease_of_use; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_EASE_OF_USE_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->ease_of_use_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_SUPPORT_LABEL'); ?></th>
            <td><?php echo $this->item->support; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_SUPPORT_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->support_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL'); ?></th>
            <td><?php echo $this->item->documentation; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->documentation_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL'); ?></th>
            <td><?php echo $this->item->value_for_money; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->value_for_money_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_OVERALL_SCORE_LABEL'); ?></th>
            <td><?php echo $this->item->overall_score; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_USED_FOR_LABEL'); ?></th>
            <td><?php echo $this->item->used_for; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_FLAGGED_LABEL'); ?></th>
            <td><?php echo $this->item->flagged; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_IPADDRESS_LABEL'); ?></th>
            <td><?php echo $this->item->ip_address; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('JPUBLISHED'); ?></th>
            <td><?php echo $this->item->published; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'); ?></th>
            <td><?php echo $this->item->created_on; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('JGLOBAL_FIELD_CREATED_BY_LABEL'); ?></th>
            <td><?php echo $this->item->created_by_name; ?></td>
        </tr>

    </table>

</div>

<?php $canCheckin = $this->getCurrentUser()->authorise('core.manage', 'com_jed.' . $this->item->id) || $this->item->checked_out == $this->getCurrentUser()->id; ?>
    <?php if ($canEdit && $this->item->checked_out == 0) : ?>
    <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_jed&task=review.edit&id=' . $this->item->id); ?>"><?php echo Text::_("JACTION_EDIT"); ?></a>
    <?php elseif ($canCheckin && $this->item->checked_out > 0) : ?>
    <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_jed&task=review.checkin&id=' . $this->item->id . '&' . Session::getFormToken() . '=1'); ?>"><?php echo Text::_("JLIB_HTML_CHECKIN"); ?></a>

    <?php endif; ?>

<?php if ($this->getCurrentUser()->authorise('core.delete', 'com_jed.review.' . $this->item->id)) : ?>
    <a class="btn btn-danger" rel="noopener noreferrer" href="#deleteModal" role="button" data-bs-toggle="modal">
        <?php echo Text::_("JACTION_DELETE"); ?>
    </a>

    <?php echo HTMLHelper::_(
        'bootstrap.renderModal',
        'deleteModal',
        [
                                                'title'  => Text::_('JACTION_DELETE'),
                                                'height' => '50%',
                                                'width'  => '20%',

                                                'modalWidth' => '50',
                                                'bodyHeight' => '100',
                                                'footer'     => '<button class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button><a href="' . Route::_('index.php?option=com_jed&task=review.remove&id=' . $this->item->id, false, 2) . '" class="btn btn-danger">' . Text::_('JACTION_DELETE') . '</a>',
                                            ],
        Text::sprintf('COM_JED_GENERAL_DELETE_CONFIRM_LABEL', $this->item->id)
    ); ?>

<?php endif; ?>
