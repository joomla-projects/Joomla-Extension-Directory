<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
// phpcs:enable PSR1.Files.SideEffects

/**
 *
 *
 * @var array $displayData
 */
?>

<h1><?php
    echo $displayData->ticket_title; ?></h1>

<div id="ticket-guidelines">

    <p><?php echo Text::sprintf('COM_JED_TICKETFORM_INTRO', Route::_('index.php?option=com_jed&view=velabandonedreportform')); ?></p>
    <?php if (empty($displayData->isReport)) : ?>
        <p><?php echo Text::sprintf('COM_JED_TICKETFORM_NEW_EXTENSION_HINT', Route::_('index.php?option=com_jed&view=extensionform')); ?></p>
        <p><?php echo Text::_('COM_JED_TICKETFORM_SUPPORT_HINT'); ?></p>
    <?php endif; ?>
</div>
