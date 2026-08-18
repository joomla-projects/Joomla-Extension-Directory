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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Site\View\Categories\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useStyle('com_jed.t09_jed'); /*
    ->useScript('form.validate');*/
HTMLHelper::_('bootstrap.tooltip');
?>
<div class="jed-home-categories">
    <?php
    if (count($this->items) == 0) {
        echo "<h1>" . Text::_('COM_JED_CATEGORIES_NONE_LABEL') . "</h1>";
    } else {
        ?>
    <div class="container">
        <div class="row gx-5 gy-3">
            <?php foreach ($this->items as $c) : ?>
                <div class="col-lg-4 jed-home-category">
                    <div class="card">
                        <div class="card-header jed-home-item-view d-flex align-items-center gap-2">
                            <span class="jed-home-category-icon fa fa-camera rounded-circle bg-warning p-2 text-white"></span>
                            <h4 class="jed-home-category-title mb-0 category_list_jed">
                                <a href="<?php echo Route::_('index.php?option=com_jed&view=category&id=' . $c->id); ?>">
                                    <?php echo $c->title; ?>
                                </a>
                            </h4>
                            <span class="badge badge-info rounded-pill ms-auto"><?php echo $c->numitems; ?></span>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($c->children as $sc) {
                                    if ($sc->numitems > 0) { ?>
                                        <li class="list-group-item category_list_jed">
                                            <a href="<?php echo Route::_('index.php?option=com_jed&view=category&id=' . $sc->id); ?>">
                                                <?php echo $sc->title; ?>
                                            </a>
                                            <span class="badge rounded-pill float-end badge-info">  <?php echo $sc->numitems; ?></span>
                                        </li>
                                    <?php }
                                } ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php } ?>
</div>
