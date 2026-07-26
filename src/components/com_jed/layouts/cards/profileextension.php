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

/**
 * @param int    $id          The extension id
 * @param string $image       The url of the logo image
 * @param string $title       The title of the extension
 * @param string $description A shortened version of the extension's description
 * @param string $includes    The types of Joomla extensions this extension ships with (e.g. "Component, Plugin")
 * @param string $versions    The Joomla versions this extension supports
 * @param string $link        The link to the extension's own page
 * @param bool   $isFavorited Whether the current user has bookmarked this extension
 */

/**
*
 *
 * @var array $displayData
*/
extract($displayData);

?>

<li class="jed-grid__item">
    <div class="card card--extension">
        <div class="card__image">
            <div class="image-placeholder">
                <?php if ($image) : ?>
                    <a href="<?php echo $link; ?>"><img src="<?php echo $image; ?>" alt="<?php echo $title; ?>"/></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card__header">
            <a href="<?php echo $link; ?>" class="card__extension-title"><?php echo $title; ?></a>
            <?php if (!empty($id) && JedHelper::isLoggedIn()) : ?>
                <?php echo LayoutHelper::render('elements.favoritebutton', [
                    'extensionId' => (int) $id,
                    'isFavorited' => !empty($isFavorited),
                ]); ?>
            <?php endif; ?>
        </div>
        <div class="card__description">
            <?php echo $description; ?>
        </div>
        <div class="card__footer">
            <?php if ($includes) : ?>
                <div class="card__extension-includes"><?php echo $includes; ?></div>
            <?php endif; ?>
            <?php if ($versions) : ?>
                <div class="card__extension-versions"><?php echo $versions; ?></div>
            <?php endif; ?>
        </div>
        <div class="card__actions">
            <a href="#" class="button button--grey"><?php echo Text::_('COM_JED_PROFILE_EXTENSION_SHARE'); ?></a>
        </div>
    </div>
</li>
