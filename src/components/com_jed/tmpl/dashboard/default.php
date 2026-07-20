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
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

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

?>
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
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($this->reviews)) : ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                <?php echo Text::_('COM_JED_DASHBOARD_NO_ENTRIES'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($this->reviews as $i => $item) : ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td><?php echo $this->escape($item->extension_title ?? '—'); ?></td>
                                <td>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&view=review&id=' . (int) $item->id); ?>">
                                        <?php echo $this->escape($item->title); ?>
                                    </a>
                                </td>
                                <td><?php echo (int) $item->overall_score; ?></td>
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
                                <td><?php echo $item->published ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php /* ---- Favourite Extensions (Placeholder) ---- */ ?>
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
                            <th><?php echo Text::_('JSTATUS'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" class="text-center text-muted">
                                <?php echo Text::_('COM_JED_DASHBOARD_FAVOURITES_COMING_SOON'); ?>
                            </td>
                        </tr>
                    </tbody>
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
                </table>
            </div>
        </div>
    </div>

</div>
