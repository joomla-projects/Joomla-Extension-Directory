<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Administrator\View\Feedruns\HtmlView $this */
?>
<form action="<?php echo Route::_('index.php?option=com_vel&view=feedruns'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <p class="text-muted"><?php echo Text::_('COM_VEL_FEEDRUNS_INTRO'); ?></p>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info"><?php echo Text::_('COM_VEL_DASH_NO_FEED_RUNS'); ?></div>
                <?php else : ?>
                    <table class="table table-striped">
                        <caption class="visually-hidden"><?php echo Text::_('COM_VEL_FEEDRUNS_TITLE'); ?></caption>
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_FEED'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_STARTED'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_FINISHED'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_WINDOW'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_RESULT'); ?></th>
                            <th scope="col"><?php echo Text::_('JSTATUS'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $run) : ?>
                            <tr>
                                <td><?php echo Text::_('COM_VEL_FEED_SOURCE_' . strtoupper((string) $run->source)); ?></td>
                                <td><?php echo HTMLHelper::_('date', $run->started, Text::_('DATE_FORMAT_LC2')); ?></td>
                                <td><?php echo $run->finished ? HTMLHelper::_('date', $run->finished, Text::_('DATE_FORMAT_LC2')) : '&mdash;'; ?></td>
                                <td class="small"><?php echo $run->window_from ? $this->escape($run->window_from) . ' &rarr; ' . $this->escape($run->window_to) : '&mdash;'; ?></td>
                                <td class="small"><?php echo Text::sprintf('COM_VEL_FEED_RUN_SUMMARY', (int) $run->items_seen, (int) $run->items_new, (int) $run->items_updated); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo match ((string) $run->status) { 'ok' => 'success', 'partial' => 'warning text-dark', 'failed' => 'danger', default => 'secondary' }; ?>"><?php echo Text::_('COM_VEL_FEED_RUN_STATUS_' . strtoupper((string) $run->status)); ?></span>
                                    <?php if ($run->message) : ?><div class="small text-muted"><?php echo $this->escape($run->message); ?></div><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <input type="hidden" name="source" id="feed-source" value=""/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
