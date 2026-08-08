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

use Joomla\CMS\Language\Text;

/**
 * A listing's screenshots (`P1-07`, work item 5).
 *
 * `#__jed_extensions_images`, the admin views and the developer's upload subform have existed
 * since 4.0; only this was missing, so every screenshot anybody ever uploaded was invisible -
 * 33,873 of them across 10,657 listings in the imported stock.
 *
 * Thumbnails link to the full-size variant rather than opening a lightbox: a plain link works
 * without JavaScript, is what a screen reader and a keyboard already understand, and needs no
 * third-party gallery script on the site's most-visited page. `loading="lazy"` keeps a listing
 * with eight screenshots from costing eight requests before anything is scrolled to.
 *
 * @var array $displayData
 */

/**
 * @param array  $screenshots Rows with `thumbnail` and `full` URLs, in the developer's order.
 * @param string $name        The listing name, for the alt text.
 */
extract($displayData);

$screenshots = array_values(array_filter((array) $screenshots, static fn ($s) => !empty($s->thumbnail)));

if ($screenshots === []) {
    return;
}

?>
<section class="jed-screenshots mb-4">
    <h2 class="heading heading--m"><?php echo Text::_('COM_JED_EXTENSION_SCREENSHOTS_HEADING'); ?></h2>
    <ul class="jed-screenshots__list list-unstyled d-flex flex-wrap gap-3 m-0 p-0">
        <?php foreach ($screenshots as $index => $shot) : ?>
            <li class="jed-screenshots__item">
                <a href="<?php echo htmlspecialchars($shot->full ?: $shot->thumbnail, ENT_QUOTES, 'UTF-8'); ?>"
                   target="_blank" rel="noopener">
                    <img src="<?php echo htmlspecialchars($shot->thumbnail, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars(
                             Text::sprintf('COM_JED_EXTENSION_SCREENSHOT_ALT', $name, $index + 1),
                             ENT_QUOTES,
                             'UTF-8'
                         ); ?>"
                         loading="lazy" decoding="async"
                         class="rounded border" style="max-height: 160px; width: auto;">
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
