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
use Jed\Component\Vel\Administrator\Enum\FeedClassification;
use Jed\Component\Vel\Administrator\Enum\FeedDecision;
use Jed\Component\Vel\Administrator\Version\VersionRange;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Administrator\View\Feeditem\HtmlView $this */

$item     = $this->item;
$n        = (array) $item->normalised;
$advisory = $item->advisory;
$aState   = $advisory ? (AdvisoryState::tryFrom((string) $advisory->state) ?? AdvisoryState::DRAFT) : null;

$range = static fn (array $r): string => (string) VersionRange::fromStrings((string) ($r['from'] ?? '*'), (string) ($r['to'] ?? '*'));
?>
<form action="<?php echo Route::_('index.php?option=com_vel&view=feeditem&layout=edit&id=' . (int) $item->id); ?>" method="post" name="adminForm" id="feeditem-form">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="font-monospace fs-5"><?php echo $this->escape($item->external_id); ?></span>
        <span class="badge bg-light text-dark"><?php echo Text::_('COM_VEL_FEED_SOURCE_' . strtoupper((string) $item->source)); ?></span>
        <span class="badge bg-info"><?php echo Text::_($this->classification->label()); ?></span>
        <span class="badge bg-<?php echo match ($this->decision) {
            FeedDecision::PENDING => 'warning text-dark', FeedDecision::CREATED, FeedDecision::MERGED => 'success', default => 'secondary'
        }; ?>"><?php echo Text::_($this->decision->label()); ?></span>
        <?php if (!empty($n['source_url'])) : ?>
            <a class="ms-auto small" href="<?php echo $this->escape($n['source_url']); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_VEL_FEED_VIEW_SOURCE'); ?></a>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><?php echo Text::_('COM_VEL_FEED_EXTERNAL_RECORD'); ?></div>
                <div class="card-body small">
                    <h3 class="h6"><?php echo $this->escape((string) ($n['title'] ?? '')); ?></h3>
                    <dl class="row">
                        <dt class="col-4"><?php echo Text::_('COM_VEL_HEADING_ASSIGNER'); ?></dt><dd class="col-8"><?php echo $this->escape((string) ($n['assigner'] ?? '')); ?> (<?php echo $this->escape((string) ($n['state'] ?? '')); ?>)</dd>
                        <dt class="col-4"><?php echo Text::_('COM_VEL_HEADING_PUBLISHED'); ?></dt><dd class="col-8"><?php echo $this->escape((string) ($n['dates']['published'] ?? '—')); ?></dd>
                        <dt class="col-4"><?php echo Text::_('COM_VEL_HEADING_UPDATED'); ?></dt><dd class="col-8"><?php echo $this->escape((string) ($n['dates']['updated'] ?? '—')); ?></dd>
                        <dt class="col-4"><?php echo Text::_('COM_VEL_HEADING_CVSS'); ?></dt>
                        <dd class="col-8"><?php echo !empty($n['cvss']) ? $this->escape((string) $n['cvss']['score'] . ' ' . strtoupper((string) $n['cvss']['severity']) . ' — ' . $n['cvss']['vector'] . ' (' . ($n['cvss']['from'] ?? '') . ')') : '—'; ?></dd>
                        <dt class="col-4"><?php echo Text::_('COM_VEL_HEADING_WEAKNESS'); ?></dt>
                        <dd class="col-8"><?php echo !empty($n['cwes']) ? $this->escape(implode(', ', array_map(static fn ($c) => (string) $c['id'], $n['cwes']))) : '—'; ?></dd>
                    </dl>
                    <p class="text-prewrap"><?php echo nl2br($this->escape((string) ($n['description'] ?? ''))); ?></p>
                    <h4 class="h6"><?php echo Text::_('COM_VEL_HEADING_AFFECTED'); ?></h4>
                    <ul>
                        <?php foreach ((array) ($n['affected'] ?? []) as $product) : ?>
                            <li>
                                <strong><?php echo $this->escape((string) $product['vendor']); ?></strong> / <?php echo $this->escape((string) $product['product']); ?>
                                <?php if (!empty($product['package'])) : ?><span class="font-monospace text-muted">(<?php echo $this->escape((string) $product['package']); ?>)</span><?php endif; ?>
                                <?php if (!empty($product['versions'])) : ?>: <?php echo $this->escape(implode('; ', array_map($range, (array) $product['versions']))); ?><?php endif; ?>
                                <?php if (!empty($product['fixed'])) : ?> &rarr; <?php echo $this->escape((string) $product['fixed']); ?><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($n['references'])) : ?>
                        <h4 class="h6"><?php echo Text::_('COM_VEL_HEADING_REFERENCES'); ?></h4>
                        <ul>
                            <?php foreach ((array) $n['references'] as $reference) : ?>
                                <li class="text-break"><a href="<?php echo $this->escape((string) $reference['url']); ?>" target="_blank" rel="noopener"><?php echo $this->escape((string) $reference['url']); ?></a> <span class="text-muted"><?php echo $this->escape(implode(', ', (array) ($reference['tags'] ?? []))); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><?php echo Text::_('COM_VEL_FEED_OUR_ADVISORY'); ?></div>
                <div class="card-body small">
                    <?php if ($advisory === null) : ?>
                        <p class="text-muted"><?php echo Text::_('COM_VEL_FEED_NO_ADVISORY'); ?></p>
                        <?php if ($this->canDecide && $this->decision === FeedDecision::PENDING) : ?>
                            <?php if ($this->classification !== FeedClassification::JOOMLA_CORE) : ?>
                                <button type="button" class="btn btn-primary mb-3" onclick="Joomla.submitbutton('feeditem.create')">
                                    <span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_FEED_CREATE'); ?>
                                </button>
                            <?php else : ?>
                                <div class="alert alert-secondary"><?php echo Text::_('COM_VEL_FEED_CORE_NOTE'); ?></div>
                            <?php endif; ?>
                            <fieldset class="mb-3">
                                <legend class="fs-6"><?php echo Text::_('COM_VEL_LEGEND_MERGE_INTO'); ?></legend>
                                <input type="text" class="form-control mb-2" name="advisory_reference" placeholder="VEL-0042">
                                <button type="button" class="btn btn-outline-primary" onclick="Joomla.submitbutton('feeditem.merge')"><?php echo Text::_('COM_VEL_BUTTON_FEED_MERGE'); ?></button>
                            </fieldset>
                        <?php endif; ?>
                    <?php else : ?>
                        <h3 class="h6">
                            <a href="<?php echo Route::_('index.php?option=com_vel&task=advisory.edit&id=' . (int) $advisory->id); ?>"><?php echo \sprintf('VEL-%04d', (int) $advisory->id); ?> <?php echo $this->escape($advisory->title); ?></a>
                            <span class="badge bg-<?php echo $aState->badge(); ?>"><?php echo Text::_($aState->label()); ?></span>
                        </h3>
                        <dl class="row">
                            <dt class="col-4"><?php echo Text::_('COM_VEL_HEADING_CVE'); ?></dt><dd class="col-8"><?php echo $this->escape((string) ($advisory->cve_id ?: '—')); ?></dd>
                            <dt class="col-4"><?php echo Text::_('COM_VEL_HEADING_CVSS'); ?></dt><dd class="col-8"><?php echo $advisory->cvss_vector ? $this->escape($advisory->cvss_score . ' — ' . $advisory->cvss_vector) : '—'; ?></dd>
                            <dt class="col-4"><?php echo Text::_('COM_VEL_HEADING_WEAKNESS'); ?></dt><dd class="col-8"><?php echo $advisory->cwes ? $this->escape(implode(', ', array_map(static fn ($c) => (string) $c->cwe_id, (array) $advisory->cwes))) : '—'; ?></dd>
                            <dt class="col-4"><?php echo Text::_('COM_VEL_HEADING_UPDATED'); ?></dt><dd class="col-8"><?php echo $advisory->date_updated ? HTMLHelper::_('date', $advisory->date_updated, Text::_('DATE_FORMAT_LC2')) : '—'; ?></dd>
                        </dl>
                        <h4 class="h6"><?php echo Text::_('COM_VEL_HEADING_AFFECTED'); ?></h4>
                        <ul>
                            <?php foreach ((array) $advisory->products as $product) : ?>
                                <li>
                                    <strong><?php echo $this->escape($product->vendor); ?></strong> / <?php echo $this->escape($product->product); ?>:
                                    <?php echo $this->escape(implode('; ', array_map(static fn ($v) => (string) VersionRange::fromStrings((string) $v->version_from, (string) $v->version_to), (array) $product->versions))) ?: '—'; ?>
                                    <?php if ($product->fixed_version) : ?> &rarr; <?php echo $this->escape($product->fixed_version); ?><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($this->canDecide && $this->decision === FeedDecision::PENDING) : ?>
                            <input type="hidden" name="advisory_id" value="<?php echo (int) $advisory->id; ?>">
                            <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('feeditem.merge')">
                                <span class="icon-download" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_FEED_MERGE_MISSING'); ?>
                            </button>
                            <p class="form-text"><?php echo Text::_('COM_VEL_FEED_MERGE_DESC'); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($this->canDecide && $this->decision === FeedDecision::PENDING) : ?>
                        <hr>
                        <button type="button" class="btn btn-outline-secondary" onclick="Joomla.submitbutton('feeditem.ignore')">
                            <span class="icon-times" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_FEED_IGNORE'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="id" value="<?php echo (int) $item->id; ?>"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
