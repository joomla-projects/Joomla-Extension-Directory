<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
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

/**
*
 *
 * @var \Jed\Component\Jed\Site\View\Category\HtmlView $this
*/
$wa = $this->document->getWebAssetManager();
$wa->useStyle('com_jed.newjed')
    ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

$user       = Factory::getApplication()->getIdentity();
$userId     = $user->id;
$listOrder  = $this->getState('list.ordering');
$listDirn   = $this->getState('list.direction');
$canCreate  = $user->authorise('core.create', 'com_jed');
$canEdit    = $user->authorise('core.edit', 'com_jed');
$canCheckin = $user->authorise('core.manage', 'com_jed');
$canChange  = $user->authorise('core.edit.state', 'com_jed');
$canDelete  = $user->authorise('core.delete', 'com_jed');

// Import CSS

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useStyle('com_jed.jazstyle');

?>

<div class="jed-cards-wrapper margin-bottom-half">
    <div class="jed-container">
        <h2 class="heading heading--m"><?php echo $this->get("items")[0]->category_title; ?> Extensions</h2>
        <p class="font-size-s"><?php echo $this->get("items")[0]->category_hierarchy; ?></p>
        <ul class="jed-grid jed-grid--1-1-1">
            <?php foreach ($this->get("items") as $item) : ?>
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


<?php echo $this->get("pagination")->getPaginationLinks(); ?>
<?php
echo LayoutHelper::render(
    'category.children',
    [
        'children' => $this->get("children"),
    ]
);
?>
