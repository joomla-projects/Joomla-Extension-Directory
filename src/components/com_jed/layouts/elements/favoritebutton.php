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

use Joomla\CMS\Language\Text;

/**
 * @param int  $extensionId The extension to bookmark
 * @param bool $isFavorited Whether the current user has already bookmarked it
 */

/**
*
 *
 * @var array $displayData
*/
extract($displayData);

?>
<button type="button" class="jed-favorite-btn btn btn-link p-0" data-extension-id="<?php echo (int) $extensionId; ?>"
        aria-pressed="<?php echo $isFavorited ? 'true' : 'false'; ?>"
        aria-label="<?php echo Text::_('COM_JED_FAVORITE_TOGGLE_LABEL'); ?>">
    <i class="<?php echo $isFavorited ? 'fa-solid' : 'fa-regular'; ?> fa-bookmark" aria-hidden="true"></i>
</button>
