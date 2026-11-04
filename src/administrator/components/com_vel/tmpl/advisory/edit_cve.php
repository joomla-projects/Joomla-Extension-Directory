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

use Jed\Component\Vel\Administrator\Enum\CveState;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Jed\Component\Vel\Administrator\View\Advisory\HtmlView $this */

$item     = $this->item;
$cveState = CveState::tryFrom((string) ($item->cve_state ?? '')) ?? CveState::NONE;
$token    = Session::getFormToken() . '=1';
$id       = (int) $item->id;
?>
<div class="row">
    <div class="col-lg-6">
        <h4 class="fs-6"><?php echo Text::_('COM_VEL_CVE_RECORD_HEADING'); ?></h4>
        <p class="text-muted small"><?php echo Text::_('COM_VEL_CVE_RECORD_DESC'); ?></p>

        <?php if ($this->can['cve']) : ?>
            <div class="mb-2">
                <label class="form-label" for="cve_id"><?php echo Text::_('COM_VEL_FIELD_CVE_ID_LABEL'); ?></label>
                <input type="text" class="form-control font-monospace" id="cve_id" name="cve_id" maxlength="20"
                       pattern="CVE-[0-9]{4}-[0-9]{4,}" placeholder="CVE-2026-12345"
                       value="<?php echo $this->escape((string) $item->cve_id); ?>">
            </div>
            <div class="mb-2">
                <label class="form-label" for="cve_state"><?php echo Text::_('COM_VEL_FIELD_CVE_STATE_LABEL'); ?></label>
                <select class="form-select" id="cve_state" name="cve_state">
                    <?php foreach (CveState::cases() as $option) : ?>
                        <option value="<?php echo $option->value; ?>" <?php echo $option === $cveState ? 'selected' : ''; ?>><?php echo Text::_($option->label()); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label" for="cve_assigner"><?php echo Text::_('COM_VEL_FIELD_CVE_ASSIGNER_LABEL'); ?></label>
                <input type="text" class="form-control" id="cve_assigner" name="cve_assigner" maxlength="64"
                       value="<?php echo $this->escape((string) $item->cve_assigner); ?>">
                <div class="form-text"><?php echo Text::_('COM_VEL_FIELD_CVE_ASSIGNER_DESC'); ?></div>
            </div>
            <div class="mb-2">
                <label class="form-label" for="date_reserved"><?php echo Text::_('COM_VEL_FIELD_DATE_RESERVED_LABEL'); ?></label>
                <input type="text" class="form-control" id="date_reserved" name="date_reserved" placeholder="YYYY-MM-DD HH:MM:SS"
                       value="<?php echo $this->escape((string) ($item->date_reserved ?? '')); ?>">
                <div class="form-text"><?php echo Text::_('COM_VEL_FIELD_DATE_RESERVED_DESC'); ?></div>
            </div>
            <button type="button" class="btn btn-outline-primary" onclick="Joomla.submitbutton('advisory.setcve')">
                <span class="icon-save" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_SAVE_CVE'); ?>
            </button>
        <?php else : ?>
            <dl class="row small">
                <dt class="col-4"><?php echo Text::_('COM_VEL_FIELD_CVE_ID_LABEL'); ?></dt>
                <dd class="col-8 font-monospace"><?php echo $item->cve_id ? $this->escape($item->cve_id) : '&mdash;'; ?></dd>
                <dt class="col-4"><?php echo Text::_('COM_VEL_FIELD_CVE_STATE_LABEL'); ?></dt>
                <dd class="col-8"><?php echo Text::_($cveState->label()); ?></dd>
                <dt class="col-4"><?php echo Text::_('COM_VEL_FIELD_CVE_ASSIGNER_LABEL'); ?></dt>
                <dd class="col-8"><?php echo $this->escape((string) $item->cve_assigner) ?: '&mdash;'; ?></dd>
            </dl>
        <?php endif; ?>
    </div>

    <div class="col-lg-6">
        <h4 class="fs-6"><?php echo Text::_('COM_VEL_CVE_JSON_HEADING'); ?></h4>
        <p class="text-muted small"><?php echo Text::_('COM_VEL_CVE_JSON_DESC'); ?></p>

        <?php if ($this->can['cve']) : ?>
            <div class="d-grid gap-2">
                <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_vel&task=cve.preview&id=' . $id . '&' . $token); ?>">
                    <span class="icon-eye" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_CVE_PREVIEW'); ?>
                </a>
                <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_vel&task=cve.generate&id=' . $id . '&shape=record&' . $token); ?>">
                    <span class="icon-download" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_CVE_DOWNLOAD_RECORD'); ?>
                </a>
                <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_vel&task=cve.generate&id=' . $id . '&shape=cna&' . $token); ?>">
                    <span class="icon-download" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_CVE_DOWNLOAD_CNA'); ?>
                </a>
            </div>

            <hr>
            <h4 class="fs-6"><?php echo Text::_('COM_VEL_CVE_SERVICES_HEADING'); ?></h4>
            <p class="text-muted small"><?php echo Text::_('COM_VEL_CVE_SERVICES_DESC'); ?></p>
            <div class="d-grid gap-2">
                <?php if ($cveState === CveState::NONE) : ?>
                    <button type="button" class="btn btn-outline-primary"
                            onclick="if (confirm('<?php echo $this->escape(Text::_('COM_VEL_CONFIRM_RESERVE')); ?>')) { Joomla.submitbutton('cve.reserve'); }">
                        <span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_CVE_RESERVE'); ?>
                    </button>
                <?php endif; ?>
                <?php if ($cveState !== CveState::NONE && $this->state->isPublic()) : ?>
                    <button type="button" class="btn btn-outline-primary"
                            onclick="if (confirm('<?php echo $this->escape(Text::_('COM_VEL_CONFIRM_SUBMIT')); ?>')) { Joomla.submitbutton('cve.submit'); }">
                        <span class="icon-upload" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_CVE_SUBMIT'); ?>
                    </button>
                <?php endif; ?>
                <?php if ($cveState !== CveState::NONE) : ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="Joomla.submitbutton('cve.fetch')">
                        <span class="icon-refresh" aria-hidden="true"></span> <?php echo Text::_('COM_VEL_BUTTON_CVE_FETCH'); ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($item->published_json)) : ?>
            <hr>
            <h4 class="fs-6"><?php echo Text::_('COM_VEL_CVE_LAST_JSON'); ?></h4>
            <pre class="small bg-light p-2 border" style="max-height: 20rem; overflow: auto;"><?php echo $this->escape((string) $item->published_json); ?></pre>
        <?php endif; ?>
    </div>
</div>
