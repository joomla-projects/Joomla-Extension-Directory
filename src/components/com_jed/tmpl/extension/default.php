<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Jed\Component\Jed\Site\View\Extension\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Site\View\Extension\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

$user       = $this->getCurrentUser();
$canCreate  = $user->authorise('core.create', 'com_jed');
$canEdit    = $user->authorise('core.edit', 'com_jed');
$canCheckin = $user->authorise('core.manage', 'com_jed');
$canChange  = $user->authorise('core.edit.state', 'com_jed');
$canDelete  = $user->authorise('core.delete', 'com_jed');

// Import CSS
/**
 * @var Joomla\CMS\WebAsset\WebAssetManager $wa
*/
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useStyle('com_jed.jazstyle');

?>
<div class="jed-cards-wrapper">
    <article class="container mb-5">
        <header class="row gap-2">
            <div class="col d-flex flex-column gap-2 mb-3">
                <h2 class="fs-1 m-0 d-flex flex-row gap-2 align-items-center">
                    <?php
                    echo $this->escape($this->item->name) ?>
                </h2>
                <div class="d-flex flex-row gap-3">
                    <div class="jed-extension-header__developer">By <a
                                href="#"><?php
                                echo $this->item->created_by_name ?></a></div>
                    <div class="stars-wrapper">
                        <?php echo JedscoreHelper::getStars($this->item->score_overall); ?>
                        <span class="text-muted">
                    <?php
                    echo $this->item->review_string ?>
                </span>
                    </div>
                </div>
                <?php
                echo $this->item->category_hierarchy ?>
            </div>
            <div class="col text-end">
                <?php
                $canCheckin = $this->getCurrentUser()->authorise('core.manage', 'com_jed.' . $this->item->id); ?>
                <?php
                if ($canEdit) : ?>
                    <a class="btn btn-sm btn-outline-primary" role="button"
                       href="<?php
                        echo Route::_('index.php?option=com_jed&task=extension.edit&id=' . $this->item->id) ?>">
                        <span class="icon-pencil" aria-hidden="true"></span>
                        <?php
                        echo Text::_("JACTION_EDIT") ?>
                    </a>
                    <?php
                endif; ?>
            </div>
        </header>

        <img src="<?php
        echo $this->item->logo ?>" alt="<?php
        echo $this->escape($this->item->name) ?>"
             class="rounded img-fluid mx-auto d-block" style="max-height: 525px">

        <div class="row gap-2">
            <div class="col-8 ">
                <?php
                echo $this->item->intro; ?>
            </div>
            <dl class="col">
                <dl class="row">
                    <dt class="col-6">Version</dt>
                    <dd class="col-6"><?php echo $this->item->extension_version; ?></dd>
                    <dt class="col-6">Last updated</dt>
                    <dd class="col-6"><?php echo HTMLHelper::_('date', $this->item->modified, 'M j Y'); ?></dd>
                    <dt class="col-6">Date added</dt>
                    <dd class="col-6"><?php echo HTMLHelper::_('date', $this->item->created, 'M j Y') ?></dd>
                    <dt class="col-6">Includes</dt>
                    <dd class="col-6"><?php echo JedtrophyHelper::getTrophyIncludesStringFull($this->item->extension_types) ?></dd>
                    <dt class="col-6">Compatibility</dt>
                    <dd class="col-6"><?php echo JedtrophyHelper::getTrophyVersionsStringFull($this->item->joomla_versions) ?></dd>
                </dl>
                <div class="d-flex align-items-center justify-content-center">
                    <a href="<?php echo Route::_('index.php?option=com_jed&task=review.add&extension_id=' . $this->item->id); ?>"
                       class="btn btn-outline-success">
                        <span class="fa fa-pencil" aria-hidden="true"></span>
                        Write a review
                    </a>
                </div>
            </div>
        </div>

        <div class="jed-wrapper jed-extension margin-bottom">
            <div class="jed-extension__image">
                <?php if ($this->item->logo) : ?>
                    <img src=" <?php echo $this->item->logo_large ?>" alt=" <?php echo $this->escape($this->item->name) ?>"
                         class="rounded img-fluid mx-auto d-block" style="max-height: 525px">
                <?php endif; ?>
            </div>
            <div class="jed-grid jed-grid--2-1 margin-bottom">
                <div class="jed-grid__item">
                    <div class="jed-subitem-intro mb-2">
                         <?php echo $this->item->intro; ?>
                    </div>

                    <div class="jed-subitem-description mb-2 collapse">
                         <?php echo $this->item->description ?>
                    </div>

                    <p class="button-group">
                        <a href=" <?php echo $this->item->homepage_link ?>" class="button button--grey">Website</a>
                        <a href=" <?php echo $this->item->demo_link ?>" class="button button--grey">Demo</a>
                        <a href=" <?php echo $this->item->documentation_link ?>" class="button button--grey">Documentation</a>
                        <a href=" <?php echo $this->item->support_link ?>" class="button button--grey">Support</a>
                        <a href=" <?php echo $this->item->license_link ?>" class="button button--grey">License</a>
                    </p>
                </div>
                <div class="jed-grid__item">
                    <p>
                        <span class="button-group display-block align-right">

                            <?php

                            $url =  Route::_('index.php?option=com_tickets&view=ticketform&litem=2&lid=' . $this->item->id . '&vr=' . $this->item->id)
                            ?>
                            <a href="<?php echo $url; ?>" class="button button--grey">Report</a>
                            <a href="#" class="button button--grey">Share</a>
                        </span>

                    </p>
                </div>
            </div>

            <div class="jed-grid jed-grid--1-2">
                <div class="jed-grid__item">
                    <h2 class="heading heading--m">Reviews</h2>
                    <hr>
                    <?php
                    $slideid = 0;
                    foreach ($this->item->reviews as $rev) {continue;
                        echo HTMLHelper::_(
                            'bootstrap.addSlide',
                            'review_extension_group',
                            $rev->version . ' - ' .
                            $rev->title . ' - ' . JedscoreHelper::getStars($rev->overall_score) . ' ' . JedHelper::prettyShortDate($rev->created_on),
                            'review_extension_group' . '_slide' . ($slideid++)
                        );
                        echo "<p>" . $rev->body . "</p>";
                        echo "<p>Functionality (" . $rev->functionality . ") - " . $rev->functionality_comment . "</p>";
                        echo "<p>Ease of use (" . $rev->ease_of_use . ") - " . $rev->ease_of_use_comment . "</p>";
                        echo "<p>Documentation (" . $rev->documentation . ") - " . $rev->documentation_comment . "</p>";
                        echo "<p>Value For Money (" . $rev->value_for_money . ") - " . $rev->value_for_money_comment . "</p>";
                        echo "<p>Used for - " . $rev->used_for . "</p>";
                        echo HTMLHelper::_('bootstrap.endSlide');
                    ?>
                </div>

            </div>
        </div>
            <?php

            echo HTMLHelper::_('uitab.endTab');
        }?>
    </article>
</div>


