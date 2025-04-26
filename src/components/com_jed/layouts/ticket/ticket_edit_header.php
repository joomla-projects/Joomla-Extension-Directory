<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/** @var \Joomla\CMS\Form\Form $displayData */
$headerlabeloptions = ['hiddenLabel' => true];
$fieldhiddenoptions = ['hidden' => true];
?>
<div class="span12 form-horizontal">

    <div class="row ticket-header-row">
        <div class="col-md-12 ticket-header">
            <h1>Subject</h1>

            <h7><?php echo $displayData->ticket_subject; ?></h7>
        </div>
    </div>
    <div class="row ticket-header-row">
        <div class="col-md-12 ticket-header">

            <h1>Category</h1>
            <h7><?php echo $displayData->ticket_category_type; ?></h7>
        </div>
    </div>
    <div class="row ticket-header-row">
        <div class="col-md-12 ticket-header">

            <h1>Ticket Status</h1>
            <h7><?php echo $displayData->ticket_status; ?></h7>
        </div>
    </div>

</div>
