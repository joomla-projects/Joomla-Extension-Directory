<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\ExtensionType;
use Jed\Component\Vel\Administrator\Enum\Severity;
use Jed\Component\Vel\Site\Helper\VelHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Site\View\Advisories\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_vel.site', 'com_vel/css/vel.css');

$f        = $this->filters;
$listUrl  = Route::_('index.php?option=com_vel&view=advisories');
$showCvss = (int) $this->params->get('show_cvss_in_list', 1) === 1;

// Everything except the status, for the tab links - a tab switch keeps the other filters.
$carry = array_filter([
    'search'   => $f['search'],
    'severity' => implode(',', $f['severity']),
    'cvss_min' => $f['cvss_min'],
    'cvss_max' => $f['cvss_max'],
    'vendor'   => $f['vendor'],
    'type'     => $f['type'],
    'sort'     => $f['sort'] !== 'a.date_published' ? match ($f['sort']) { 'a.date_updated' => 'updated', 'a.title' => 'title', 'a.cvss_score' => 'score', default => '' } : '',
    'dir'      => $f['dir'] !== 'DESC' ? 'asc' : '',
], static fn ($v): bool => $v !== null && $v !== '');

$tabUrl = static fn (string $status): string => $listUrl . '?' . http_build_query(array_merge(['status' => $status], $carry));

$sortKey = match ($f['sort']) { 'a.date_updated' => 'updated', 'a.title' => 'title', 'a.cvss_score' => 'score', default => 'published' };
$sortUrl = static function (string $key) use ($listUrl, $f, $carry, $sortKey): string {
    $dir = $sortKey === $key && $f['dir'] === 'DESC' ? 'asc' : 'desc';

    return $listUrl . '?' . http_build_query(array_merge($carry, ['status' => $f['status'], 'sort' => $key, 'dir' => $dir]));
};
?>
<div class="com-vel-list">
    <div class="page-header">
        <h1><?php echo $this->escape($this->params->get('page_heading', Text::_('COM_VEL_LIST_HEADING'))); ?></h1>
    </div>

    <div class="alert alert-info com-vel-intro">
        <p class="mb-1"><?php echo Text::_('COM_VEL_LIST_INTRO'); ?></p>
        <p class="mb-0 small">
            <a href="<?php echo Route::_('index.php?option=com_vel&view=report'); ?>"><?php echo Text::_('COM_VEL_LINK_REPORT'); ?></a> &middot;
            <a href="<?php echo Route::_('index.php?option=com_vel&view=fixnotice'); ?>"><?php echo Text::_('COM_VEL_LINK_FIXNOTICE'); ?></a> &middot;
            <a href="<?php echo Route::_('index.php?option=com_vel&view=advisories&format=feed&type=rss'); ?>"><?php echo Text::_('COM_VEL_LINK_RSS'); ?></a> &middot;
            <a href="<?php echo Route::_('index.php?option=com_vel&view=api&resource=index'); ?>"><?php echo Text::_('COM_VEL_LINK_API'); ?></a>
        </p>
    </div>

    <ul class="nav nav-tabs com-vel-tabs" role="tablist">
        <?php foreach (['live', 'patched', 'abandoned', 'all'] as $tab) : ?>
            <?php
            $label = $tab === 'all' ? Text::_('JALL') : Text::_(AdvisoryStatus::from($tab)->label());
            $count = (int) ($this->counts[$tab] ?? 0);
            ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $f['status'] === $tab ? 'active' : ''; ?>" href="<?php echo $tabUrl($tab); ?>" <?php echo $f['status'] === $tab ? 'aria-current="page"' : ''; ?>>
                    <?php echo $label; ?> <span class="badge bg-secondary"><?php echo $count; ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <form action="<?php echo $listUrl; ?>" method="get" class="com-vel-filters card card-body mb-3" role="search">
        <input type="hidden" name="status" value="<?php echo $this->escape($f['status']); ?>">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="vel-search"><?php echo Text::_('COM_VEL_FILTER_SEARCH_LABEL'); ?></label>
                <input type="search" class="form-control" id="vel-search" name="search" value="<?php echo $this->escape($f['search']); ?>" placeholder="<?php echo Text::_('COM_VEL_FILTER_SEARCH_HINT'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="vel-severity"><?php echo Text::_('COM_VEL_HEADING_SEVERITY'); ?></label>
                <select class="form-select" id="vel-severity" name="severity">
                    <option value=""><?php echo Text::_('COM_VEL_FILTER_SEVERITY_ANY'); ?></option>
                    <?php foreach ([['critical'], ['high', 'critical'], ['medium', 'high', 'critical'], ['low'], ['medium'], ['high']] as $set) : ?>
                        <?php $value = implode(',', $set); ?>
                        <option value="<?php echo $value; ?>" <?php echo implode(',', $f['severity']) === $value ? 'selected' : ''; ?>>
                            <?php echo \count($set) > 1 ? Text::sprintf('COM_VEL_FILTER_SEVERITY_AT_LEAST', Text::_(Severity::from($set[0])->label())) : Text::_(Severity::from($set[0])->label()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label" for="vel-cvss-min"><?php echo Text::_('COM_VEL_FILTER_CVSS_MIN'); ?></label>
                <input type="number" class="form-control" id="vel-cvss-min" name="cvss_min" min="0" max="10" step="0.1" value="<?php echo $f['cvss_min'] !== null ? $this->escape((string) $f['cvss_min']) : ''; ?>">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label" for="vel-cvss-max"><?php echo Text::_('COM_VEL_FILTER_CVSS_MAX'); ?></label>
                <input type="number" class="form-control" id="vel-cvss-max" name="cvss_max" min="0" max="10" step="0.1" value="<?php echo $f['cvss_max'] !== null ? $this->escape((string) $f['cvss_max']) : ''; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="vel-type"><?php echo Text::_('COM_VEL_FILTER_TYPE'); ?></label>
                <select class="form-select" id="vel-type" name="type">
                    <option value=""><?php echo Text::_('COM_VEL_FILTER_TYPE_ANY'); ?></option>
                    <?php foreach (ExtensionType::cases() as $type) : ?>
                        <option value="<?php echo $type->value; ?>" <?php echo $f['type'] === $type->value ? 'selected' : ''; ?>><?php echo Text::_($type->label()); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            </div>
        </div>
        <?php if ($f['search'] !== '' || $f['severity'] !== [] || $f['cvss_min'] !== null || $f['cvss_max'] !== null || $f['vendor'] !== '' || $f['type'] !== '') : ?>
            <div class="mt-2 small">
                <a href="<?php echo $listUrl . '?status=' . $this->escape($f['status']); ?>"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
            </div>
        <?php endif; ?>
    </form>

    <?php if (empty($this->items)) : ?>
        <div class="alert alert-success">
            <?php echo Text::_('COM_VEL_LIST_EMPTY_' . strtoupper($f['status'] === 'all' ? 'all' : $f['status'])); ?>
        </div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped com-vel-table">
                <caption class="visually-hidden"><?php echo Text::_('COM_VEL_LIST_HEADING'); ?></caption>
                <thead>
                <tr>
                    <th scope="col">
                        <a href="<?php echo $sortUrl('title'); ?>"><?php echo Text::_('JGLOBAL_TITLE'); ?></a>
                    </th>
                    <th scope="col"><?php echo Text::_('COM_VEL_HEADING_PRODUCTS'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_VEL_HEADING_VENDOR'); ?></th>
                    <?php if ($showCvss) : ?>
                        <th scope="col" class="text-nowrap">
                            <a href="<?php echo $sortUrl('score'); ?>"><?php echo Text::_('COM_VEL_HEADING_CVSS'); ?></a>
                        </th>
                    <?php endif; ?>
                    <th scope="col"><?php echo Text::_('COM_VEL_HEADING_SEVERITY'); ?></th>
                    <?php if ($f['status'] === 'all') : ?>
                        <th scope="col"><?php echo Text::_('COM_VEL_HEADING_STATUS'); ?></th>
                    <?php endif; ?>
                    <th scope="col" class="text-nowrap">
                        <a href="<?php echo $sortUrl('published'); ?>"><?php echo Text::_('COM_VEL_HEADING_PUBLISHED'); ?></a>
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($this->items as $item) : ?>
                    <?php
                    $status   = AdvisoryStatus::tryFrom((string) $item->status) ?? AdvisoryStatus::LIVE;
                    $severity = $item->severity ? Severity::tryFrom((string) $item->severity) : null;
                    ?>
                    <tr>
                        <th scope="row">
                            <a href="<?php echo VelHelper::route($item); ?>"><?php echo $this->escape($item->title); ?></a>
                            <div class="small text-muted">
                                <span class="font-monospace"><?php echo \sprintf('VEL-%04d', (int) $item->id); ?></span>
                                <?php if ($item->cve_id) : ?>
                                    &middot; <span class="font-monospace"><?php echo $this->escape($item->cve_id); ?></span>
                                <?php endif; ?>
                                <?php if ((string) $item->exploited === 'yes') : ?>
                                    &middot; <span class="badge bg-danger"><?php echo Text::_('COM_VEL_EXPLOITED_YES'); ?></span>
                                <?php endif; ?>
                            </div>
                        </th>
                        <td><?php echo $this->escape((string) $item->products); ?></td>
                        <td><?php echo $this->escape((string) $item->vendor); ?></td>
                        <?php if ($showCvss) : ?>
                            <td class="text-nowrap"><?php echo $item->cvss_score !== null ? number_format((float) $item->cvss_score, 1) : '&mdash;'; ?></td>
                        <?php endif; ?>
                        <td>
                            <?php if ($severity !== null) : ?>
                                <span class="badge bg-<?php echo $severity->badge(); ?>"><?php echo Text::_($severity->label()); ?></span>
                            <?php else : ?>
                                <span class="text-muted"><?php echo Text::_('COM_VEL_SEVERITY_UNRATED'); ?></span>
                            <?php endif; ?>
                        </td>
                        <?php if ($f['status'] === 'all') : ?>
                            <td><span class="badge bg-<?php echo $status->badge(); ?>"><?php echo Text::_($status->label()); ?></span></td>
                        <?php endif; ?>
                        <td class="text-nowrap">
                            <time datetime="<?php echo $this->escape((string) $item->date_published); ?>"><?php echo HTMLHelper::_('date', $item->date_published, Text::_('DATE_FORMAT_LC4')); ?></time>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php echo $this->pagination->getListFooter(); ?>
    <?php endif; ?>
</div>
