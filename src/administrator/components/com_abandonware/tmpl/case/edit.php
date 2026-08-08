<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Abandonware\Administrator\Enum\CaseSource;
use Jed\Component\Abandonware\Administrator\Enum\CaseStatus;
use Jed\Component\Abandonware\Administrator\Enum\Resolution;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Abandonware\Administrator\View\Case\HtmlView $this */

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('bootstrap.tab');

$item      = $this->item;
$status    = $this->status;
$hasContact = !empty($item->contact_time);

// The two conditions the mark button needs, kept apart so the reason it is unavailable can be
// stated rather than left to be guessed at. CaseService checks both again - this only decides
// what to draw.
$canReachAbandoned = $status->canMoveTo(CaseStatus::ABANDONED);
?>

<form action="<?php echo Route::_('index.php?option=com_abandonware&layout=edit&id=' . (int) $item->id); ?>"
      method="post" name="adminForm" id="case-form" class="form-validate">

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">
                        <?php echo $this->escape($item->extension_name) ?: Text::_('COM_ABANDONWARE_UNNAMED_EXTENSION'); ?>
                        <span class="badge bg-<?php echo $status->badge(); ?>"><?php echo Text::_($status->label()); ?></span>
                    </h2>

                    <?php foreach ($this->form->getFieldset('case') as $field) : ?>
                        <div class="mb-2"><?php echo $field->renderField(); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><?php echo Text::_('COM_ABANDONWARE_HEADING_SIGNALS'); ?></div>
                <div class="card-body">
                    <?php if (empty($item->decoded_signals)) : ?>
                        <p class="text-muted mb-0"><?php echo Text::_('JNONE'); ?></p>
                    <?php else : ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($item->decoded_signals as $signal) : ?>
                                <?php $source = CaseSource::tryFrom((string) ($signal['source'] ?? '')) ?? CaseSource::MANUAL; ?>
                                <li class="list-group-item px-0">
                                    <span class="badge bg-info"><?php echo Text::_($source->label()); ?></span>
                                    <?php echo $this->escape((string) ($signal['detail'] ?? '')); ?>
                                    <span class="small text-muted d-block"><?php echo $this->escape((string) ($signal['time'] ?? '')); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($item->linkchecks)) : ?>
                <div class="card mt-3">
                    <div class="card-header"><?php echo Text::_('COM_ABANDONWARE_HEADING_LINK_EVIDENCE'); ?></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <caption class="visually-hidden"><?php echo Text::_('COM_ABANDONWARE_HEADING_LINK_EVIDENCE'); ?></caption>
                            <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_ABANDONWARE_HEADING_LINK_TYPE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_ABANDONWARE_HEADING_URL'); ?></th>
                                <th scope="col"><?php echo Text::_('JSTATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_ABANDONWARE_HEADING_FAILURES'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($item->linkchecks as $link) : ?>
                                <tr>
                                    <td><?php echo $this->escape((string) $link->link_type); ?></td>
                                    <td class="text-break small"><?php echo $this->escape((string) $link->url); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo (string) $link->status === 'hard' ? 'danger' : 'warning text-dark'; ?>">
                                            <?php echo $this->escape((string) $link->status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo (int) $link->fail_count; ?>
                                        <?php if (!empty($link->first_failed)) : ?>
                                            <span class="small text-muted d-block"><?php echo Text::sprintf('COM_ABANDONWARE_SINCE', $this->escape((string) $link->first_failed)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($item->reports)) : ?>
                <div class="card mt-3">
                    <div class="card-header"><?php echo Text::_('COM_ABANDONWARE_HEADING_REPORTS'); ?></div>
                    <div class="card-body">
                        <?php foreach ($item->reports as $report) : ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="small text-muted">
                                    <?php echo $this->escape((string) $report->reporter_name); ?>
                                    &lt;<?php echo $this->escape((string) $report->reporter_email); ?>&gt;
                                    &middot; <?php echo HTMLHelper::_('date', $report->created, Text::_('DATE_FORMAT_LC2')); ?>
                                    <?php if ((int) $report->consent_to_process !== 1) : ?>
                                        <span class="badge bg-warning text-dark"><?php echo Text::_('COM_ABANDONWARE_NO_CONSENT_RECORDED'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div><?php echo nl2br($this->escape((string) $report->reason)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?php echo Text::_('COM_ABANDONWARE_HEADING_WORKFLOW'); ?></div>
                <div class="card-body">

                    <?php if ($this->can['edit'] && $status === CaseStatus::RECEIVED) : ?>
                        <button type="button" class="btn btn-outline-primary w-100 mb-3"
                                onclick="Joomla.submitbutton('case.claim')">
                            <span class="icon-user" aria-hidden="true"></span> <?php echo Text::_('COM_ABANDONWARE_BUTTON_CLAIM'); ?>
                        </button>
                    <?php endif; ?>

                    <?php
                    /*
                     * Step 3. It is drawn first, ahead of everything else, and the marking controls
                     * below are inert until it has happened. 4.10 calls this the step most likely
                     * to be skipped; making it the only thing available at this point is the
                     * cheapest way to make skipping it hard.
                     */
                    ?>
                    <?php if ($this->can['contact'] && $status->isOpen() && $status !== CaseStatus::ABANDONED) : ?>
                        <fieldset class="mb-3">
                            <legend class="fs-6"><?php echo Text::_('COM_ABANDONWARE_LEGEND_CONTACT'); ?></legend>

                            <?php if ($hasContact) : ?>
                                <p class="small text-muted">
                                    <?php echo Text::sprintf(
                                        'COM_ABANDONWARE_CONTACTED_ON',
                                        HTMLHelper::_('date', $item->contact_time, Text::_('DATE_FORMAT_LC2'))
                                    ); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ((int) ($item->extension_id ?? 0) <= 0) : ?>
                                <div class="alert alert-warning small">
                                    <?php echo Text::_('COM_ABANDONWARE_WARN_NO_LISTING_TO_MAIL'); ?>
                                </div>
                            <?php endif; ?>

                            <label class="form-label" for="grace_days"><?php echo Text::_('COM_ABANDONWARE_FIELD_GRACE_DAYS_LABEL'); ?></label>
                            <input type="number" class="form-control mb-2" id="grace_days" name="grace_days"
                                   min="1" max="365" value="<?php echo (int) $this->graceDays; ?>">

                            <label class="form-label" for="contact_note"><?php echo Text::_('COM_ABANDONWARE_FIELD_CONTACT_NOTE_LABEL'); ?></label>
                            <textarea class="form-control mb-2" id="contact_note" name="contact_note" rows="3"><?php echo $this->escape((string) ($item->contact_note ?? '')); ?></textarea>

                            <button type="button" class="btn btn-primary w-100"
                                    onclick="Joomla.submitbutton('case.contact')">
                                <span class="icon-envelope" aria-hidden="true"></span>
                                <?php echo Text::_($hasContact ? 'COM_ABANDONWARE_BUTTON_CONTACT_AGAIN' : 'COM_ABANDONWARE_BUTTON_CONTACT'); ?>
                            </button>
                        </fieldset>
                    <?php endif; ?>

                    <?php if ($this->can['mark'] && $status->isOpen() && $status !== CaseStatus::ABANDONED) : ?>
                        <fieldset class="mb-3">
                            <legend class="fs-6"><?php echo Text::_('COM_ABANDONWARE_LEGEND_MARK'); ?></legend>

                            <?php if (!$hasContact) : ?>
                                <div class="alert alert-warning small mb-0">
                                    <?php echo Text::_('COM_ABANDONWARE_WARN_CONTACT_FIRST'); ?>
                                </div>
                            <?php elseif (!$canReachAbandoned) : ?>
                                <div class="alert alert-info small mb-0">
                                    <?php echo Text::_('COM_ABANDONWARE_WARN_NOT_MARKABLE_YET'); ?>
                                </div>
                            <?php else : ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="publish" name="publish">
                                    <label class="form-check-label" for="publish">
                                        <?php echo Text::_('COM_ABANDONWARE_FIELD_PUBLISH_LABEL'); ?>
                                    </label>
                                    <div class="form-text"><?php echo Text::_('COM_ABANDONWARE_FIELD_PUBLISH_DESC'); ?></div>
                                </div>

                                <button type="button" class="btn btn-danger w-100"
                                        onclick="if (confirm('<?php echo $this->escape(Text::_('COM_ABANDONWARE_CONFIRM_MARK')); ?>')) { Joomla.submitbutton('case.markabandoned'); }">
                                    <span class="icon-warning" aria-hidden="true"></span> <?php echo Text::_('COM_ABANDONWARE_BUTTON_MARK'); ?>
                                </button>
                            <?php endif; ?>
                        </fieldset>
                    <?php endif; ?>

                    <?php if ($this->can['resolve'] && $status->isOpen()) : ?>
                        <fieldset>
                            <legend class="fs-6"><?php echo Text::_('COM_ABANDONWARE_LEGEND_RESOLVE'); ?></legend>

                            <label class="form-label" for="resolution"><?php echo Text::_('COM_ABANDONWARE_FIELD_RESOLUTION_LABEL'); ?></label>
                            <select class="form-select mb-2" id="resolution" name="resolution">
                                <?php foreach (Resolution::cases() as $resolution) : ?>
                                    <option value="<?php echo $resolution->value; ?>"><?php echo Text::_($resolution->label()); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label class="form-label" for="resolve_note"><?php echo Text::_('COM_ABANDONWARE_FIELD_RESOLVE_NOTE_LABEL'); ?></label>
                            <textarea class="form-control mb-2" id="resolve_note" name="resolve_note" rows="2"></textarea>

                            <button type="button" class="btn btn-success w-100"
                                    onclick="Joomla.submitbutton('case.resolve')">
                                <span class="icon-checkmark" aria-hidden="true"></span> <?php echo Text::_('COM_ABANDONWARE_BUTTON_RESOLVE'); ?>
                            </button>
                        </fieldset>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><?php echo Text::_('COM_ABANDONWARE_HEADING_CASE_FACTS'); ?></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5"><?php echo Text::_('COM_ABANDONWARE_HEADING_SOURCE'); ?></dt>
                        <dd class="col-7"><?php echo Text::_((CaseSource::tryFrom((string) $item->source) ?? CaseSource::MANUAL)->label()); ?></dd>

                        <dt class="col-5"><?php echo Text::_('JGLOBAL_FIELD_CREATED_LABEL'); ?></dt>
                        <dd class="col-7"><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC2')); ?></dd>

                        <?php if ((int) ($item->ticket_id ?? 0) > 0) : ?>
                            <dt class="col-5"><?php echo Text::_('COM_ABANDONWARE_HEADING_TICKET'); ?></dt>
                            <dd class="col-7">
                                <a href="<?php echo Route::_('index.php?option=com_tickets&task=ticket.edit&id=' . (int) $item->ticket_id); ?>">
                                    #<?php echo (int) $item->ticket_id; ?>
                                </a>
                            </dd>
                        <?php endif; ?>

                        <?php if (!empty($item->listing)) : ?>
                            <dt class="col-5"><?php echo Text::_('COM_ABANDONWARE_HEADING_LISTING_MODIFIED'); ?></dt>
                            <dd class="col-7"><?php echo $item->listing->modified ? HTMLHelper::_('date', $item->listing->modified, Text::_('DATE_FORMAT_LC4')) : '&mdash;'; ?></dd>

                            <?php if (!empty($item->listing->last_update_check_error)) : ?>
                                <dt class="col-5"><?php echo Text::_('COM_ABANDONWARE_HEADING_UPDATE_ERROR'); ?></dt>
                                <dd class="col-7 text-danger"><?php echo $this->escape((string) $item->listing->last_update_check_error); ?></dd>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($item->abandoned_time)) : ?>
                            <dt class="col-5"><?php echo Text::_('COM_ABANDONWARE_HEADING_MARKED'); ?></dt>
                            <dd class="col-7"><?php echo HTMLHelper::_('date', $item->abandoned_time, Text::_('DATE_FORMAT_LC2')); ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($item->resolved_time)) : ?>
                            <dt class="col-5"><?php echo Text::_('COM_ABANDONWARE_HEADING_RESOLVED'); ?></dt>
                            <dd class="col-7">
                                <?php echo HTMLHelper::_('date', $item->resolved_time, Text::_('DATE_FORMAT_LC2')); ?>
                                <?php if ($item->resolution) : ?>
                                    <br><?php echo Text::_('COM_ABANDONWARE_RESOLUTION_' . strtoupper((string) $item->resolution)); ?>
                                <?php endif; ?>
                            </dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="id" value="<?php echo (int) $item->id; ?>"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
