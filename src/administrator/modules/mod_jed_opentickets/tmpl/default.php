<?php

/**
 * @package JED
 *
 * @subpackage mod_jed_opentickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$moduleId = str_replace(' ', '', $module->title) . $module->id;

?>
<table class="table" id="<?php echo $moduleId; ?>">
    <caption class="visually-hidden"><?php echo $module->title; ?></caption>
    <thead>
        <tr>
            <th scope="col"><?php echo Text::_('MOD_JED_OPENTICKETS_HEADING_SUBJECT'); ?></th>
            <th scope="col" class="w-15"><?php echo Text::_('MOD_JED_OPENTICKETS_HEADING_CREATOR'); ?></th>
            <th scope="col" class="w-15"><?php echo Text::_('MOD_JED_OPENTICKETS_HEADING_CATEGORY'); ?></th>
            <th scope="col" class="w-15"><?php echo Text::_('MOD_JED_OPENTICKETS_HEADING_CREATED'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (\count($tickets)) : ?>
            <?php foreach ($tickets as $ticket) : ?>
                <tr>
                    <th scope="row">
                        <a href="<?php echo $ticket->link; ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo htmlspecialchars($ticket->ticket_subject, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($ticket->ticket_subject, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </th>
                    <td>
                        <?php echo htmlspecialchars($ticket->created_by ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($ticket->categorytype_string ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td>
                        <?php echo HTMLHelper::_('date', $ticket->created_on, Text::_('DATE_FORMAT_LC4')); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="4">
                    <?php echo Text::_('MOD_JED_OPENTICKETS_NO_MATCHING_RESULTS'); ?>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
