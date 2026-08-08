<?php

/**
 * @package JED
 *
 * @subpackage mod_jed_extensions
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Browse\BrowseList;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/**
 * @var \Joomla\Registry\Registry $params
 * @var object[]                  $extensions
 */

if ($extensions === []) {
    return;
}

$list = BrowseList::fromKey((string) $params->get('browse_list', 'top-rated')) ?? BrowseList::TOP_RATED;

/*
 * The same card as the browse pages (P1-14). It was a bespoke compact list until then, which is
 * precisely the "five slightly different cards" the plan warns about - and it showed the name
 * and a line of text where the pages showed five decision signals, so the module was the one
 * place a visitor learned least.
 *
 * The bookmark icon is off for a different reason and stays off: a module appears on pages that
 * are otherwise identical for every visitor - the home page above all - and a per-visitor icon
 * would make them uncacheable for a decoration. That is the trade P1-13 undid on the lists.
 */
?>
<div class="jed-module jed-module--extensions jed-module--<?php echo $list->value; ?>">
    <ul class="jed-grid jed-grid--1 jed-module__list list-unstyled m-0 p-0">
        <?php foreach ($extensions as $extension) : ?>
            <?php
            $card = JedHelper::cardData($extension);

            if (!$params->get('show_description', 1)) {
                $card['description'] = '';
            }
            ?>
            <?php echo LayoutHelper::render('cards.extension', $card); ?>
        <?php endforeach; ?>
    </ul>

    <?php if ($params->get('show_all_link', 1) && (int) $params->get('all_link_itemid', 0) > 0) : ?>
        <p class="jed-module__more m-0">
            <a href="<?php echo Route::_('index.php?Itemid=' . (int) $params->get('all_link_itemid')); ?>">
                <?php echo Text::sprintf('MOD_JED_EXTENSIONS_SEE_ALL', Text::_($list->label())); ?>
            </a>
        </p>
    <?php endif; ?>
</div>
