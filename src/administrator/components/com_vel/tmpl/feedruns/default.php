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
                <?php if ($this->progress !== null) : ?>
                    <?php $report = $this->progress; ?>
                    <?php // Filled in and kept current by media/com_vel/js/feedrun.js, which steps the run. ?>
                    <section id="vel-feedrun" class="card mb-4" aria-labelledby="vel-feedrun-title">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <h2 id="vel-feedrun-title" class="h4 m-0"><?php echo $this->escape($report['label']); ?></h2>
                                <span id="vel-feedrun-status" class="badge bg-info"><?php echo $this->escape($report['statusLabel']); ?></span>
                                <span class="ms-auto small text-muted">
                                    <?php echo Text::_('COM_VEL_FEEDRUN_ELAPSED'); ?>: <span id="vel-feedrun-elapsed">&mdash;</span>
                                </span>
                            </div>

                            <p id="vel-feedrun-activity" class="mb-2" role="status" aria-live="polite"><?php echo $this->escape($report['activity']); ?></p>

                            <div id="vel-feedrun-progress" class="progress mb-3" role="progressbar" aria-labelledby="vel-feedrun-activity" aria-valuemin="0" aria-valuemax="100">
                                <div id="vel-feedrun-bar" class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
                            </div>

                            <dl class="d-flex flex-wrap gap-4 mb-2">
                                <div><dt class="small text-muted"><?php echo Text::_('COM_VEL_FEEDRUN_SEEN'); ?></dt><dd id="vel-feedrun-seen" class="fs-4 m-0"><?php echo (int) $report['seen']; ?></dd></div>
                                <div><dt class="small text-muted"><?php echo Text::_('COM_VEL_FEEDRUN_NEW'); ?></dt><dd id="vel-feedrun-new" class="fs-4 m-0"><?php echo (int) $report['new']; ?></dd></div>
                                <div><dt class="small text-muted"><?php echo Text::_('COM_VEL_FEEDRUN_UPDATED'); ?></dt><dd id="vel-feedrun-updated" class="fs-4 m-0"><?php echo (int) $report['updated']; ?></dd></div>
                            </dl>

                            <div id="vel-feedrun-message" class="alert d-none" role="alert"></div>

                            <details id="vel-feedrun-details" open>
                                <summary class="small"><?php echo Text::_('COM_VEL_FEEDRUN_ACTIVITY'); ?></summary>
                                <ol id="vel-feedrun-log" class="list-unstyled small font-monospace mb-0 mt-2 overflow-auto"></ol>
                            </details>

                            <div class="mt-3 d-flex gap-2">
                                <button type="button" id="vel-feedrun-stop" class="btn btn-outline-danger"><?php echo Text::_('COM_VEL_FEEDRUN_STOP'); ?></button>
                                <button type="button" id="vel-feedrun-continue" class="btn btn-primary d-none"><?php echo Text::_('COM_VEL_FEEDRUN_CONTINUE'); ?></button>
                                <a id="vel-feedrun-close" class="btn btn-secondary d-none" href="<?php echo Route::_('index.php?option=com_vel&view=feedruns'); ?>"><?php echo Text::_('COM_VEL_FEEDRUN_CLOSE'); ?></a>
                            </div>

                            <?php if ($this->progressProblem !== '') : ?>
                                <div class="alert alert-danger mt-3 mb-0"><?php echo $this->escape($this->progressProblem); ?></div>
                            <?php elseif (!$report['done']) : ?>
                                <?php // Removed by feedrun.js when it starts: if it stays, the script did not run. ?>
                                <div id="vel-feedrun-waiting" class="alert alert-warning mt-3 mb-0"><?php echo Text::_('COM_VEL_FEEDRUN_WAITING_FOR_SCRIPT'); ?></div>
                            <?php endif; ?>

                            <noscript><div class="alert alert-warning mt-3 mb-0"><?php echo Text::_('COM_VEL_FEEDRUN_NOSCRIPT'); ?></div></noscript>
                        </div>
                    </section>
                <?php endif; ?>

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
                                    <span class="badge bg-<?php echo match ((string) $run->status) { 'ok' => 'success', 'partial' => 'warning text-dark', 'failed' => 'danger', 'running' => 'info', default => 'secondary' }; ?>"><?php echo Text::_('COM_VEL_FEED_RUN_STATUS_' . strtoupper((string) $run->status)); ?></span>
                                    <?php if ((string) $run->status === 'running' && $this->can['feeds']) : ?>
                                        <a class="small ms-1" href="<?php echo Route::_('index.php?option=com_vel&view=feedruns&run=' . (int) $run->id); ?>"><?php echo Text::_('COM_VEL_FEEDRUN_CONTINUE'); ?></a>
                                    <?php endif; ?>
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
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
