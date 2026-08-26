<?php

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

use Jed\Component\Jed\Administrator\Listing\ListingStatus;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Jed\Component\Jed\Site\View\Dashboard\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip');

$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();

if (!$isLoggedIn) {
    try {
        $app = Factory::getApplication();
        $app->enqueueMessage(Text::_('COM_JED_DASHBOARD_NO_ACCESS_LABEL'), 'warning');
        $app->redirect($redirectURL);
    } catch (Exception $e) {
        echo $e->getMessage();
    }
    return;
}

$user   = $this->getCurrentUser();
$userId = $user->id;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useStyle('com_jed.style');
$wa->useScript('com_jed.favorite');

?>
<div id="jed-favorite-i18n" class="d-none"
     data-ajax-url="<?php echo Route::_('index.php?option=com_jed&format=raw'); ?>"
     data-csrf-token="<?php echo Session::getFormToken(); ?>"
     data-msg-no-entries="<?php echo Text::_('COM_JED_DASHBOARD_NO_ENTRIES'); ?>"></div>
<div class="com-jed-dashboard">

    <?php /* ---- Ownership handovers ---- */ ?>
    <?php if (!empty($this->transfers) || !empty($this->transferable)) : ?>
        <?php
        // Status and the form to start one live in the same card on purpose: the first question
        // an owner has after sending a request is what it is waiting on (8.8.1), and the second
        // is whether it went out at all. Splitting them across the page would answer neither.
        //
        // The other party is named by *name*. Showing their address here would disclose something
        // they never shared with whoever is reading this page.
        ?>
        <div class="card mb-4 border-warning">
            <div class="card-header">
                <h3 class="mb-0"><?php echo Text::_('COM_JED_TRANSFER_HEADING'); ?></h3>
            </div>
            <div class="card-body">
                <?php if (!empty($this->transfers)) : ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($this->transfers as $transfer) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <strong><?php echo $this->escape($transfer->extension_name); ?></strong>
                                    <span class="text-muted small d-block">
                                        <?php if ($transfer->awaiting_me) : ?>
                                            <?php echo Text::_('COM_JED_TRANSFER_WAITING_FOR_YOU'); ?>
                                        <?php else : ?>
                                            <?php echo Text::sprintf('COM_JED_TRANSFER_WAITING_FOR_OTHER', $this->escape($transfer->other_name)); ?>
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?php echo Route::_(
                                       'index.php?option=com_jed&task=transfer.cancel&id=' . (int) $transfer->id
                                       . '&' . Session::getFormToken() . '=1',
                                       false
                                   ); ?>">
                                    <?php echo Text::_('COM_JED_TRANSFER_CANCEL'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($this->transferable)) : ?>
                    <form action="<?php echo Route::_('index.php?option=com_jed&task=transfer.request'); ?>"
                          method="post" class="row g-2 align-items-end">
                        <div class="col-12">
                            <p class="text-muted small mb-2">
                                <?php echo Text::_('COM_JED_TRANSFER_FORM_INTRO'); ?>
                            </p>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="jed-transfer-extension">
                                <?php echo Text::_('COM_JED_TRANSFER_FORM_EXTENSION'); ?>
                            </label>
                            <select class="form-select" id="jed-transfer-extension" name="extension_id" required>
                                <?php foreach ($this->transferable as $candidate) : ?>
                                    <option value="<?php echo (int) $candidate->id; ?>">
                                        <?php echo $this->escape($candidate->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="jed-transfer-email">
                                <?php echo Text::_('COM_JED_TRANSFER_FORM_EMAIL'); ?>
                            </label>
                            <?php // The account address of the new owner. They must already have one. ?>
                            <input type="email" class="form-control" id="jed-transfer-email"
                                   name="recipient_email" required
                                   placeholder="<?php echo $this->escape(Text::_('COM_JED_TRANSFER_FORM_EMAIL_HINT')); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-warning w-100">
                                <?php echo Text::_('COM_JED_TRANSFER_FORM_SUBMIT'); ?>
                            </button>
                        </div>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php /* ---- Maintainer invitations ---- */ ?>
    <?php if (!empty($this->invitations)) : ?>
        <?php
        // A maintainer invitation grants nothing until it is answered (8.8, P1-03 item 4), so
        // this is where being named turns into actually being able to do something. Placed
        // first on the page because it is the only item here that is waiting on the reader.
        ?>
        <div class="card mb-4 border-info">
            <div class="card-header">
                <h3 class="mb-0"><?php echo Text::_('COM_JED_MAINTAINER_INVITE_HEADING'); ?></h3>
            </div>
            <div class="card-body">
                <p><?php echo Text::_('COM_JED_MAINTAINER_INVITE_INTRO'); ?></p>
                <ul class="list-group">
                    <?php foreach ($this->invitations as $invite) : ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <strong><?php echo $this->escape($invite->extension_name); ?></strong>
                                <?php if (!empty($invite->invited_by_name)) : ?>
                                    <span class="text-muted small">
                                        &mdash; <?php echo $this->escape($invite->invited_by_name); ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <span class="btn-group btn-group-sm">
                                <?php foreach ([1 => 'ACCEPT', 0 => 'DECLINE'] as $accept => $label) : ?>
                                    <a class="btn <?php echo $accept ? 'btn-success' : 'btn-outline-secondary'; ?>"
                                       href="<?php echo Route::_(
                                           'index.php?option=com_jed&task=extension.respondToInvitation'
                                           . '&extension_id=' . (int) $invite->extension_id
                                           . '&accept=' . $accept
                                           . '&' . Session::getFormToken() . '=1',
                                           false
                                       ); ?>">
                                        <?php echo Text::_('COM_JED_MAINTAINER_INVITE_' . $label); ?>
                                    </a>
                                <?php endforeach; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php /* ---- Reviews ---- */ ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="mb-0"><?php echo Text::_('COM_JED_DASHBOARD_REVIEWS_HEADER'); ?></h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_JED_DASHBOARD_COL_EXTENSION'); ?></th>
                            <th><?php echo Text::_('COM_JED_DASHBOARD_COL_REVIEW_TITLE'); ?></th>
                            <th><?php echo Text::_('COM_JED_REVIEWS_OVERALL_SCORE_LABEL'); ?></th>
                            <th><?php echo Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'); ?></th>
                            <th><?php echo Text::_('JSTATUS'); ?></th>
                            <th><?php echo Text::_('JACTION'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($this->reviews)) : ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                <?php echo Text::_('COM_JED_DASHBOARD_NO_ENTRIES'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($this->reviews as $i => $item) :
                            $isOwnExtensionRow = !empty($item->is_own_extension);
                            $isOwnReviewRow    = (int) $item->created_by === (int) $userId;
                            $rowClass          = 'row' . ($i % 2) . ($isOwnExtensionRow ? ' border border-danger' : '');
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><?php echo $this->escape($item->extension_title ?? '—'); ?></td>
                                <td>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&view=review&id=' . (int) $item->id); ?>">
                                        <?php echo $this->escape($item->title); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format((float) $item->overall_score, 1); ?> / 5</td>
                                <td>
                                    <?php
                                    if (!empty($item->created_on)) {
                                        try {
                                            echo (new DateTime($item->created_on))->format('d M Y');
                                        } catch (Exception) {
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ((int) $item->state === -2) {
                                        echo Text::_('JTRASHED');
                                    } elseif ((int) $item->state === 1) {
                                        echo Text::_('JPUBLISHED');
                                    } else {
                                        echo Text::_('JUNPUBLISHED');
                                    }
                                    ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($isOwnReviewRow && (int) $item->state !== -2) : ?>
                                        <a class="btn btn-danger btn-sm"
                                           href="<?php echo Route::_('index.php?option=com_jed&task=review.remove&id=' . (int) $item->id . '&' . Session::getFormToken() . '=1', false); ?>"
                                           onclick="return confirm('<?php echo htmlspecialchars(addslashes(Text::_('COM_JED_DASHBOARD_DELETE_REVIEW_CONFIRM')), ENT_QUOTES); ?>');">
                                            <?php echo Text::_('COM_JED_DASHBOARD_DELETE_REVIEW'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($isOwnExtensionRow && !empty($item->developer_response) && (int) $item->developer_response_published !== -2) : ?>
                                        <a class="btn btn-danger btn-sm"
                                           href="<?php echo Route::_('index.php?option=com_jed&task=review.deleteResponse&id=' . (int) $item->id . '&' . Session::getFormToken() . '=1', false); ?>"
                                           onclick="return confirm('<?php echo htmlspecialchars(addslashes(Text::_('COM_JED_DASHBOARD_DELETE_RESPONSE_CONFIRM')), ENT_QUOTES); ?>');">
                                            <?php echo Text::_('COM_JED_DASHBOARD_DELETE_RESPONSE'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($isOwnExtensionRow) : ?>
                                        <a class="btn btn-outline-secondary btn-sm"
                                           href="<?php echo Route::_('index.php?option=com_tickets&view=ticketform&litem=' . TicketType::Review->value . '&lid=' . (int) $item->id . '&vr=' . (int) $item->extension_id); ?>">
                                            <?php echo Text::_('COM_JED_DASHBOARD_REPORT_REVIEW'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($this->reviewsPagination->total > $this->reviewsPagination->limit) : ?>
                        <tfoot>
                            <tr>
                                <td colspan="6"><?php echo $this->reviewsPagination->getListFooter(); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <?php /* ---- Favourite Extensions ---- */ ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="mb-0"><?php echo Text::_('COM_JED_DASHBOARD_FAVOURITES_HEADER'); ?></h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_JED_EXTENSION_NAME_LABEL'); ?></th>
                            <th><?php echo Text::_('JCATEGORY'); ?></th>
                            <th><?php echo Text::_('COM_JED_DASHBOARD_COL_DATE_ADDED'); ?></th>
                            <th><?php echo Text::_('JACTION'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="jed-favorites-tbody">
                    <?php if (empty($this->favorites)) : ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                <?php echo Text::_('COM_JED_DASHBOARD_NO_ENTRIES'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($this->favorites as $i => $item) : ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&view=extension&id=' . (int) $item->extension_id); ?>">
                                        <?php echo $this->escape($item->name ?? '—'); ?>
                                    </a>
                                </td>
                                <td><?php echo $this->escape($item->category_title ?? '—'); ?></td>
                                <td>
                                    <?php
                                    if (!empty($item->created)) {
                                        try {
                                            echo (new DateTime($item->created))->format('d M Y');
                                        } catch (Exception) {
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="jed-favorite-remove-btn btn btn-danger btn-sm"
                                            data-extension-id="<?php echo (int) $item->extension_id; ?>"
                                            data-confirm="<?php echo htmlspecialchars(Text::_('COM_JED_DASHBOARD_DELETE_FAVORITE_CONFIRM'), ENT_QUOTES); ?>">
                                        <?php echo Text::_('COM_JED_DASHBOARD_DELETE_FAVORITE'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($this->favoritesPagination->total > $this->favoritesPagination->limit) : ?>
                        <tfoot>
                            <tr>
                                <td colspan="4"><?php echo $this->favoritesPagination->getListFooter(); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <?php /* ---- Extensions (as Owner) ---- */ ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="mb-0"><?php echo Text::_('COM_JED_DASHBOARD_OWNED_EXTENSIONS_HEADER'); ?></h3>
            <a href="<?php echo Route::_('index.php?option=com_jed&view=newextension'); ?>" class="btn btn-primary btn-sm">
                <?php echo Text::_('COM_JED_DASHBOARD_SUBMIT_EXTENSION'); ?>
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_JED_EXTENSION_NAME_LABEL'); ?></th>
                            <th><?php echo Text::_('COM_JED_GENERAL_VERSION_LABEL'); ?></th>
                            <th><?php echo Text::_('JCATEGORY'); ?></th>
                            <th><?php echo Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'); ?></th>
                            <th><?php echo Text::_('JSTATUS'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($this->extensions)) : ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                <?php echo Text::_('COM_JED_DASHBOARD_NO_ENTRIES'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($this->extensions as $i => $item) : ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&task=extensionform.edit&id=' . (int) $item->id); ?>">
                                        <?php echo $this->escape($item->name ?? '—'); ?>
                                    </a>
                                </td>
                                <td><?php echo $this->escape($item->extension_version ?? '—'); ?></td>
                                <td><?php echo $this->escape($item->category_title ?? '—'); ?></td>
                                <td>
                                    <?php
                                    if (!empty($item->created)) {
                                        try {
                                            echo (new DateTime($item->created))->format('d M Y');
                                        } catch (Exception) {
                                        }
                                    }
                                    ?>
                                </td>
                                <?php
                                // Six states, not two. "Unpublished" used to cover waiting for
                                // review, turned down, taken offline and blocked alike - 13.6
                                // lists exactly that as a design gap, and it is the one thing a
                                // developer opens this page to find out.
                                $status = ListingStatus::forItem($item);
                                ?>
                                <td>
                                    <span class="badge <?php echo $status->badgeClass(); ?>">
                                        <?php echo Text::_($status->label()); ?>
                                    </span>
                                    <?php if ($status === ListingStatus::REJECTED && !empty($item->approved_notes)) : ?>
                                        <div class="small text-muted mt-1">
                                            <?php echo $this->escape($item->approved_notes); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($this->extensionsPagination->total > $this->extensionsPagination->limit) : ?>
                        <tfoot>
                            <tr>
                                <td colspan="5"><?php echo $this->extensionsPagination->getListFooter(); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <?php /* ---- Tickets ---- */ ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="mb-0"><?php echo Text::_('COM_JED_DASHBOARD_TICKETS_HEADER'); ?></h3>
            <a href="<?php echo Route::_('index.php?option=com_tickets&view=ticketform'); ?>" class="btn btn-primary btn-sm">
                <?php echo Text::_('COM_JED_DASHBOARD_CREATE_TICKET'); ?>
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_JED_GENERAL_TYPE_LABEL'); ?></th>
                            <th><?php echo Text::_('COM_JED_GENERAL_SUBJECT_LABEL'); ?></th>
                            <th><?php echo Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'); ?></th>
                            <th><?php echo Text::_('JSTATUS'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($this->tickets)) : ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                <?php echo Text::_('COM_JED_DASHBOARD_NO_ENTRIES'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($this->tickets as $i => $item) : ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td><?php echo $this->escape($item->categorytype_string ?? '—'); ?></td>
                                <td>
                                    <a href="<?php echo Route::_('index.php?option=com_tickets&task=ticket.edit&id=' . (int) $item->id); ?>">
                                        <?php echo $this->escape($item->ticket_subject); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    if (!empty($item->created_on)) {
                                        try {
                                            echo (new DateTime($item->created_on))->format('d M Y H:i');
                                        } catch (Exception) {
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php echo $this->escape($item->ticket_status); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($this->ticketsPagination->total > $this->ticketsPagination->limit) : ?>
                        <tfoot>
                            <tr>
                                <td colspan="4"><?php echo $this->ticketsPagination->getListFooter(); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

</div>
