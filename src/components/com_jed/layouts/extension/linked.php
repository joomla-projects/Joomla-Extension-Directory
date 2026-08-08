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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/**
 * The linked extensions of one listing (`P1-23`, `P1-07` item 12).
 *
 * Three relations, worded from the reader's side rather than the schema's:
 *
 *  - "Add-on for"                 - this listing's parent
 *  - "Also available as"          - the free/paid counterpart, from either direction
 *  - "Add-ons for this extension" - the confirmed listings that extend this one
 *
 * Rendered through `cards.extension` and `JedHelper::cardData()` like every other list of
 * listings on the site (P1-14). Hand-rolled markup here would have been shorter, and would have
 * been the fourth place a listing is drawn - which is how the trust signals came to be missing
 * from some of them in the first place.
 *
 * The whole block is skipped when there is nothing in it, so the ~93 % of the catalogue with no
 * relation at all gains no empty heading.
 *
 * @param object $linked      The model's linked object: parent, variants, children, childCount.
 * @param int    $extensionId The listing being rendered, for the "see all" link.
 *
 * @var array $displayData
 */
extract($displayData);

$sections = [];

if (!empty($linked->parent)) {
    $sections[] = ['heading' => Text::_('COM_JED_EXTENSION_LINKED_PARENT'), 'items' => [$linked->parent]];
}

if (!empty($linked->variants)) {
    $sections[] = ['heading' => Text::_('COM_JED_EXTENSION_LINKED_VARIANTS'), 'items' => $linked->variants];
}

if (!empty($linked->children)) {
    $sections[] = [
        'heading' => Text::_('COM_JED_EXTENSION_LINKED_CHILDREN'),
        'items'   => $linked->children,
        // Only when the list was actually cut short - "see all 4" above four cards is noise.
        'more'    => $linked->childCount > \count($linked->children) ? (int) $linked->childCount : 0,
    ];
}

if (!$sections) {
    return;
}
?>
<?php foreach ($sections as $section) : ?>
    <section class="jed-extension-linked mt-5">
        <h2 class="heading heading--m"><?php echo $section['heading']; ?></h2>
        <ul class="jed-grid jed-grid--1-1-1">
            <?php foreach ($section['items'] as $item) : ?>
                <?php // One card, one mapping (P1-14). See JedHelper::cardData(). ?>
                <?php echo LayoutHelper::render('cards.extension', JedHelper::cardData($item)); ?>
            <?php endforeach; ?>
        </ul>
        <?php if (!empty($section['more'])) : ?>
            <p>
                <a href="<?php echo Route::_('index.php?option=com_jed&view=extensions&parent_id=' . (int) $extensionId); ?>">
                    <?php echo Text::sprintf('COM_JED_EXTENSION_LINKED_CHILDREN_ALL', (int) $section['more']); ?>
                </a>
            </p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
