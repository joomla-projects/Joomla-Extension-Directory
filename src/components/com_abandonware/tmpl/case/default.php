<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
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
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Abandonware\Site\View\Case\HtmlView $this */

$item = $this->item;

// Whether the listing is still something a visitor can be sent to. A blocked, unapproved,
// unpublished or deleted listing is not, and linking to it would produce the JED's own "not
// available" page from a list that just told somebody this thing exists.
$listingVisible = (int) ($item->extension_id ?? 0) > 0
    && (int) ($item->listing_approved ?? 0) === 1
    && (int) ($item->listing_state ?? 0) === 1
    && (int) ($item->listing_blocked ?? 0) === 0
    && (int) ($item->listing_deleted ?? 0) === 0;
?>

<div class="com-abandonware-case">
    <div class="page-header">
        <h1 itemprop="headline"><?php echo $this->escape($item->extension_name) ?: Text::_('COM_ABANDONWARE_UNNAMED_EXTENSION'); ?></h1>
    </div>

    <dl class="row">
        <?php if ($item->developer_name) : ?>
            <dt class="col-sm-3"><?php echo Text::_('COM_ABANDONWARE_HEADING_DEVELOPER'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($item->developer_name); ?></dd>
        <?php endif; ?>

        <?php if ($item->extension_version) : ?>
            <dt class="col-sm-3"><?php echo Text::_('COM_ABANDONWARE_FIELD_EXTENSION_VERSION_LABEL'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($item->extension_version); ?></dd>
        <?php endif; ?>

        <dt class="col-sm-3"><?php echo Text::_('COM_ABANDONWARE_HEADING_MARKED'); ?></dt>
        <dd class="col-sm-9"><?php echo HTMLHelper::_('date', $item->abandoned_time, Text::_('DATE_FORMAT_LC3')); ?></dd>

        <?php if ($listingVisible) : ?>
            <dt class="col-sm-3"><?php echo Text::_('COM_ABANDONWARE_HEADING_LISTING'); ?></dt>
            <dd class="col-sm-9">
                <a href="<?php echo Route::_('index.php?option=com_jed&view=extension&id=' . (int) $item->extension_id); ?>">
                    <?php echo Text::_('COM_ABANDONWARE_VIEW_LISTING'); ?>
                </a>
            </dd>
        <?php endif; ?>
    </dl>

    <?php
    /*
     * No reason text, and no reporter. The reason a case exists is written by the JED team and by
     * the automated signals, and both are internal - `internal_notes` and `signals` are not in the
     * model's column set at all. What is public is the conclusion and the date, which is what a
     * visitor needs and the least that can be said about somebody's product.
     *
     * The legacy `abandoneditem` view printed `reporter_fullname` here. That is the one thing from
     * the old page that is deliberately not carried over.
     */
    ?>
    <div class="alert alert-warning">
        <p class="mb-0"><?php echo Text::_('COM_ABANDONWARE_CASE_PUBLIC_NOTE'); ?></p>
    </div>

    <p>
        <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_abandonware&view=abandoned'); ?>">
            <?php echo Text::_('COM_ABANDONWARE_BUTTON_BACK_TO_LIST'); ?>
        </a>
    </p>
</div>
