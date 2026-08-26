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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
*
 *
 * @var \Jed\Component\Jed\Site\View\Category\HtmlView $this
*/

//    $wa = Factory::getApplication()->getDocument()->getWebAssetManager()->enableAsset('choicesjs'); ;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
//HTMLHelper::_('webcomponent', 'system/webcomponents/joomla-field-fancy-select.min.js', ['version' => 'auto', 'relative' => true]);

$user       = $this->getCurrentUser();
$userId     = $user->id;
$listOrder  = $this->getState('list.ordering');
$listDirn   = $this->getState('list.direction');
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
$wa->useStyle('com_jed.style');

$childrenLimit = 9;

if (JedHelper::isLoggedIn()) {
    $wa->useScript('com_jed.favorite');
}

?>
<?php if (JedHelper::isLoggedIn()) : ?>
    <div id="jed-favorite-i18n" class="d-none"
         data-ajax-url="<?php echo Route::_('index.php?option=com_jed&format=raw'); ?>"
         data-csrf-token="<?php echo Session::getFormToken(); ?>"></div>
<?php endif; ?>
<?php // The subcategories of the category being viewed. $this->items holds extensions, not the
      // category tree - reading ->children off it was what produced two warnings per page load
      // and left this whole block empty. ?>
<?php if ($this->children) : ?>
    <div class="jed-home-categories">
        <div class="container">
            <div class="row gx-5">
                <?php foreach ($this->children as $c) : ?>
                    <div class="col-lg-4 mb-3 card jed-home-category">
                        <div class="card-header jed-home-item-view">
                            <span class="jed-home-category-icon fa fa-camera rounded-circle bg-warning p-2 text-white d-inline-block"></span>
                            <h4 class="jed-home-category-title d-inline-block">
                                <a href="<?php echo Route::_('index.php?option=com_jed&view=category&id=' . $c->id); ?>">
                                    <?php echo $this->escape($c->title); ?>
                                </a>
                            </h4>
                            <?php // getNumItems() without the recursive flag: numitems already covers the
                                  // subtree (JedcategoryHelper), so recursing again would double-count. ?>
                            <span class="badge rounded-pill float-end"><?php echo (int) $c->getNumItems(); ?></span>
                        </div>
                        <div class="card-body">
                            <?php
                            $visibleChildren = array_values(array_filter($c->getChildren(), fn ($sc) => $sc->getNumItems() > 0));
                            $shownChildren   = array_slice($visibleChildren, 0, $childrenLimit);
                            ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($shownChildren as $sc) : ?>
                                    <li class="list-group-item">
                                        <a href="<?php echo Route::_('index.php?option=com_jed&view=category&id=' . $sc->id); ?>">
                                            <?php echo $this->escape($sc->title); ?>
                                        </a>
                                        <span class="badge rounded-pill float-end badge-info-cat">  <?php echo (int) $sc->getNumItems(); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (count($visibleChildren) > $childrenLimit) : ?>
                                <a class="btn btn-sm btn-outline-secondary d-block mt-2 jed-view-more"
                                   href="<?php echo Route::_('index.php?option=com_jed&view=category&id=' . $c->id); ?>">
                                    <?php echo Text::_('COM_JED_VIEW_MORE'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="jed-cards-wrapper margin-bottom-half">
    <div class="jed-container">
        <?php // A category can legitimately hold no listings of its own while its children do,
              // so the heading is taken from the category rather than from the first extension. ?>
        <h2 class="heading heading--m"><?php echo $this->escape($this->categoryTitle); ?> Extensions</h2>
        <?php if ($this->categoryHierarchy !== '') : ?>
            <p class="font-size-s"><?php echo $this->categoryHierarchy; ?></p>
        <?php endif; ?>
        <?php if ($this->items) : ?>
            <ul class="jed-grid jed-grid--1-1-1">
                <?php foreach ($this->items as $item) : ?>
                    <?php // One card, one mapping (P1-14). See JedHelper::cardData(). ?>
                    <?php echo LayoutHelper::render('cards.extension', JedHelper::cardData($item)); ?>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p><?php echo Text::_('COM_JED_CATEGORY_NO_EXTENSIONS'); ?></p>
        <?php endif; ?>
    </div>
</div>


<?php echo $this->pagination->getPaginationLinks(); ?>
<?php
echo LayoutHelper::render(
    'category.children',
    [
        'children' => $this->children,
    ]
);
?>
