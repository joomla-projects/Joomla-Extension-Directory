<?php

/** @var \Jed\Component\Jed\Site\View\Review\HtmlView $this */
/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

$currentUserId = $this->getCurrentUser()->id;
$isOwnReview   = $currentUserId == $this->item->created_by;

$canEdit = $this->getCurrentUser()->authorise('core.edit', 'com_jed');

if (!$canEdit && $this->getCurrentUser()->authorise('core.edit.own', 'com_jed')) {
    $canEdit = $isOwnReview;
}

$canRespond = JedHelper::isOwnerOrMaintainer((int) $this->item->extension_id);
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
            <th><?php echo Text::_('COM_JED_GENERAL_TITLE_LABEL'); ?></th>
            <td><?php echo $this->item->title; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('JALIAS'); ?></th>
            <td><?php echo $this->item->alias; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_BODY_LABEL'); ?></th>
            <td><?php echo nl2br((string) $this->item->body); ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_FUNCTIONALITY_LABEL'); ?></th>
            <td><?php echo number_format((float) $this->item->functionality, 1); ?> / 5</td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_FUNCTIONALITY_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->functionality_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_EASE_OF_USE_LABEL'); ?></th>
            <td><?php echo number_format((float) $this->item->ease_of_use, 1); ?> / 5</td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_EASE_OF_USE_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->ease_of_use_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_SUPPORT_LABEL'); ?></th>
            <td><?php echo number_format((float) $this->item->support, 1); ?> / 5</td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_GENERAL_SUPPORT_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->support_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL'); ?></th>
            <td><?php echo number_format((float) $this->item->documentation, 1); ?> / 5</td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->documentation_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL'); ?></th>
            <td><?php echo number_format((float) $this->item->value_for_money, 1); ?> / 5</td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL_COMMENT'); ?></th>
            <td><?php echo $this->item->value_for_money_comment; ?></td>
        </tr>

        <tr>
            <th><?php echo Text::_('COM_JED_REVIEWS_OVERALL_SCORE_LABEL'); ?></th>
            <td><?php echo number_format((float) $this->item->overall_score, 1); ?> / 5</td>
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

<?php if (($isOwnReview && $this->item->published != -2) || JedHelper::isAdminOrSuperUser()) : ?>
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
                                                'footer'     => '<button class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button><a href="' . Route::_('index.php?option=com_jed&task=review.remove&id=' . $this->item->id . '&' . Session::getFormToken() . '=1', false) . '" class="btn btn-danger">' . Text::_('JACTION_DELETE') . '</a>',
                                            ],
        Text::sprintf('COM_JED_GENERAL_DELETE_CONFIRM_LABEL', $this->item->id)
    ); ?>

<?php endif; ?>

<?php if ($isOwnReview && $this->item->published != 1 && $this->item->published != -2) : ?>
    <p class="alert alert-info mt-3"><?php echo Text::_('COM_JED_REVIEW_NOT_PUBLISHED_NOTICE'); ?></p>
<?php endif; ?>

<?php if ($canRespond) : ?>
    <div class="mt-4">
        <h3><?php echo Text::_('COM_JED_REVIEW_DEVELOPER_RESPONSE_HEADING_OWN'); ?></h3>

        <?php if (empty($this->item->developer_response) && $this->item->developer_response_published != -2) : ?>
            <p class="text-muted"><?php echo Text::_('COM_JED_REVIEW_CAN_RESPOND_NOTICE'); ?></p>
            <form action="<?php echo Route::_('index.php?option=com_jed&task=review.saveResponse'); ?>" method="post">
                <div class="mb-2">
                    <textarea class="form-control" name="developer_response" rows="4" required></textarea>
                </div>
                <input type="hidden" name="id" value="<?php echo (int) $this->item->id; ?>">
                <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_JED_REVIEW_DEVELOPER_RESPONSE_SUBMIT'); ?></button>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        <?php else : ?>
            <p><?php echo nl2br($this->escape($this->item->developer_response)); ?></p>
            <?php if ($this->item->developer_response_published == 1) : ?>
                <p class="text-success"><?php echo Text::_('COM_JED_REVIEW_DEVELOPER_RESPONSE_STATUS_PUBLISHED'); ?></p>
            <?php elseif ($this->item->developer_response_published == -2) : ?>
                <p class="text-muted"><?php echo Text::_('COM_JED_REVIEW_DEVELOPER_RESPONSE_STATUS_DELETED'); ?></p>
            <?php else : ?>
                <p class="text-muted"><?php echo Text::_('COM_JED_REVIEW_DEVELOPER_RESPONSE_STATUS_PENDING'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
