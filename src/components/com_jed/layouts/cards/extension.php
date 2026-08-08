<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/**
 * The one extension card, used by every view that lists extensions (`P1-14`).
 *
 * It carries the five signals the UX study (13.2) found people were discovering too late -
 * compatibility, when it was last updated, the rating *and how many reviews it rests on*, what it
 * costs, and what is in the package. 65% of respondents had evaluated an extension and only then
 * hit a limitation that would have stopped them: the version, a missing feature, or the price.
 * None of that is new data. All five columns were already on the row.
 *
 * Build the display data with {@see JedHelper::cardData()} rather than assembling it by hand.
 * That is the other half of "one card": the layout was already shared before this, and the views
 * still disagreed, because each one passed whatever its own model happened to have prepared.
 *
 * **Nothing here may be understood by colour alone** (13.8). Every badge carries its meaning as
 * text or, where the visible token is short, as an accessible name; the decorative icons are
 * `aria-hidden`. And no figure appears that the JED cannot substantiate (13.4.4) - there is no
 * install count and no download total, because the directory does not know either.
 *
 * @var array $displayData
 */

/**
 * @param int    $id            The extension id.
 * @param string $link          Routed link to the listing.
 * @param string $title         The listing name.
 * @param string $image         Logo URL, already resolved. May be empty.
 * @param string $developer     The developer's display name. May be empty.
 * @param string $description   Card text, plain and already encoded.
 * @param float  $score         0-5.
 * @param int    $reviewCount   How many reviews the score rests on.
 * @param string $compatibility Raw `joomla_versions` value.
 * @param string $includes      Raw `extension_types` value.
 * @param string $type          free | paid | freemium | cloud.
 * @param string $modified      SQL datetime, or empty.
 * @param string $category      The primary category title.
 * @param bool   $isFavorited   Whether to show the bookmark as set.
 */
extract($displayData);

$updated  = JedHelper::relativeDate($modified ?? null);
$added    = $updated === null ? JedHelper::relativeDate($created ?? null) : null;
$versions = JedHelper::versionBadges($compatibility ?? '');
$parts    = JedHelper::includeBadges($includes ?? '');
$count    = (int) ($reviewCount ?? 0);

?>
<li class="jed-grid__item">
    <div class="card card--extension">
        <div class="card__image">
            <div class="image-placeholder">
                <?php if (!empty($image)) : ?>
                    <a href="<?php echo $link; ?>" tabindex="-1" aria-hidden="true">
                        <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="" loading="lazy" decoding="async">
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card__header">
            <a href="<?php echo $link; ?>" class="card__extension-title">
                <?php echo htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php if (!empty($id) && JedHelper::isLoggedIn()) : ?>
                <?php echo LayoutHelper::render('elements.favoritebutton', [
                    'extensionId' => (int) $id,
                    'isFavorited' => !empty($isFavorited),
                ]); ?>
            <?php endif; ?>

            <?php if (!empty($developer)) : ?>
                <div class="card__extension-developer">
                    <?php echo Text::sprintf(
                        'COM_JED_CARD_BY_DEVELOPER',
                        htmlspecialchars((string) $developer, ENT_QUOTES, 'UTF-8')
                    ); ?>
                </div>
            <?php endif; ?>

            <div class="align-boxes card__signals">
                <?php
                /*
                 * Signal 1 - the rating, always with its sample size. A star row on its own does
                 * not distinguish 4.6 from two reviews and 4.6 from two hundred, and the stars
                 * are an image to a screen reader either way, so the whole thing gets one
                 * spoken label.
                 */ ?>
                <div class="stars-wrapper card__signal card__signal--rating">
                    <?php if ($count > 0) : ?>
                        <span aria-hidden="true"><?php echo JedscoreHelper::getStars((float) $score); ?></span>
                        <span class="card__review-count" aria-hidden="true">
                            <?php echo Text::plural('COM_JED_CARD_REVIEWS', $count); ?>
                        </span>
                        <span class="visually-hidden">
                            <?php echo Text::sprintf('COM_JED_CARD_RATING_SR', number_format((float) $score, 1), $count); ?>
                        </span>
                    <?php else : ?>
                        <span class="card__signal--empty"><?php echo Text::_('COM_JED_CARD_NO_REVIEWS'); ?></span>
                    <?php endif; ?>
                </div>

                <?php /* Signal 2 - compatibility, the study's single most important factor. */ ?>
                <div class="compatibility-wrapper card__signal card__signal--compatibility">
                    <?php if ($versions !== []) : ?>
                        <span class="visually-hidden"><?php echo Text::_('COM_JED_CARD_COMPATIBLE_WITH'); ?></span>
                        <?php foreach ($versions as $version) : ?>
                            <span class="joomla-version-badge" title="<?php echo $version['label']; ?>">
                                <span class="fab fa-joomla" aria-hidden="true"></span>
                                <?php echo $version['short']; ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <span class="card__signal--empty"><?php echo Text::_('COM_JED_CARD_NO_COMPATIBILITY'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card__description">
            <?php echo $description; ?>
        </div>

        <div class="card__footer">
            <?php /* Signal 3 - cost. Named in the study as a limitation people hit late. */ ?>
            <?php if (!empty($type)) : ?>
                <div class="card__extension-type card__signal card__signal--type badge-type-<?php echo htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('COM_JED_CARD_TYPE_' . strtoupper((string) $type)); ?>
                </div>
            <?php endif; ?>

            <?php /* Signal 4 - what the package actually contains. */ ?>
            <div class="card__extension-includes card__signal card__signal--includes">
                <?php if ($parts !== []) : ?>
                    <span class="visually-hidden"><?php echo Text::_('COM_JED_CARD_INCLUDES'); ?></span>
                    <?php foreach ($parts as $part) : ?>
                        <span class="badge badge-<?php echo $part['key']; ?>"><?php echo $part['label']; ?></span>
                    <?php endforeach; ?>
                <?php else : ?>
                    <span class="card__signal--empty"><?php echo Text::_('COM_JED_CARD_NO_INCLUDES'); ?></span>
                <?php endif; ?>
            </div>

            <?php
            /*
             * Signal 5 - how long ago somebody last touched it. A listing that has never been
             * edited since submission has no `modified`; saying when it was *added* answers the
             * same question, and saying nothing at all would leave the one signal about
             * maintenance blank on exactly the listings where it matters most.
             */ ?>
            <div class="card__extension-updated card__signal card__signal--updated">
                <?php if ($updated !== null) : ?>
                    <time datetime="<?php echo $updated['iso']; ?>" title="<?php echo $updated['absolute']; ?>">
                        <?php echo Text::sprintf('COM_JED_CARD_UPDATED', $updated['relative']); ?>
                    </time>
                <?php elseif ($added !== null) : ?>
                    <time datetime="<?php echo $added['iso']; ?>" title="<?php echo $added['absolute']; ?>">
                        <?php echo Text::sprintf('COM_JED_CARD_ADDED', $added['relative']); ?>
                    </time>
                <?php else : ?>
                    <span class="card__signal--empty"><?php echo Text::_('COM_JED_CARD_NO_DATE'); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($category)) : ?>
                <div class="card__extension-category">
                    <?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</li>
