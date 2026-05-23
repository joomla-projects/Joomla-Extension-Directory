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
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Layout\LayoutHelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');


$user       = $this->getCurrentUser();
$userId     = $user->id;
$listOrder  = $this->state->get('list.ordering', '');
$listDirn   = $this->state->get('list.direction');
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

<div class="jed-cards-wrapper margin-bottom-half">
    <div class="jed-container">
        <h2 class="heading heading--m"><?php echo $this->items[0]->category_title; ?> Extensions</h2>
        <p class="font-size-s"><?php echo $this->items[0]->category_hierarchy; ?></p>
        <ul class="jed-grid jed-grid--1-1-1">
            <?php foreach ($this->items as $item) : ?>
                <?php echo LayoutHelper::render(
                    'cards.extension',
                    [
                                                    'image'         => $item->logo,
                                                    'title'         => $item->title,
                                                    'developer'     => $item->developer,
                                                    'score_string'  => $item->score_string,
                                                    'score'         => $item->score,
                                                    'reviews'       => $item->review_string,
                                                    'compatibility' => $item->version,
                                                    'description'   => $item->description,
                                                    'type'          => $item->type,
                                                    'category'      => $item->category_title,
                                                    'link'          => Route::_(sprintf('index.php?option=com_jed&view=extension&catid=%s&id=%s', $item->primary_category_id, $item->id)),
                                                    ]
                ); ?>
            <?php endforeach; ?>
        </ul>
    </div>
</div>


<?php echo $this->pagination->getPaginationLinks(); ?>