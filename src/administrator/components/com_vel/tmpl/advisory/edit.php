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
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\CveState;
use Jed\Component\Vel\Administrator\Enum\ReportKind;
use Jed\Component\Vel\Administrator\Enum\ReportState;
use Jed\Component\Vel\Administrator\Enum\RetiredReason;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Administrator\View\Advisory\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');
$wa->registerAndUseStyle('com_vel.admin', 'com_vel/css/admin.css');

$item   = $this->item;
$isNew  = empty($item->id);
$state  = $this->state;
$status = $item->status_enum ?? AdvisoryStatus::LIVE;

$embargoPending = !empty($item->date_public) && strtotime((string) $item->date_public) > time();
?>

<form action="<?php echo Route::_('index.php?option=com_vel&view=advisory&layout=edit&id=' . (int) $item->id); ?>"
      method="post" name="adminForm" id="advisory-form" class="form-validate" enctype="multipart/form-data" aria-label="<?php echo Text::_($isNew ? 'COM_VEL_ADVISORY_TITLE_NEW' : 'COM_VEL_ADVISORY_TITLE_EDIT'); ?>">

    <?php if (!$isNew) : ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="font-monospace fs-5"><?php echo $this->escape($item->reference); ?></span>
            <span class="badge bg-<?php echo $state->badge(); ?>"><?php echo Text::_($state->label()); ?></span>
            <span class="badge bg-<?php echo $status->badge(); ?>"><?php echo Text::_($status->label()); ?></span>
            <?php if (!empty($item->severity_enum)) : ?>
                <span class="badge bg-<?php echo $item->severity_enum->badge(); ?>">
                    <?php echo Text::_($item->severity_enum->label()); ?>
                    <?php if ($item->cvss_score !== null) : ?>(<?php echo number_format((float) $item->cvss_score, 1); ?>)<?php endif; ?>
                </span>
            <?php endif; ?>
            <?php if ($item->cve_id) : ?>
                <span class="badge bg-light text-dark font-monospace"><?php echo $this->escape($item->cve_id); ?></span>
            <?php endif; ?>
            <?php if ($state->isPublic()) : ?>
                <a class="ms-auto small" target="_blank" rel="noopener" href="<?php echo Route::link('site', 'index.php?option=com_vel&view=advisory&id=' . (int) $item->id . ':' . $item->alias); ?>">
                    <span class="icon-external-link" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_VIEW_PUBLIC'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-9">
            <?php echo HTMLHelper::_('uitab.startTabSet', 'velAdvisoryTabs', ['active' => 'advisory', 'recall' => true, 'breakpoint' => 768]); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'advisory', Text::_('COM_VEL_TAB_ADVISORY')); ?>
            <div class="row">
                <div class="col-lg-8">
                    <?php echo $this->form->renderField('title'); ?>
                    <?php echo $this->form->renderField('alias'); ?>
                    <?php echo $this->form->renderField('description'); ?>
                    <?php echo $this->form->renderField('solution'); ?>
                    <?php echo $this->form->renderField('workaround'); ?>
                </div>
                <div class="col-lg-4">
                    <?php echo $this->form->renderField('status'); ?>
                    <?php echo $this->form->renderField('status_override'); ?>
                    <?php if (!$isNew && isset($item->derived_status) && $item->derived_status !== $status) : ?>
                        <div class="alert alert-info small">
                            <?php echo Text::sprintf('COM_VEL_STATUS_DERIVED_DIFFERS', Text::_($item->derived_status->label())); ?>
                        </div>
                    <?php endif; ?>
                    <?php echo $this->form->renderField('exploited'); ?>
                    <?php echo $this->form->renderField('discovery'); ?>
                    <?php echo $this->form->renderField('assigned_to'); ?>
                </div>
            </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'products', Text::_('COM_VEL_TAB_PRODUCTS')); ?>
            <p class="text-muted"><?php echo Text::_('COM_VEL_TAB_PRODUCTS_INTRO'); ?></p>
            <?php echo $this->form->renderField('products'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'classification', Text::_('COM_VEL_TAB_CLASSIFICATION')); ?>
            <?php echo $this->form->renderField('cwes'); ?>
            <hr>
            <div class="row">
                <div class="col-lg-8">
                    <?php echo $this->form->renderField('cvss_vector'); ?>
                    <?php echo $this->form->renderField('cvss_score'); ?>
                    <p class="form-text">
                        <?php echo Text::_('COM_VEL_CVSS_HELP'); ?>
                        <a href="https://www.first.org/cvss/calculator/3.1" target="_blank" rel="noopener">CVSS 3.1</a> &middot;
                        <a href="https://www.first.org/cvss/calculator/4.0" target="_blank" rel="noopener">CVSS 4.0</a>
                    </p>
                </div>
                <div class="col-lg-4">
                    <?php echo $this->form->renderField('severity'); ?>
                    <?php echo $this->form->renderField('severity_reason'); ?>
                </div>
            </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'references', Text::_('COM_VEL_TAB_REFERENCES')); ?>
            <?php echo $this->form->renderField('references'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'credits', Text::_('COM_VEL_TAB_CREDITS')); ?>
            <?php echo $this->form->renderField('credits'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'dates', Text::_('COM_VEL_TAB_DATES')); ?>
            <div class="row">
                <div class="col-lg-6">
                    <?php echo $this->form->renderField('date_reported'); ?>
                    <?php echo $this->form->renderField('date_vendor_notified'); ?>
                    <?php echo $this->form->renderField('date_public'); ?>
                    <?php echo $this->form->renderField('date_fixed'); ?>
                </div>
                <div class="col-lg-6">
                    <?php if (!$isNew) : ?>
                        <h4 class="fs-6"><?php echo Text::_('COM_VEL_DATES_AUTOMATIC'); ?></h4>
                        <p class="text-muted small"><?php echo Text::_('COM_VEL_DATES_AUTOMATIC_DESC'); ?></p>
                        <dl class="row small">
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_DATE_RESERVED_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo $item->date_reserved ? HTMLHelper::_('date', $item->date_reserved, Text::_('DATE_FORMAT_LC2')) : '&mdash;'; ?></dd>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_DATE_PUBLISHED_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo $item->date_published ? HTMLHelper::_('date', $item->date_published, Text::_('DATE_FORMAT_LC2')) : '&mdash;'; ?></dd>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_DATE_UPDATED_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo $item->date_updated ? HTMLHelper::_('date', $item->date_updated, Text::_('DATE_FORMAT_LC2')) : '&mdash;'; ?></dd>
                            <?php if (!empty($item->date_retired)) : ?>
                                <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_DATE_RETIRED_LABEL'); ?></dt>
                                <dd class="col-7"><?php echo HTMLHelper::_('date', $item->date_retired, Text::_('DATE_FORMAT_LC2')); ?></dd>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php if (!$isNew) : ?>
                <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'reports', Text::_('COM_VEL_TAB_REPORTS')); ?>
                <?php if (empty($item->reports)) : ?>
                    <p class="text-muted"><?php echo Text::_('COM_VEL_NO_REPORTS_LINKED'); ?></p>
                <?php else : ?>
                    <table class="table table-sm">
                        <caption class="visually-hidden"><?php echo Text::_('COM_VEL_TAB_REPORTS'); ?></caption>
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_REFERENCE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_KIND'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_REPORTER'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_EXTENSION'); ?></th>
                            <th scope="col"><?php echo Text::_('JSTATUS'); ?></th>
                            <th scope="col"><?php echo Text::_('JDATE'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($item->reports as $report) : ?>
                            <?php
                            $kind   = ReportKind::tryFrom((string) $report->kind) ?? ReportKind::VULNERABILITY;
                            $rstate = ReportState::tryFrom((string) $report->state) ?? ReportState::NEW;
                            ?>
                            <tr>
                                <td><a href="<?php echo Route::_('index.php?option=com_vel&task=report.edit&id=' . (int) $report->id); ?>"><?php echo \sprintf('VELR-%05d', (int) $report->id); ?></a></td>
                                <td><?php echo Text::_($kind->label()); ?></td>
                                <td>
                                    <?php if ($this->can['communicate']) : ?>
                                        <?php echo $this->escape($report->reporter_name); ?>
                                        <?php if ($report->reporter_organisation) : ?><span class="text-muted">(<?php echo $this->escape($report->reporter_organisation); ?>)</span><?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted"><?php echo Text::_('COM_VEL_REPORTER_HIDDEN'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $this->escape($report->extension_name); ?> <span class="text-muted"><?php echo $this->escape($report->extension_version); ?></span></td>
                                <td><span class="badge bg-<?php echo $rstate->badge(); ?>"><?php echo Text::_($rstate->label()); ?></span></td>
                                <td><?php echo HTMLHelper::_('date', $report->created, Text::_('DATE_FORMAT_LC4')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($this->can['communicate']) : ?>
                    <?php echo $this->loadTemplate('messages'); ?>
                <?php endif; ?>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>

                <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'cve', Text::_('COM_VEL_TAB_CVE')); ?>
                <?php echo $this->loadTemplate('cve'); ?>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>

                <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'history', Text::_('COM_VEL_TAB_HISTORY')); ?>
                <?php if (empty($item->history)) : ?>
                    <p class="text-muted"><?php echo Text::_('COM_VEL_NO_HISTORY'); ?></p>
                <?php else : ?>
                    <table class="table table-sm">
                        <caption class="visually-hidden"><?php echo Text::_('COM_VEL_TAB_HISTORY'); ?></caption>
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_REVISION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_STATE'); ?></th>
                            <th scope="col"><?php echo Text::_('JDATE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_BY'); ?></th>
                            <th scope="col"></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($item->history as $revision) : ?>
                            <tr>
                                <td><?php echo (int) $revision->revision; ?></td>
                                <td><?php echo Text::_((AdvisoryState::tryFrom((string) $revision->state) ?? AdvisoryState::DRAFT)->label()); ?></td>
                                <td><?php echo HTMLHelper::_('date', $revision->created, Text::_('DATE_FORMAT_LC2')); ?></td>
                                <td><?php echo $revision->name ? $this->escape($revision->name) : Text::_('COM_VEL_AUTOMATION'); ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_vel&task=advisory.revision&id=' . (int) $item->id . '&revision=' . (int) $revision->id . '&' . \Joomla\CMS\Session\Session::getFormToken() . '=1'); ?>">
                                        <?php echo Text::_('COM_VEL_BUTTON_VIEW_JSON'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php endif; ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'velAdvisoryTabs', 'notes', Text::_('COM_VEL_TAB_NOTES')); ?>
            <?php echo $this->form->renderField('external_ticket_url'); ?>
            <?php echo $this->form->renderField('internal_notes'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
        </div>

        <div class="col-lg-3">
            <?php if (!$isNew) : ?>
                <div class="card">
                    <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_WORKFLOW'); ?></div>
                    <div class="card-body">
                        <?php if (!empty($item->problems)) : ?>
                            <div class="alert alert-warning small">
                                <strong><?php echo Text::_('COM_VEL_NOT_READY_BECAUSE'); ?></strong>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($item->problems as $problem) : ?>
                                        <li><?php echo $this->escape($problem); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($state === AdvisoryState::DRAFT && $this->can['state']) : ?>
                            <button type="button" class="btn btn-outline-primary w-100 mb-2" <?php echo $item->problems ? 'disabled' : ''; ?>
                                    onclick="Joomla.submitbutton('advisory.ready')">
                                <span class="icon-checkbox" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_READY'); ?>
                            </button>
                            <p class="form-text"><?php echo Text::_('COM_VEL_BUTTON_READY_DESC'); ?></p>
                        <?php endif; ?>

                        <?php if ($state === AdvisoryState::READY) : ?>
                            <?php if ($this->can['publish']) : ?>
                                <?php if ($embargoPending) : ?>
                                    <div class="alert alert-info small">
                                        <?php echo Text::sprintf('COM_VEL_EMBARGO_UNTIL', HTMLHelper::_('date', $item->date_public, Text::_('DATE_FORMAT_LC2'))); ?>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" value="1" id="force" name="force">
                                        <label class="form-check-label" for="force"><?php echo Text::_('COM_VEL_FIELD_FORCE_PUBLISH_LABEL'); ?></label>
                                    </div>
                                <?php endif; ?>

                                <?php if ($this->can['jed'] && $this->jedTicketMode === 'ask') : ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" value="1" id="open_jed_ticket" name="open_jed_ticket" <?php echo $status === AdvisoryStatus::LIVE ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="open_jed_ticket"><?php echo Text::_('COM_VEL_FIELD_OPEN_JED_TICKET_LABEL'); ?></label>
                                        <div class="form-text"><?php echo Text::_('COM_VEL_FIELD_OPEN_JED_TICKET_DESC'); ?></div>
                                    </div>
                                <?php endif; ?>

                                <button type="button" class="btn btn-success w-100 mb-2"
                                        onclick="if (confirm('<?php echo $this->escape(Text::_('COM_VEL_CONFIRM_PUBLISH')); ?>')) { Joomla.submitbutton('advisory.publish'); }">
                                    <span class="icon-publish" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_PUBLISH'); ?>
                                </button>
                            <?php else : ?>
                                <div class="alert alert-info small"><?php echo Text::_('COM_VEL_AWAITING_PUBLISHER'); ?></div>
                            <?php endif; ?>

                            <?php if ($this->can['state']) : ?>
                                <button type="button" class="btn btn-outline-secondary w-100 mb-2" onclick="Joomla.submitbutton('advisory.draft')">
                                    <span class="icon-pencil" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_DRAFT'); ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($state === AdvisoryState::PUBLISHED && $this->can['publish']) : ?>
                            <fieldset class="mt-2">
                                <legend class="fs-6"><?php echo Text::_('COM_VEL_LEGEND_RETIRE'); ?></legend>
                                <label class="form-label" for="retired_reason"><?php echo Text::_('COM_VEL_FIELD_RETIRED_REASON_LABEL'); ?></label>
                                <select class="form-select mb-2" id="retired_reason" name="retired_reason">
                                    <?php foreach (RetiredReason::cases() as $reason) : ?>
                                        <option value="<?php echo $reason->value; ?>"><?php echo Text::_($reason->label()); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="form-label" for="retire_note"><?php echo Text::_('COM_VEL_FIELD_RETIRE_NOTE_LABEL'); ?></label>
                                <textarea class="form-control mb-2" id="retire_note" name="retire_note" rows="2"></textarea>
                                <button type="button" class="btn btn-outline-danger w-100"
                                        onclick="if (confirm('<?php echo $this->escape(Text::_('COM_VEL_CONFIRM_RETIRE')); ?>')) { Joomla.submitbutton('advisory.retire'); }">
                                    <span class="icon-unpublish" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_RETIRE'); ?>
                                </button>
                            </fieldset>
                        <?php endif; ?>

                        <?php if ($state === AdvisoryState::RETIRED && $this->can['publish']) : ?>
                            <p class="small">
                                <?php echo Text::sprintf('COM_VEL_RETIRED_BECAUSE', Text::_((RetiredReason::tryFrom((string) $item->retired_reason) ?? RetiredReason::OTHER)->label())); ?>
                            </p>
                            <button type="button" class="btn btn-outline-success w-100"
                                    onclick="if (confirm('<?php echo $this->escape(Text::_('COM_VEL_CONFIRM_REPUBLISH')); ?>')) { Joomla.submitbutton('advisory.publish'); }">
                                <span class="icon-publish" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_REPUBLISH'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_FACTS'); ?></div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-5"><?php echo Text::_('COM_VEL_HEADING_SOURCE'); ?></dt>
                            <dd class="col-7"><?php echo Text::_('COM_VEL_SOURCE_' . strtoupper((string) $item->source)); ?></dd>
                            <dt class="col-5"><?php echo Text::_('JGLOBAL_FIELD_CREATED_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC2')); ?></dd>
                            <?php if ($item->modified) : ?>
                                <dt class="col-5"><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_LABEL'); ?></dt>
                                <dd class="col-7"><?php echo HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC2')); ?></dd>
                            <?php endif; ?>
                            <?php if ((int) ($item->jed_ticket_id ?? 0) > 0) : ?>
                                <dt class="col-5"><?php echo Text::_('COM_VEL_HEADING_JED_TICKET'); ?></dt>
                                <dd class="col-7"><a href="<?php echo Route::_('index.php?option=com_jed&task=ticket.edit&id=' . (int) $item->jed_ticket_id); ?>">#<?php echo (int) $item->jed_ticket_id; ?></a></dd>
                            <?php elseif ($this->can['jed'] && $state->isPublic()) : ?>
                                <dt class="col-5"><?php echo Text::_('COM_VEL_HEADING_JED_TICKET'); ?></dt>
                                <dd class="col-7"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="Joomla.submitbutton('advisory.jedticket')"><?php echo Text::_('COM_VEL_BUTTON_OPEN_JED_TICKET'); ?></button></dd>
                            <?php endif; ?>
                            <?php if ((int) ($item->abandonware_case_id ?? 0) > 0) : ?>
                                <dt class="col-5"><?php echo Text::_('COM_VEL_HEADING_ABANDONWARE_CASE'); ?></dt>
                                <dd class="col-7"><a href="<?php echo Route::_('index.php?option=com_abandonware&task=case.edit&id=' . (int) $item->abandonware_case_id); ?>">#<?php echo (int) $item->abandonware_case_id; ?></a></dd>
                            <?php endif; ?>
                            <?php if (!empty($item->external_ticket_url)) : ?>
                                <dt class="col-5"><?php echo Text::_('COM_VEL_HEADING_EXTERNAL_TICKET'); ?></dt>
                                <dd class="col-7 text-break"><a href="<?php echo $this->escape($item->external_ticket_url); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_VEL_OPEN'); ?></a></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            <?php else : ?>
                <div class="alert alert-info small"><?php echo Text::_('COM_VEL_NEW_ADVISORY_HINT'); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="id" value="<?php echo (int) $item->id; ?>"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
