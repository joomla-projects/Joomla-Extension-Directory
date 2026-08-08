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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Jed\Component\Jed\Site\View\Profile\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip');

// Import CSS
/**
 * @var Joomla\CMS\WebAsset\WebAssetManager $wa
*/
$wa = $this->getDocument()->getWebAssetManager();
$wa->useStyle('com_jed.jazstyle');

if (JedHelper::isLoggedIn()) {
    $wa->useScript('com_jed.favorite');
}
?>
<?php if (JedHelper::isLoggedIn()) : ?>
    <div id="jed-favorite-i18n" class="d-none"
         data-ajax-url="<?php echo Route::_('index.php?option=com_jed&format=raw'); ?>"
         data-csrf-token="<?php echo Session::getFormToken(); ?>"></div>
<?php endif; ?>

<div class="jed-cards-wrapper margin-bottom-half">
    <div class="jed-container">
        <div class="jed-profile-header d-flex align-items-center gap-3 mb-4">
            <img src="<?php echo $this->developer->logo; ?>" alt="<?php echo $this->escape($this->developer->name); ?>"
                 class="jed-profile-header__logo rounded" width="96" height="96">
            <div>
                <h2 class="heading heading--m m-0"><?php echo $this->escape($this->developer->name); ?></h2>
                <a href="<?php echo $this->escape($this->developer->website); ?>" rel="noopener noreferrer" target="_blank">
                    <?php echo $this->escape($this->developer->website); ?>
                </a>
            </div>
        </div>

        <h3 class="heading heading--s"><?php echo Text::_('COM_JED_PROFILE_EXTENSIONS_HEADING'); ?></h3>

        <ul class="jed-grid jed-grid--1-1-1">
            <?php foreach ($this->items as $item) : ?>
                <?php
                /*
                 * The same card as everywhere else (P1-14). This page used to have one of its
                 * own that showed the Joomla versions but no rating and no last-updated date -
                 * two cards for one thing, disagreeing about what a visitor needs to know.
                 */
                ?>
                <?php echo LayoutHelper::render('cards.extension', JedHelper::cardData($item)); ?>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php echo $this->pagination->getPaginationLinks(); ?>
