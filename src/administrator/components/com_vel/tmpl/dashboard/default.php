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

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Administrator\View\Dashboard\HtmlView $this */

$c = $this->counters;

$tiles = [
    ['reports_new',       'COM_VEL_DASH_REPORTS_NEW',       'index.php?option=com_vel&view=reports&filter[state]=new',          'danger'],
    ['reports_verifying', 'COM_VEL_DASH_REPORTS_VERIFYING', 'index.php?option=com_vel&view=reports&filter[state]=verifying',    'secondary'],
    ['drafts',            'COM_VEL_DASH_DRAFTS',            'index.php?option=com_vel&view=advisories&filter[state]=draft',     'secondary'],
    ['ready',             'COM_VEL_DASH_READY',             'index.php?option=com_vel&view=advisories&filter[state]=ready',     'info'],
    ['embargo_due',       'COM_VEL_DASH_EMBARGO_DUE',       'index.php?option=com_vel&view=advisories&filter[state]=working',   'warning'],
    ['embargo_passed',    'COM_VEL_DASH_EMBARGO_PASSED',    'index.php?option=com_vel&view=advisories&filter[state]=working',   'danger'],
    ['vendor_overdue',    'COM_VEL_DASH_VENDOR_OVERDUE',    'index.php?option=com_vel&view=advisories&filter[state]=working',   'warning'],
    ['feed_pending',      'COM_VEL_DASH_FEED_PENDING',      'index.php?option=com_vel&view=feeditems&filter[decision]=pending', 'info'],
    ['published_live',    'COM_VEL_DASH_PUBLISHED_LIVE',    'index.php?option=com_vel&view=advisories&filter[state]=published&filter[status]=live', 'danger'],
    ['published_total',   'COM_VEL_DASH_PUBLISHED_TOTAL',   'index.php?option=com_vel&view=advisories&filter[state]=published', 'success'],
];
?>
<div class="com-vel-dashboard">
    <div class="row g-3">
        <?php foreach ($tiles as [$key, $label, $link, $colour]) : ?>
            <div class="col-6 col-md-4 col-xl-2">
                <a class="card text-decoration-none h-100 border-<?php echo $colour; ?>" href="<?php echo Route::_($link); ?>">
                    <div class="card-body text-center">
                        <div class="display-6 text-<?php echo $colour === 'secondary' ? 'body' : $colour; ?>"><?php echo (int) ($c[$key] ?? 0); ?></div>
                        <div class="small text-muted"><?php echo Text::_($label); ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><?php echo Text::_('COM_VEL_DASH_OVERDUE_HEADING'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->overdue)) : ?>
                        <p class="text-muted mb-0"><?php echo Text::_('COM_VEL_DASH_NOTHING_OVERDUE'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm mb-0">
                            <caption class="visually-hidden"><?php echo Text::_('COM_VEL_DASH_OVERDUE_HEADING'); ?></caption>
                            <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_VEL_HEADING_ADVISORY'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_VEL_HEADING_STATE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_VEL_FIELD_DATE_PUBLIC_LABEL'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_VEL_FIELD_DATE_VENDOR_NOTIFIED_LABEL'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($this->overdue as $row) : ?>
                                <?php $state = AdvisoryState::tryFrom((string) $row->state) ?? AdvisoryState::DRAFT; ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo Route::_('index.php?option=com_vel&task=advisory.edit&id=' . (int) $row->id); ?>">
                                            <span class="font-monospace"><?php echo \sprintf('VEL-%04d', (int) $row->id); ?></span>
                                            <?php echo $this->escape($row->title); ?>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-<?php echo $state->badge(); ?>"><?php echo Text::_($state->label()); ?></span></td>
                                    <td><?php echo $row->date_public ? HTMLHelper::_('date', $row->date_public, Text::_('DATE_FORMAT_LC4')) : '&mdash;'; ?></td>
                                    <td><?php echo $row->date_vendor_notified ? HTMLHelper::_('date', $row->date_vendor_notified, Text::_('DATE_FORMAT_LC4')) : '&mdash;'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?php echo Text::_('COM_VEL_DASH_FEEDS_HEADING'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->feedRuns)) : ?>
                        <p class="text-muted mb-0"><?php echo Text::_('COM_VEL_DASH_NO_FEED_RUNS'); ?></p>
                    <?php else : ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($this->feedRuns as $run) : ?>
                                <li class="mb-2">
                                    <strong><?php echo Text::_('COM_VEL_FEED_SOURCE_' . strtoupper((string) $run->source)); ?></strong>
                                    <span class="badge bg-<?php echo match ((string) $run->status) {
                                        'ok' => 'success', 'partial' => 'warning text-dark', 'failed' => 'danger', default => 'secondary'
                                    }; ?>">
                                        <?php echo Text::_('COM_VEL_FEED_RUN_STATUS_' . strtoupper((string) $run->status)); ?>
                                    </span>
                                    <div class="small text-muted">
                                        <?php echo HTMLHelper::_('date', $run->started, Text::_('DATE_FORMAT_LC2')); ?>
                                        &middot; <?php echo Text::sprintf('COM_VEL_FEED_RUN_SUMMARY', (int) $run->items_seen, (int) $run->items_new, (int) $run->items_updated); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-secondary mt-2" href="<?php echo Route::_('index.php?option=com_vel&view=feedruns'); ?>"><?php echo Text::_('COM_VEL_DASH_FEED_RUNS_LINK'); ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
