<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * @var array $displayData
 */
$i = 0;
if (count($displayData['children']) > 0) {
    ?>
<h4><?php echo Text::_('COM_JED_GENERAL_CHILD_CATEGORY'); ?></h4>
<div class="d-flex flex-row gap-1 align-items-center">
    <?php foreach ($displayData['children'] as $cat) : ?>
        <?php $i++ ?>
        <a href="<?php echo Route::_(sprintf('index.php?option=com_jed&view=category&id=%d', $cat->id)) ?>">
            <?php echo htmlentities($cat->title) ?>
        </a>
        <?php if ($i != count($displayData['children'])) : ?>
        <span class="text-muted">&bull;</span>
        <?php endif ?>
    <?php endforeach; ?>
</div>
    <?php
}
?>
