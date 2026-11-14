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
<hr>

<h2><?php echo Text::_('COM_JED_REVIEW_REPORT_HEADING'); ?></h2>
<p><?php echo Text::_('COM_JED_REVIEW_REPORT_USE_LISTING_BUTTON'); ?></p>
<h2><?php echo Text::_('COM_JED_REVIEW_REPORT_PROBLEMS_HEADING'); ?></h2>
<p><?php echo Text::sprintf('COM_JED_REVIEW_REPORT_CONTACT_DEVELOPER', htmlspecialchars((string) $displayData->developer_email, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string) $displayData->developer_company, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string) $displayData->support_link, ENT_QUOTES, 'UTF-8')); ?></p>
<p><br></p>
<div class="alert alert-info">
    <?php echo Text::_('COM_JED_REVIEW_REPORT_DISCLAIMER'); ?></div>
