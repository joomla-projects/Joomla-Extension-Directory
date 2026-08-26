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
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Jed\Component\Jed\Site\View\Extension\HtmlView $this */

/*
 * P1-07 rebuilt this template. What it used to do, for anyone comparing:
 *
 *  - The logo and the intro were each rendered twice, from two half-finished layouts stacked on
 *    top of each other. The second logo carried a leading space inside src="", so the browser
 *    resolved it as a relative path and every listing with a logo showed one broken image.
 *  - The five outbound buttons read `homepage_link`, `demo_link`, `documentation_link`,
 *    `support_link` and `license_link` - none of which is a column or was ever assigned. With
 *    display_errors on they rendered the PHP warning, server path included, inside the href.
 *  - Every label was hardcoded English, against 8.9.
 *
 * The scores block, the review accordion and the report link were working and are kept as they
 * were.
 */

HTMLHelper::_('bootstrap.tooltip');

$user    = $this->getCurrentUser();
$canEdit = $user->authorise('core.edit', 'com_jed');
$item    = $this->item;

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useStyle('com_jed.style');

if (JedHelper::isLoggedIn()) {
    $wa->useScript('com_jed.favorite');
}

// Only the click loads the embed, so the script is what makes the video playable at all.
if (!empty($item->video_provider) && $item->video_provider !== 'file') {
    $wa->useScript('com_jed.video');
}

$extensionUrl = Route::_('index.php?option=com_jed&view=extension&catid=' . (int) $item->catid . '&id=' . (int) $item->id);

?>
<?php if (JedHelper::isLoggedIn()) : ?>
    <div id="jed-favorite-i18n" class="d-none"
         data-ajax-url="<?php echo Route::_('index.php?option=com_jed&format=raw'); ?>"
         data-csrf-token="<?php echo Session::getFormToken(); ?>"></div>
<?php endif; ?>
<div class="jed-cards-wrapper mb-4">
    <article class="container mb-5">
        <header class="row gap-2">
            <div class="col d-flex flex-column gap-2 mb-3">
                <h2 class="fs-1 m-0 d-flex flex-row gap-2 align-items-center">
                    <?php echo $this->escape($item->name); ?>
                    <?php if (JedHelper::isLoggedIn()) : ?>
                        <?php echo LayoutHelper::render('elements.favoritebutton', [
                            'extensionId' => (int) $item->id,
                            'isFavorited' => (bool) $item->is_favorited,
                        ]); ?>
                    <?php endif; ?>
                </h2>
                <div class="d-flex flex-row gap-3">
                    <div class="jed-extension-header__developer">
                        <?php echo Text::sprintf(
                            'COM_JED_EXTENSION_BY_DEVELOPER',
                            '<a href="' . Route::_('index.php?option=com_jed&view=profile&id=' . (int) $item->owner) . '">'
                            . $this->escape($item->created_by_name) . '</a>'
                        ); ?>
                    </div>
                    <div class="stars-wrapper">
                        <?php echo JedscoreHelper::getStars($item->score_overall); ?>
                        <span class="text-muted"><?php echo $item->review_string; ?></span>
                    </div>
                </div>
                <?php echo $item->category_hierarchy; ?>
            </div>
            <div class="col text-end">
                <?php if ($canEdit) : ?>
                    <a class="btn btn-sm btn-outline-primary" role="button"
                       href="<?php echo Route::_('index.php?option=com_jed&task=extensionform.edit&id=' . (int) $item->id); ?>">
                        <span class="icon-pencil" aria-hidden="true"></span>
                        <?php echo Text::_('JACTION_EDIT'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!empty($item->logo)) : ?>
            <img src="<?php echo htmlspecialchars($item->logo_large ?: $item->logo, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo $this->escape($item->name); ?>"
                 class="rounded img-fluid mx-auto d-block mb-4" style="max-height: 525px">
        <?php endif; ?>

        <div class="row gap-2">
            <div class="col-lg-8">
                <div class="jed-subitem-intro mb-3">
                    <?php echo JedHelper::renderMarkdown($item->intro); ?>
                </div>

                <div class="jed-subitem-description mb-4">
                    <?php echo JedHelper::renderMarkdown($item->description); ?>
                </div>

                <?php if (!empty($item->video_provider) && !empty($item->video_id)) : ?>
                    <?php echo LayoutHelper::render('elements.video', [
                        'provider' => $item->video_provider,
                        'id'       => $item->video_id,
                        'name'     => $item->name,
                    ]); ?>
                <?php endif; ?>

                <?php echo LayoutHelper::render('elements.screenshots', [
                    'screenshots' => $item->screenshots ?? [],
                    'name'        => $item->name,
                ]); ?>

                <?php if (!empty($item->download_url)) : ?>
                    <p class="jed-download">
                        <a class="btn btn-primary btn-lg jed-download__button"
                           href="<?php echo Route::_('index.php?option=com_jed&task=download.go&id=' . (int) $item->id); ?>"
                           rel="nofollow noopener">
                            <span class="fa fa-download" aria-hidden="true"></span>
                            <?php echo Text::_('COM_JED_EXTENSION_DOWNLOAD'); ?>
                        </a>
                        <?php
                        /*
                         * The note JED3 gave a whole interstitial page to. It says the same thing
                         * where the visitor already is, instead of costing them a click and losing
                         * a share of them on the way (P1-12).
                         */
                        ?>
                        <small class="jed-download__note d-block text-muted mt-1">
                            <?php echo Text::_('COM_JED_EXTENSION_DOWNLOAD_NOTE'); ?>
                            <?php if (!empty($item->requires_registration)) : ?>
                                <?php echo Text::_('COM_JED_EXTENSION_DOWNLOAD_NOTE_REGISTRATION'); ?>
                            <?php endif; ?>
                        </small>
                    </p>
                <?php endif; ?>

                <?php if (!empty($item->links)) : ?>
                    <p class="btn-group">
                        <?php foreach ($item->links as $link) : ?>
                            <a href="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>"
                               class="btn btn-secondary jed-link jed-link--<?php echo $link['key']; ?>"
                               target="_blank" rel="nofollow noopener external">
                                <?php echo Text::_($link['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </p><br />
                <?php endif; ?>

                <p class="btn-group">
                    <a href="<?php echo Route::_(
                        'index.php?option=com_tickets&view=ticketform&litem=' . TicketType::Extension->value
                        . '&lid=' . (int) $item->id . '&vr=' . (int) $item->id
                    ); ?>" class="btn btn-secondary">
                        <?php echo Text::_('COM_JED_EXTENSION_REPORT'); ?>
                    </a>
                </p>
            </div>

            <div class="col">
                <dl class="row jed-extension-facts">
                    <dt class="col-6"><?php echo Text::_('COM_JED_EXTENSION_FACT_VERSION'); ?></dt>
                    <dd class="col-6"><?php echo $this->escape($item->extension_version); ?></dd>
                    <?php if (!empty($item->modified)) : ?>
                        <dt class="col-6"><?php echo Text::_('COM_JED_EXTENSION_FACT_LAST_UPDATED'); ?></dt>
                        <dd class="col-6"><?php echo HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC3')); ?></dd>
                    <?php endif; ?>
                    <dt class="col-6"><?php echo Text::_('COM_JED_EXTENSION_FACT_DATE_ADDED'); ?></dt>
                    <dd class="col-6"><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC3')); ?></dd>
                    <dt class="col-6"><?php echo Text::_('COM_JED_EXTENSION_FACT_INCLUDES'); ?></dt>
                    <dd class="col-6"><?php echo JedtrophyHelper::getTrophyIncludesStringFull($item->extension_types); ?></dd>
                    <dt class="col-6"><?php echo Text::_('COM_JED_EXTENSION_FACT_COMPATIBILITY'); ?></dt>
                    <dd class="col-6"><?php echo JedtrophyHelper::getTrophyVersionsStringFull($item->joomla_versions); ?></dd>
                    <?php if (!empty($item->license)) : ?>
                        <dt class="col-6"><?php echo Text::_('COM_JED_EXTENSION_FACT_LICENSE'); ?></dt>
                        <dd class="col-6"><?php echo $this->escape($item->license); ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($item->tags)) : ?>
                        <dt class="col-6"><?php echo Text::_('JTAG'); ?></dt>
                        <dd class="col-6"><?php echo LayoutHelper::render('joomla.content.tags', $item->tags); ?></dd>
                    <?php endif; ?>
                </dl>
                <?php
                /*
                 * No "active installs" figure here, ever (13.4.4): Joomla does not report
                 * installations to the directory, so the JED cannot know that number, and 13.8
                 * forbids displaying a metric that cannot be substantiated. Views and download
                 * clicks are what P1-12 can honestly add.
                 */
                ?>

                <dl class="row">
                    <dt class="col-6"><h2 class="h5 m-0"><?php echo Text::_('COM_JED_EXTENSION_SCORE_OVERALL'); ?></h2></dt>
                    <dd class="col-6 text-end"><?php echo JedscoreHelper::getStars($item->score_overall); ?></dd>
                    <dt class="col-6"><?php echo Text::_('COM_JED_REVIEWS_FUNCTIONALITY_LABEL'); ?></dt>
                    <dd class="col-6 text-end"><?php echo JedscoreHelper::getStars($item->score_functionality); ?></dd>
                    <dt class="col-6"><?php echo Text::_('COM_JED_REVIEWS_EASE_OF_USE_LABEL'); ?></dt>
                    <dd class="col-6 text-end"><?php echo JedscoreHelper::getStars($item->score_ease_of_use); ?></dd>
                    <dt class="col-6"><?php echo Text::_('COM_JED_GENERAL_SUPPORT_LABEL'); ?></dt>
                    <dd class="col-6 text-end"><?php echo JedscoreHelper::getStars($item->score_support); ?></dd>
                    <dt class="col-6"><?php echo Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL'); ?></dt>
                    <dd class="col-6 text-end"><?php echo JedscoreHelper::getStars($item->score_documentation); ?></dd>
                    <dt class="col-6"><?php echo Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL'); ?></dt>
                    <dd class="col-6 text-end"><?php echo JedscoreHelper::getStars($item->score_value_for_money); ?></dd>
                </dl>

                <div class="d-flex align-items-center justify-content-center">
                    <a href="<?php echo Route::_(
                        'index.php?option=com_jed&view=reviewform&catid=' . (int) $item->catid . '&id=' . (int) $item->id
                    ); ?>" class="btn btn-outline-success">
                        <span class="fa fa-pencil" aria-hidden="true"></span>
                        <?php echo empty($item->user_review_id)
                            ? Text::_('COM_JED_EXTENSION_WRITE_REVIEW')
                            : Text::_('COM_JED_EXTENSION_EDIT_MY_REVIEW'); ?>
                    </a>
                </div>
            </div>
        </div>

        <section class="jed-extension-reviews mt-4">
            <h2 class="heading heading--m"><?php echo Text::_('COM_JED_EXTENSION_REVIEWS_HEADING'); ?></h2>
            <hr>
            <?php if (empty($item->reviews)) : ?>
                <p><?php echo Text::_('COM_JED_EXTENSION_NO_REVIEWS'); ?></p>
            <?php else :
                $slideid = 0;
                echo HTMLHelper::_('bootstrap.startAccordion', 'review_extension_group', ['active' => 'review_extension_group_slide0']);
                foreach ($item->reviews as $rev) :
                    echo HTMLHelper::_(
                        'bootstrap.addSlide',
                        'review_extension_group',
                        $rev->version . ' - ' .
                        $rev->title . ' - ' . JedscoreHelper::getStars($rev->overall_score) . ' ' . JedHelper::prettyShortDate($rev->created_on),
                        'review_extension_group_slide' . ($slideid++)
                    );
                    ?>
                    <p><?php echo htmlspecialchars($rev->body ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p>
                        <?php echo Text::_('COM_JED_REVIEWS_FUNCTIONALITY_LABEL'); ?>
                        <?php echo $rev->functionality === null
                            ? Text::_('COM_JED_REVIEWS_NOT_RATED')
                            : '(' . number_format((float) $rev->functionality, 1) . '/5)'; ?> -
                        <?php echo htmlspecialchars($rev->functionality_comment ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p>
                        <?php echo Text::_('COM_JED_REVIEWS_EASE_OF_USE_LABEL'); ?>
                        <?php echo $rev->ease_of_use === null
                            ? Text::_('COM_JED_REVIEWS_NOT_RATED')
                            : '(' . number_format((float) $rev->ease_of_use, 1) . '/5)'; ?> -
                        <?php echo htmlspecialchars($rev->ease_of_use_comment ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p>
                        <?php echo Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL'); ?>
                        <?php echo $rev->documentation === null
                            ? Text::_('COM_JED_REVIEWS_NOT_RATED')
                            : '(' . number_format((float) $rev->documentation, 1) . '/5)'; ?> -
                        <?php echo htmlspecialchars($rev->documentation_comment ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p>
                        <?php echo Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL'); ?>
                        <?php echo $rev->value_for_money === null
                            ? Text::_('COM_JED_REVIEWS_NOT_RATED')
                            : '(' . number_format((float) $rev->value_for_money, 1) . '/5)'; ?> -
                        <?php echo htmlspecialchars($rev->value_for_money_comment ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p>
                        <?php echo Text::_('COM_JED_REVIEWS_USED_FOR_LABEL'); ?> -
                        <?php echo htmlspecialchars($rev->used_for ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <?php if ((int) ($rev->developer_response_published ?? 0) === 1 && !empty($rev->developer_response)) : ?>
                        <div class="ms-4 border-start ps-3">
                            <h4><?php echo Text::sprintf('COM_JED_EXTENSION_DEVELOPER_RESPONSE_HEADING', JedHelper::prettyShortDate($rev->developer_responded_on)); ?></h4>
                            <p><?php echo nl2br(htmlspecialchars($rev->developer_response, ENT_QUOTES, 'UTF-8')); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php
                    echo HTMLHelper::_('bootstrap.endSlide');
                endforeach;
                echo HTMLHelper::_('bootstrap.endAccordion');
            endif; ?>
        </section>

        <?php if (!empty($item->linked)) : ?>
            <?php echo LayoutHelper::render('extension.linked', [
                'linked'      => $item->linked,
                'extensionId' => (int) $item->id,
            ]); ?>
        <?php endif; ?>

        <?php if (!empty($item->more_by_developer)) : ?>
            <section class="jed-extension-more mt-5">
                <h2 class="heading heading--m">
                    <?php echo Text::sprintf('COM_JED_EXTENSION_MORE_BY_DEVELOPER', $this->escape($item->created_by_name)); ?>
                </h2>
                <ul class="jed-grid jed-grid--1-1-1">
                    <?php foreach ($item->more_by_developer as $other) : ?>
                        <?php // One card, one mapping (P1-14). See JedHelper::cardData(). ?>
                        <?php echo LayoutHelper::render('cards.extension', JedHelper::cardData($other)); ?>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <a href="<?php echo Route::_('index.php?option=com_jed&view=profile&id=' . (int) $item->owner); ?>">
                        <?php echo Text::sprintf('COM_JED_EXTENSION_MORE_BY_DEVELOPER_ALL', $this->escape($item->created_by_name)); ?>
                    </a>
                </p>
            </section>
        <?php endif; ?>
    </article>
</div>
