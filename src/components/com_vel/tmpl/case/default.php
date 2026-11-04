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

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\ParticipantRole;
use Jed\Component\Vel\Administrator\Version\VersionRange;
use Jed\Component\Vel\Site\Helper\VelHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Site\View\Case\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_vel.site', 'com_vel/css/vel.css')->useScript('form.validate');

$advisory = $this->advisory;
$state    = $advisory ? (AdvisoryState::tryFrom((string) $advisory->state) ?? AdvisoryState::DRAFT) : null;
$status   = $advisory ? (AdvisoryStatus::tryFrom((string) $advisory->status) ?? AdvisoryStatus::LIVE) : null;
?>
<div class="com-vel-case">
    <div class="page-header">
        <h1><?php echo Text::sprintf('COM_VEL_CASE_HEADING', $this->escape($this->reference)); ?></h1>
        <p class="text-muted">
            <?php echo Text::sprintf('COM_VEL_CASE_YOU_ARE', $this->escape($this->participant->name ?: $this->participant->email), Text::_($this->role->label())); ?>
            &middot; <?php echo Text::_('COM_VEL_CASE_PRIVATE_LINK'); ?>
        </p>
    </div>

    <?php if ($advisory !== null) : ?>
        <div class="card mb-3">
            <div class="card-header">
                <?php echo Text::_($state->isPublic() ? 'COM_VEL_CASE_ADVISORY_PUBLISHED' : 'COM_VEL_CASE_ADVISORY_DRAFT'); ?>
                <span class="badge bg-<?php echo $state->badge(); ?>"><?php echo Text::_($state->label()); ?></span>
                <span class="badge bg-<?php echo $status->badge(); ?>"><?php echo Text::_($status->label()); ?></span>
            </div>
            <div class="card-body">
                <h2 class="h5"><?php echo $this->escape($advisory->title); ?></h2>
                <?php if ($state->isPublic()) : ?>
                    <p><a href="<?php echo VelHelper::route($advisory); ?>"><?php echo Text::_('COM_VEL_CASE_VIEW_ADVISORY'); ?></a></p>
                <?php else : ?>
                    <div class="small">
                        <?php echo HTMLHelper::_('content.prepare', (string) $advisory->description, '', 'com_vel.case'); ?>
                    </div>
                    <table class="table table-sm small">
                        <caption class="visually-hidden"><?php echo Text::_('COM_VEL_HEADING_AFFECTED'); ?></caption>
                        <thead><tr><th scope="col"><?php echo Text::_('COM_VEL_HEADING_PRODUCT'); ?></th><th scope="col"><?php echo Text::_('COM_VEL_HEADING_AFFECTED_VERSIONS'); ?></th><th scope="col"><?php echo Text::_('COM_VEL_HEADING_FIXED_IN'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ((array) $advisory->products as $product) : ?>
                            <tr>
                                <td><?php echo $this->escape($product->product); ?></td>
                                <td><?php foreach ((array) $product->versions as $version) : ?><div class="font-monospace"><?php echo $this->escape((string) VersionRange::fromStrings((string) $version->version_from, (string) $version->version_to)); ?></div><?php endforeach; ?></td>
                                <td><?php echo $product->fixed_version ? $this->escape($product->fixed_version) : '<span class="text-muted">' . Text::_('COM_VEL_NO_FIX') . '</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!empty($advisory->date_public)) : ?>
                        <p class="small mb-0"><?php echo Text::sprintf('COM_VEL_CASE_DISCLOSURE_DATE', HTMLHelper::_('date', $advisory->date_public, Text::_('DATE_FORMAT_LC3'))); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="h4"><?php echo Text::_('COM_VEL_HEADING_CONVERSATION'); ?></h2>

    <?php if ($this->thread === []) : ?>
        <p class="text-muted"><?php echo Text::_('COM_VEL_NO_MESSAGES'); ?></p>
    <?php else : ?>
        <?php foreach ($this->thread as $message) : ?>
            <?php
            $mine   = $message->participant && (int) $message->participant->id === (int) $this->participant->id;
            $author = $message->participant ? ($mine ? Text::_('COM_VEL_CASE_YOU') : $this->escape($message->participant->name ?: Text::_((ParticipantRole::tryFrom((string) $message->participant->role) ?? ParticipantRole::OTHER)->label()))) : Text::_('COM_VEL_PARTICIPANT_ROLE_TEAM');
            ?>
            <div class="com-vel-message <?php echo $mine ? 'is-mine' : ($message->participant ? '' : 'is-team'); ?>">
                <div class="meta"><strong><?php echo $author; ?></strong> &middot; <?php echo HTMLHelper::_('date', $message->created, Text::_('DATE_FORMAT_LC2')); ?></div>
                <?php if ($message->subject) : ?><div class="fw-semibold"><?php echo $this->escape($message->subject); ?></div><?php endif; ?>
                <div style="white-space: pre-wrap;"><?php echo $this->escape($message->body); ?></div>
                <?php if (!empty($message->attachments)) : ?>
                    <ul class="list-unstyled small mt-1 mb-0">
                        <?php foreach ($message->attachments as $attachment) : ?>
                            <li>
                                <a href="<?php echo Route::_('index.php?option=com_vel&task=case.attachment&token=' . $this->escape($this->token) . '&id=' . (int) $attachment->id); ?>"><?php echo $this->escape($attachment->filename); ?></a>
                                <span class="text-muted">(<?php echo HTMLHelper::_('number.bytes', (int) $attachment->size); ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?option=com_vel&task=case.reply&token=' . $this->escape($this->token)); ?>" method="post" class="form-validate card card-body mt-3" enctype="multipart/form-data">
        <h3 class="h6"><?php echo Text::_('COM_VEL_LEGEND_REPLY'); ?></h3>
        <?php echo $this->form->renderField('body'); ?>
        <?php echo $this->form->renderField('attachments'); ?>
        <div>
            <button type="submit" class="btn btn-primary validate"><?php echo Text::_('COM_VEL_BUTTON_SEND'); ?></button>
        </div>
        <input type="hidden" name="token" value="<?php echo $this->escape($this->token); ?>">
        <input type="hidden" name="task" value="case.reply">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
