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

use Jed\Component\Vel\Administrator\Enum\MessageVisibility;
use Jed\Component\Vel\Administrator\Enum\ParticipantRole;
use Jed\Component\Vel\Administrator\Service\MessageService;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

/**
 * The case thread, rendered inside the advisory or report edit form (VEL2-PRD §6.4.4).
 *
 * Expects: advisoryId (int), reportId (int), can (array), returnTask (string).
 */
$advisoryId = (int) ($displayData['advisoryId'] ?? 0);
$reportId   = (int) ($displayData['reportId'] ?? 0);
$can        = (array) ($displayData['can'] ?? []);

$service      = new MessageService(Factory::getContainer()->get(DatabaseInterface::class));
$participants = $service->participants($advisoryId ?: null, $reportId ?: null);
$messages     = $service->thread($advisoryId ?: null, $reportId ?: null, ParticipantRole::TEAM);
$escape       = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$hasVendor    = false;

foreach ($participants as $participant) {
    if ((string) $participant->role === ParticipantRole::VENDOR->value) {
        $hasVendor = true;
    }
}
?>
<div class="com-vel-thread">
    <div class="mb-3">
        <strong><?php echo Text::_('COM_VEL_HEADING_PARTICIPANTS'); ?>:</strong>
        <?php if ($participants === []) : ?>
            <span class="text-muted"><?php echo Text::_('JNONE'); ?></span>
        <?php else : ?>
            <?php foreach ($participants as $participant) : ?>
                <?php $role = ParticipantRole::tryFrom((string) $participant->role) ?? ParticipantRole::OTHER; ?>
                <span class="badge bg-light text-dark border">
                    <?php echo $escape($participant->name ?: $participant->email); ?>
                    <span class="text-muted">&lt;<?php echo $escape($participant->email); ?>&gt;</span>
                    &middot; <?php echo Text::_($role->label()); ?>
                    <?php if ((int) $participant->notify !== 1) : ?>&middot; <?php echo Text::_('COM_VEL_PARTICIPANT_MUTED'); ?><?php endif; ?>
                </span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($messages === []) : ?>
        <p class="text-muted"><?php echo Text::_('COM_VEL_NO_MESSAGES'); ?></p>
    <?php else : ?>
        <?php foreach ($messages as $message) : ?>
            <?php
            $visibility = MessageVisibility::tryFrom((string) $message->visibility) ?? MessageVisibility::TEAM;
            $author     = $message->participant
                ? $escape($message->participant->name ?: $message->participant->email) . ' (' . Text::_((ParticipantRole::tryFrom((string) $message->participant->role) ?? ParticipantRole::OTHER)->label()) . ')'
                : Text::_('COM_VEL_PARTICIPANT_ROLE_TEAM');
            ?>
            <div class="com-vel-message dir-<?php echo $escape($message->direction); ?>">
                <div class="meta">
                    <strong><?php echo $author; ?></strong>
                    &middot; <?php echo HTMLHelper::_('date', $message->created, Text::_('DATE_FORMAT_LC2')); ?>
                    &middot; <span class="badge bg-light text-dark"><?php echo Text::_('COM_VEL_CHANNEL_' . strtoupper((string) $message->channel)); ?></span>
                    <span class="badge bg-<?php echo $visibility === MessageVisibility::TEAM ? 'secondary' : 'info'; ?>"><?php echo Text::_($visibility->label()); ?></span>
                </div>
                <?php if ($message->subject) : ?>
                    <div class="fw-semibold"><?php echo $escape($message->subject); ?></div>
                <?php endif; ?>
                <div class="body"><?php echo $escape($message->body); ?></div>
                <?php if (!empty($message->attachments)) : ?>
                    <ul class="list-unstyled small mt-1 mb-0">
                        <?php foreach ($message->attachments as $attachment) : ?>
                            <li>
                                <span class="icon-paperclip" aria-hidden="true"></span>
                                <a href="<?php echo Route::_('index.php?option=com_vel&task=message.attachment&id=' . (int) $attachment->id . '&' . Session::getFormToken() . '=1'); ?>"><?php echo $escape($attachment->filename); ?></a>
                                <span class="text-muted">(<?php echo HTMLHelper::_('number.bytes', (int) $attachment->size); ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($can['communicate'])) : ?>
        <hr>
        <fieldset class="mb-3">
            <legend class="fs-6"><?php echo Text::_('COM_VEL_LEGEND_REPLY'); ?></legend>
            <input type="hidden" name="advisory_id" value="<?php echo $advisoryId; ?>">
            <input type="hidden" name="report_id" value="<?php echo $reportId; ?>">

            <div class="row g-2">
                <div class="col-md-8">
                    <label class="form-label" for="vel-reply-subject"><?php echo Text::_('COM_VEL_FIELD_MESSAGE_SUBJECT_LABEL'); ?></label>
                    <input type="text" class="form-control" id="vel-reply-subject" name="subject" maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="vel-reply-visibility"><?php echo Text::_('COM_VEL_FIELD_MESSAGE_VISIBILITY_LABEL'); ?></label>
                    <select class="form-select" id="vel-reply-visibility" name="visibility">
                        <?php foreach (MessageVisibility::cases() as $option) : ?>
                            <option value="<?php echo $option->value; ?>"><?php echo Text::_($option->label()); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label class="form-label mt-2" for="vel-reply-body"><?php echo Text::_('COM_VEL_FIELD_MESSAGE_BODY_LABEL'); ?></label>
            <textarea class="form-control" id="vel-reply-body" name="body" rows="5"></textarea>
            <label class="form-label mt-2" for="vel-reply-attachments"><?php echo Text::_('COM_VEL_FIELD_MESSAGE_ATTACHMENTS_LABEL'); ?></label>
            <input type="file" class="form-control" id="vel-reply-attachments" name="attachments[]" multiple>
            <div class="form-text"><?php echo Text::_('COM_VEL_FIELD_MESSAGE_VISIBILITY_DESC'); ?></div>
            <button type="button" class="btn btn-primary mt-2" onclick="Joomla.submitbutton('message.reply')">
                <span class="icon-envelope" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_SEND'); ?>
            </button>
        </fieldset>

        <?php if ($advisoryId > 0) : ?>
            <fieldset>
                <legend class="fs-6"><?php echo Text::_('COM_VEL_LEGEND_NOTIFY_VENDOR'); ?></legend>
                <p class="form-text"><?php echo Text::_($hasVendor ? 'COM_VEL_NOTIFY_VENDOR_AGAIN_DESC' : 'COM_VEL_NOTIFY_VENDOR_DESC'); ?></p>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label" for="vel-vendor-email"><?php echo Text::_('COM_VEL_FIELD_VENDOR_EMAIL_LABEL'); ?></label>
                        <input type="email" class="form-control" id="vel-vendor-email" name="vendor_email" placeholder="security@vendor.example">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vel-vendor-name"><?php echo Text::_('COM_VEL_FIELD_VENDOR_NAME_LABEL'); ?></label>
                        <input type="text" class="form-control" id="vel-vendor-name" name="vendor_name">
                    </div>
                </div>
                <label class="form-label mt-2" for="vel-vendor-note"><?php echo Text::_('COM_VEL_FIELD_VENDOR_NOTE_LABEL'); ?></label>
                <textarea class="form-control" id="vel-vendor-note" name="vendor_note" rows="3"></textarea>
                <button type="button" class="btn btn-outline-primary mt-2"
                        onclick="if (confirm('<?php echo $escape(Text::_('COM_VEL_CONFIRM_NOTIFY_VENDOR')); ?>')) { Joomla.submitbutton('message.notifyvendor'); }">
                    <span class="icon-envelope" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_NOTIFY_VENDOR'); ?>
                </button>
            </fieldset>
        <?php endif; ?>
    <?php endif; ?>
</div>
