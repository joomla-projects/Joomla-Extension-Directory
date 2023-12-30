<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * @var array $displayData
 */
$i = 0;
?>
<div class="d-flex flex-row gap-1 align-items-center">
    <span aria-hidden="true" class="icon-tag"></span>
    <?php foreach ($displayData['categories'] as $cat) : ?>
        <?php $i++ ?>
        <a href="<?php echo Route::_(sprintf('index.php?option=com_jed&view=category&id=%d', $cat->id)) ?>">
            <?php echo htmlentities($cat->title) ?>
        </a>
        <?php if ($i != count($displayData['categories'])) : ?>
        <span class="text-muted">&bull;</span>
        <?php endif ?>
    <?php endforeach; ?>
</div>
