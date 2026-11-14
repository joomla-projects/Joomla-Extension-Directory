<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
// phpcs:enable PSR1.Files.SideEffects

/**
*
 *
 * @var array $displayData
*/
?>

<h1><?php echo Text::sprintf('COM_JED_REVIEW_GUIDELINES_TITLE', htmlspecialchars((string) $displayData->name, ENT_QUOTES, 'UTF-8')); ?></h1>

<div id="guidelines">
    <h2><?php echo Text::_('COM_JED_REVIEW_GUIDELINES_HEADING'); ?></h2>

    <p><?php echo Text::_('COM_JED_REVIEW_GUIDELINES_INTRO'); ?></p>
    <div class="row">
        <div class="col-md-6 alert alert-success">
            <h3><span class="fa fa-check"></span> <?php echo Text::_('COM_JED_REVIEW_GUIDELINES_ALLOWED'); ?></h3>
            <ul class='review-allowed-list'>
                <?php foreach (range(1, 8) as $i) : ?>
                    <li><?php echo Text::_('COM_JED_REVIEW_GUIDELINES_ALLOWED_' . $i); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="col-md-6 alert alert-error">
            <h3><span class="fa fa-times"></span> <?php echo Text::_('COM_JED_REVIEW_GUIDELINES_NOT_ALLOWED'); ?></h3>
            <ul class='review-disallowed-list'>
                <?php foreach (range(1, 10) as $i) : ?>
                    <li><?php echo Text::_('COM_JED_REVIEW_GUIDELINES_NOT_ALLOWED_' . $i); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    </div>
<div><button class="btn btn-secondary" id="reviewBtn" ><?php echo Text::_('JCONTINUE'); ?></button></div>
