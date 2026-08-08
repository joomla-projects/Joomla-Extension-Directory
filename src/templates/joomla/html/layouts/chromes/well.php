<?php

/**
 * Joomla.org site template
 *
 * @copyright   Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Replaces the modChrome_well() function that used to live in html/modules.php. Since Joomla 4 module chrome is
 * resolved as a 'chromes.<style>' layout, so the old function based chrome is never called and has been removed.
 */

defined('_JEXEC') or die;

$module = $displayData['module'];
$params = $displayData['params'];

if ((string) $module->content === '') {
    return;
}

$moduleClassSfx = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');
?>
<div class="well <?php echo $moduleClassSfx; ?>">
    <?php if ($module->showtitle) : ?>
        <div class="page-header"><strong><?php echo $module->title; ?></strong></div>
    <?php endif; ?>
    <?php echo $module->content; ?>
</div>
