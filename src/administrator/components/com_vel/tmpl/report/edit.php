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
use Jed\Component\Vel\Administrator\Enum\ReportKind;
use Jed\Component\Vel\Administrator\Enum\ReportState;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Jed\Component\Vel\Administrator\View\Report\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_vel.admin', 'com_vel/css/admin.css');

$item  = $this->item;
$state = $this->state;
$kind  = $this->kind;

$field = function (string $label, ?string $value, bool $pre = false): void {
    if ($value === null || trim($value) === '') {
        return;
    }

    echo '<dt class="col-sm-3">' . Text::_($label) . '</dt><dd class="col-sm-9' . ($pre ? ' text-prewrap' : '') . '">'
        . ($pre ? nl2br($this->escape($value)) : $this->escape($value)) . '</dd>';
};
?>

<form action="<?php echo Route::_('index.php?option=com_vel&view=report&layout=edit&id=' . (int) $item->id); ?>"
      method="post" name="adminForm" id="report-form" enctype="multipart/form-data">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="font-monospace fs-5"><?php echo $this->escape($item->reference); ?></span>
        <span class="badge bg-<?php echo $state->badge(); ?>"><?php echo Text::_($state->label()); ?></span>
        <span class="badge bg-secondary"><?php echo Text::_($kind->label()); ?></span>
        <span class="badge bg-light text-dark"><?php echo Text::_('COM_VEL_CHANNEL_' . strtoupper((string) $item->channel)); ?></span>
        <?php if ((string) $item->actively_exploited === 'yes') : ?>
            <span class="badge bg-danger"><?php echo Text::_('COM_VEL_EXPLOITED_YES'); ?></span>
        <?php endif; ?>
        <?php if ((int) $item->publicly_disclosed === 1) : ?>
            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_VEL_BADGE_PUBLIC'); ?></span>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_REPORT'); ?></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <?php $field('COM_VEL_FIELD_EXTENSION_NAME_LABEL', (string) $item->extension_name . ((string) $item->extension_version !== '' ? ' ' . $item->extension_version : '')); ?>
                        <?php if ((int) ($item->extension_id ?? 0) > 0) : ?>
                            <dt class="col-sm-3"><?php echo Text::_('COM_VEL_FIELD_EXTENSION_ID_LABEL'); ?></dt>
                            <dd class="col-sm-9"><a href="<?php echo Route::_('index.php?option=com_jed&task=extension.edit&id=' . (int) $item->extension_id); ?>">#<?php echo (int) $item->extension_id; ?></a></dd>
                        <?php endif; ?>
                        <?php $field('COM_VEL_FIELD_EXTENSION_URL_LABEL', (string) $item->extension_url); ?>
                        <?php $field('COM_VEL_FIELD_DEVELOPER_NAME_LABEL', (string) $item->developer_name); ?>
                        <?php if ($this->can['communicate']) : ?>
                            <?php $field('COM_VEL_FIELD_DEVELOPER_EMAIL_LABEL', (string) $item->developer_email); ?>
                        <?php endif; ?>
                        <?php $field('COM_VEL_FIELD_VULNERABILITY_CLASS_LABEL', (string) $item->vulnerability_class); ?>
                        <?php $field('COM_VEL_FIELD_REPORT_DESCRIPTION_LABEL', (string) $item->description, true); ?>
                        <?php $field('COM_VEL_FIELD_STEPS_LABEL', (string) $item->steps_to_reproduce, true); ?>
                        <?php $field('COM_VEL_FIELD_IMPACT_LABEL', (string) $item->impact, true); ?>
                        <?php $field('COM_VEL_FIELD_ACTIVELY_EXPLOITED_LABEL', Text::_('COM_VEL_EXPLOITED_' . strtoupper((string) $item->actively_exploited))); ?>
                        <?php $field('COM_VEL_FIELD_DISCLOSURE_URL_LABEL', (string) $item->disclosure_url); ?>
                        <?php $field('COM_VEL_FIELD_DEVELOPER_CONTACT_STATUS_LABEL', Text::_('COM_VEL_CONTACT_STATUS_' . strtoupper((string) $item->developer_contact_status))); ?>
                        <?php $field('COM_VEL_FIELD_FIXED_VERSION_LABEL', (string) $item->fixed_version); ?>
                        <?php $field('COM_VEL_FIELD_PATCH_URL_LABEL', (string) $item->patch_url); ?>
                        <?php $field('COM_VEL_FIELD_TRACKING_REFERENCE_LABEL', (string) $item->tracking_reference); ?>
                        <?php $field('COM_VEL_FIELD_ADDITIONAL_INFO_LABEL', (string) $item->additional_info, true); ?>
                    </dl>
                </div>
            </div>

            <?php if (!empty($item->attachments)) : ?>
                <div class="card mt-3">
                    <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_ATTACHMENTS'); ?></div>
                    <div class="card-body">
                        <?php if ($this->can['communicate']) : ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($item->attachments as $attachment) : ?>
                                    <li>
                                        <span class="icon-paperclip" aria-hidden="true"></span>
                                        <a href="<?php echo Route::_('index.php?option=com_vel&task=message.attachment&id=' . (int) $attachment->id . '&' . Session::getFormToken() . '=1'); ?>"><?php echo $this->escape($attachment->filename); ?></a>
                                        <span class="text-muted small">(<?php echo HTMLHelper::_('number.bytes', (int) $attachment->size); ?>, <?php echo $this->escape($attachment->mime); ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="form-text mb-0"><?php echo Text::_('COM_VEL_ATTACHMENT_WARNING'); ?></p>
                        <?php else : ?>
                            <p class="text-muted mb-0"><?php echo Text::plural('COM_VEL_N_ATTACHMENTS_HIDDEN', \count($item->attachments)); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($this->can['communicate']) : ?>
                <div class="card mt-3">
                    <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_CONVERSATION'); ?></div>
                    <div class="card-body">
                        <?php echo LayoutHelper::render('vel.thread', [
                            'advisoryId' => (int) ($item->advisory_id ?? 0),
                            'reportId'   => (int) $item->id,
                            'can'        => $this->can,
                            'returnTask' => 'report.edit',
                        ], JPATH_ADMINISTRATOR . '/components/com_vel/layouts'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_REPORTER'); ?></div>
                <div class="card-body small">
                    <?php if ($this->can['communicate']) : ?>
                        <dl class="row mb-0">
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_REPORTER_NAME_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo $this->escape($item->reporter_name); ?></dd>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_REPORTER_EMAIL_LABEL'); ?></dt>
                            <dd class="col-7 text-break"><?php echo $this->escape($item->reporter_email); ?></dd>
                            <?php if ($item->reporter_organisation) : ?>
                                <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_REPORTER_ORGANISATION_LABEL'); ?></dt>
                                <dd class="col-7"><?php echo $this->escape($item->reporter_organisation); ?></dd>
                            <?php endif; ?>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_REPORTER_ROLE_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo Text::_('COM_VEL_REPORTER_ROLE_' . strtoupper((string) $item->reporter_role)); ?></dd>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_REPORTER_USER_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo (int) $item->reporter_user_id > 0 ? '<a href="' . Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $item->reporter_user_id) . '">#' . (int) $item->reporter_user_id . '</a>' : Text::_('COM_VEL_GUEST'); ?></dd>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_REPORTER_WANTS_CREDIT_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo (int) $item->reporter_wants_credit === 1 ? Text::_('JYES') . ($item->reporter_credit_name ? ' (' . $this->escape($item->reporter_credit_name) . ')' : '') : Text::_('JNO'); ?></dd>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_SHARE_WITH_DEVELOPER_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo (int) $item->share_with_developer === 1 ? Text::_('JYES') : Text::_('JNO'); ?></dd>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_CONSENT_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo (int) $item->consent_to_process === 1 ? HTMLHelper::_('date', $item->consent_time, Text::_('DATE_FORMAT_LC2')) : '<span class="badge bg-warning text-dark">' . Text::_('COM_VEL_NO_CONSENT') . '</span>'; ?></dd>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_FIELD_VERIFIED_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo $item->verified_time ? HTMLHelper::_('date', $item->verified_time, Text::_('DATE_FORMAT_LC2')) : Text::_('COM_VEL_NOT_VERIFIED'); ?></dd>
                        </dl>
                    <?php else : ?>
                        <p class="text-muted mb-0"><?php echo Text::_('COM_VEL_REPORTER_HIDDEN_DESC'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_DECISION'); ?></div>
                <div class="card-body">
                    <?php if ($item->advisory) : ?>
                        <?php $aState = AdvisoryState::tryFrom((string) $item->advisory->state) ?? AdvisoryState::DRAFT; ?>
                        <p>
                            <?php echo Text::_('COM_VEL_ATTACHED_TO'); ?>
                            <a href="<?php echo Route::_('index.php?option=com_vel&task=advisory.edit&id=' . (int) $item->advisory->id); ?>">
                                <?php echo \sprintf('VEL-%04d', (int) $item->advisory->id); ?> <?php echo $this->escape($item->advisory->title); ?>
                            </a>
                            <span class="badge bg-<?php echo $aState->badge(); ?>"><?php echo Text::_($aState->label()); ?></span>
                        </p>
                    <?php elseif ($state->isOpen()) : ?>
                        <?php if ($this->can['create']) : ?>
                            <button type="button" class="btn btn-primary w-100 mb-3" onclick="Joomla.submitbutton('report.createadvisory')">
                                <span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_CREATE_ADVISORY'); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($this->can['edit']) : ?>
                            <fieldset class="mb-3">
                                <legend class="fs-6"><?php echo Text::_('COM_VEL_LEGEND_ATTACH'); ?></legend>
                                <?php if (!empty($item->candidates)) : ?>
                                    <select class="form-select mb-2" name="advisory_id" aria-label="<?php echo Text::_('COM_VEL_LEGEND_ATTACH'); ?>">
                                        <option value="0"><?php echo Text::_('COM_VEL_ATTACH_PICK'); ?></option>
                                        <?php foreach ($item->candidates as $candidate) : ?>
                                            <option value="<?php echo (int) $candidate->id; ?>"><?php echo \sprintf('VEL-%04d', (int) $candidate->id); ?> &middot; <?php echo $this->escape($candidate->title); ?> (<?php echo Text::_('COM_VEL_STATE_' . strtoupper((string) $candidate->state)); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <input type="text" class="form-control mb-2" name="advisory_reference" placeholder="<?php echo $this->escape(Text::_('COM_VEL_PLACEHOLDER_ADVISORY_OR_CVE')); ?>" aria-label="<?php echo $this->escape(Text::_('COM_VEL_ATTACH_REFERENCE')); ?>">
                                <button type="button" class="btn btn-outline-primary w-100" onclick="Joomla.submitbutton('report.attach')">
                                    <span class="icon-link" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_ATTACH'); ?>
                                </button>
                            </fieldset>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($this->can['state'] && $state !== ReportState::ACCEPTED) : ?>
                        <div class="d-grid gap-2">
                            <?php if ($state === ReportState::NEW) : ?>
                                <button type="button" class="btn btn-outline-secondary" onclick="Joomla.submitbutton('report.triage')"><?php echo Text::_('COM_VEL_BUTTON_TRIAGED'); ?></button>
                            <?php endif; ?>
                            <?php if ($state !== ReportState::DUPLICATE) : ?>
                                <button type="button" class="btn btn-outline-secondary" onclick="Joomla.submitbutton('report.duplicate')"><?php echo Text::_('COM_VEL_BUTTON_DUPLICATE'); ?></button>
                            <?php endif; ?>
                            <?php if ($state !== ReportState::REJECTED) : ?>
                                <button type="button" class="btn btn-outline-danger" onclick="if (confirm('<?php echo $this->escape(Text::_('COM_VEL_CONFIRM_REJECT')); ?>')) { Joomla.submitbutton('report.reject'); }"><?php echo Text::_('COM_VEL_BUTTON_REJECT'); ?></button>
                            <?php endif; ?>
                            <?php if ($state !== ReportState::SPAM) : ?>
                                <button type="button" class="btn btn-outline-dark" onclick="if (confirm('<?php echo $this->escape(Text::_('COM_VEL_CONFIRM_SPAM')); ?>')) { Joomla.submitbutton('report.spam'); }"><?php echo Text::_('COM_VEL_BUTTON_SPAM'); ?></button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_FACTS'); ?></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5"><?php echo Text::_('COM_VEL_HEADING_RECEIVED'); ?></dt>
                        <dd class="col-7"><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC2')); ?></dd>
                        <?php if ($item->modified) : ?>
                            <dt class="col-5"><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_LABEL'); ?></dt>
                            <dd class="col-7"><?php echo HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC2')); ?></dd>
                        <?php endif; ?>
                        <?php if ($item->legacy_form_id) : ?>
                            <dt class="col-5"><?php echo Text::_('COM_VEL_HEADING_LEGACY'); ?></dt>
                            <dd class="col-7"><?php echo Text::sprintf('COM_VEL_LEGACY_SUBMISSION', (int) $item->legacy_form_id, (int) $item->legacy_submission_id); ?></dd>
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
