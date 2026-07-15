<?php

/** @var \Jed\Component\Jed\Site\View\Newextension\HtmlView $this */
/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Step 1 of the "create a new extension" wizard: pick a source. The upload/git blocks detect the
 * extension's data via AJAX (see media/com_jed/js/newextension.js) and reveal a "Continue" button
 * once detection succeeds; "Manual" skips detection entirely.
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$wa = $this->document->getWebAssetManager();
$wa->useScript('com_jed.newextension')
    ->useStyle('com_jed.newextension');

HTMLHelper::_('bootstrap.tooltip');

$formUrl   = Route::_('index.php?option=com_jed&view=newextension&layout=form');
$manualUrl = Route::_('index.php?option=com_jed&task=newextension.manual&' . Session::getFormToken() . '=1');
?>
<div class="newextension-picker">
    <h1><?php echo Text::_('COM_JED_NEWEXTENSION_PAGE_TITLE'); ?></h1>
    <p class="newextension-intro"><?php echo Text::_('COM_JED_NEWEXTENSION_INTRO'); ?></p>

    <div class="row newextension-blocks">
        <div class="col-12 col-lg-6">
            <div class="card newextension-block" id="newextension-upload-block">
                <div class="card-body">
                    <h2 class="card-title"><?php echo Text::_('COM_JED_NEWEXTENSION_UPLOAD_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JED_NEWEXTENSION_UPLOAD_EXPLANATION'); ?></p>

                    <div class="newextension-dropzone jed-upload-dropzone" id="newextension-dropzone" tabindex="0"
                         role="button"
                         aria-label="<?php echo $this->escape(Text::_('COM_JED_NEWEXTENSION_UPLOAD_DROPZONE_LABEL')); ?>">
                        <span class="icon-upload" aria-hidden="true"></span>
                        <p class="newextension-dropzone-text">
                            <?php echo Text::_('COM_JED_NEWEXTENSION_UPLOAD_DROPZONE_LABEL'); ?>
                        </p>
                        <input type="file" id="newextension-file-input" class="visually-hidden" accept=".zip"/>
                    </div>

                    <p class="newextension-selected-filename d-none" id="newextension-selected-filename"></p>

                    <button type="button" class="btn btn-primary d-none" id="newextension-upload-read">
                        <?php echo Text::_('COM_JED_NEWEXTENSION_READ_FROM_FILE'); ?>
                    </button>

                    <div class="newextension-result" id="newextension-upload-result" aria-live="polite"></div>

                    <a href="<?php echo $formUrl; ?>" class="btn btn-success d-none" id="newextension-upload-continue">
                        <?php echo Text::_('COM_JED_NEWEXTENSION_CONTINUE'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card newextension-block" id="newextension-git-block">
                <div class="card-body">
                    <h2 class="card-title"><?php echo Text::_('COM_JED_NEWEXTENSION_GIT_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JED_NEWEXTENSION_GIT_EXPLANATION'); ?></p>

                    <div class="input-group">
                        <input type="url" class="form-control" id="newextension-git-url"
                               placeholder="https://github.com/owner/repository"/>
                        <button type="button" class="btn btn-primary" id="newextension-git-read">
                            <?php echo Text::_('COM_JED_NEWEXTENSION_READ_FROM_GIT'); ?>
                        </button>
                    </div>

                    <div class="newextension-result" id="newextension-git-result" aria-live="polite"></div>

                    <a href="<?php echo $formUrl; ?>" class="btn btn-success d-none" id="newextension-git-continue">
                        <?php echo Text::_('COM_JED_NEWEXTENSION_CONTINUE'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="newextension-manual">
        <p><?php echo Text::_('COM_JED_NEWEXTENSION_MANUAL_EXPLANATION'); ?></p>
        <a href="<?php echo $manualUrl; ?>" class="btn btn-secondary">
            <?php echo Text::_('COM_JED_NEWEXTENSION_MANUAL'); ?>
        </a>
    </div>
</div>

<div id="newextension-i18n" class="d-none"
     data-ajax-url="<?php echo Route::_('index.php?option=com_jed&format=raw'); ?>"
     data-csrf-token="<?php echo Session::getFormToken(); ?>"
     data-msg-uploading="<?php echo $this->escape(Text::_('COM_JED_NEWEXTENSION_STATUS_UPLOADING')); ?>"
     data-msg-reading-git="<?php echo $this->escape(Text::_('COM_JED_NEWEXTENSION_STATUS_READING_GIT')); ?>"
     data-msg-error="<?php echo $this->escape(Text::_('COM_JED_NEWEXTENSION_STATUS_ERROR')); ?>"
     data-msg-git-url-required="<?php echo $this->escape(Text::_('COM_JED_NEWEXTENSION_GIT_URL_REQUIRED')); ?>"
     data-label-name="<?php echo $this->escape(Text::_('COM_JED_GENERAL_TITLE_LABEL')); ?>"
     data-label-developer-url="<?php echo $this->escape(Text::_('COM_JED_EXTENSION_DEVELOPER_URL_LABEL')); ?>"
     data-label-developer-email="<?php echo $this->escape(Text::_('COM_JED_EXTENSION_DEVELOPER_EMAIL_LABEL')); ?>"
     data-label-update-url="<?php echo $this->escape(Text::_('COM_JED_FORM_LBL_EXTENSIONVARIEDDATUM_UPDATE_URL')); ?>"
     data-label-changelog-url="<?php echo $this->escape(Text::_('COM_JED_EXTENSION_CHANGELOG_URL_LABEL')); ?>"
     data-label-extension-types="<?php echo $this->escape(Text::_('COM_JED_EXTENSION_EXTENSION_TYPE_LABEL')); ?>"
></div>
