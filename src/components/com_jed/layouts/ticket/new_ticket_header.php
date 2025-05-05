<?php

/**
 * @package       JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
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
    <?php
    if ($displayData->ticket_title == "Submit Ticket") { ?>
        <p><b>This support system is primarily for developers that have listings on this site. If you need support with a specific extension please contact the developer or try the Joomla Forum. </b>If you have found an extension that no longer exists, please use the <a href="index.php?option=com_jed&view=velabandonedreportform">VEL abandoned report</a>.</p>
        <p>To submit a new extension, always <span style="color: #ff0000;"><strong>check</strong></span> your extensions with <a href="http://extensions.joomla.org/extension/jedchecker" target="_blank" rel="noopener noreferrer">JED Checker</a> and then use <a href="index.php?option=com_jed&view=extensionform">this link</a> . Extensions with errors are rejected.</p>
        <p>For extension support, including how to use it or reporting a bug, please contact the extension developer directly.</p>

        <?php
    } else { ?>
            <p>blah blah blah</p>
        <?php
    } ?>
</div>
