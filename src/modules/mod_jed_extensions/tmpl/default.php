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
use Joomla\CMS\Language\Text;
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
 * No bookmark icons here, deliberately. A module appears on pages that are otherwise identical
 * for every visitor - the home page above all - and a per-visitor icon would make them
 * uncacheable for the sake of a decoration, which is the same trade P1-13 just undid on the
 * browse lists themselves.
 */
?>
<div class="jed-module jed-module--extensions jed-module--<?php echo $list->value; ?>">
    <ul class="jed-module__list list-unstyled m-0 p-0">
        <?php foreach ($extensions as $extension) : ?>
            <li class="jed-module__item d-flex gap-2 mb-3">
                <?php if ($extension->logo_url) : ?>
                    <img src="<?php echo htmlspecialchars($extension->logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="" loading="lazy" decoding="async"
                         class="jed-module__logo rounded" width="48" height="48">
                <?php endif; ?>
                <div class="jed-module__body">
                    <a class="jed-module__title d-block"
                       href="<?php echo Route::_(
                           'index.php?option=com_jed&view=extension&catid=' . (int) $extension->catid
                           . '&id=' . (int) $extension->id
                       ); ?>">
                        <?php echo htmlspecialchars($extension->name, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <?php if ($params->get('show_description', 1)) : ?>
                        <small class="jed-module__text text-muted d-block">
                            <?php echo $extension->card_text; ?>
                        </small>
                    <?php endif; ?>
                </div>
            </li>
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
