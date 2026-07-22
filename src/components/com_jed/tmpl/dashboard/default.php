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
$wa->useStyle('com_jed.jazstyle');
$wa->useScript('com_jed.favorite');

?>
<div id="jed-favorite-i18n" class="d-none"
     data-ajax-url="<?php echo Route::_('index.php?option=com_jed&format=raw'); ?>"
     data-csrf-token="<?php echo Session::getFormToken(); ?>"
     data-msg-no-entries="<?php echo Text::_('COM_JED_DASHBOARD_NO_ENTRIES'); ?>"></div>
<div class="com-jed-dashboard">

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
                                    if ((int) $item->published === -2) {
                                        echo Text::_('JTRASHED');
                                    } elseif ((int) $item->published === 1) {
                                        echo Text::_('JPUBLISHED');
                                    } else {
                                        echo Text::_('JUNPUBLISHED');
                                    }
                                    ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($isOwnReviewRow && (int) $item->published !== -2) : ?>
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
                                <td><?php echo $item->state ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?></td>
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
