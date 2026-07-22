<?php

/** @var \Jed\Component\Jed\Administrator\View\Extension\HtmlView $this */
/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\View\Extension\HtmlView;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Renders the "existing items + drag & drop upload" area used for the images and files
 * subforms. Existing rows are shown as read-only cards with a delete checkbox; the dropzone
 * lets the user pick/drop new files, which only get attached to a hidden subform row - the
 * actual upload happens when the surrounding admin form is submitted.
 *
 * @since 4.0.0
 */
if (!function_exists('jedRenderExtensionUploadArea')) {
    function jedRenderExtensionUploadArea(
        HtmlView $view,
        string $title,
        array $items,
        string $type,
        string $subformName,
        string $dropzoneId,
        string $deleteFieldName
    ): void {
        $isImage = $type === 'image';
        ?>
        <div class="jed-upload-area" data-subform="<?php echo $subformName; ?>" data-upload-type="<?php echo $type; ?>"
             data-file-selector=".jed-upload-input" data-accept="<?php echo $isImage ? 'image/*' : ''; ?>"
             data-remove-label="<?php echo Text::_('JGLOBAL_FIELD_REMOVE'); ?>">
            <h3><?php echo $title; ?></h3>
            <div class="jed-upload-gallery">
                <?php foreach ($items as $item) : ?>
                    <div class="jed-upload-card" data-existing-id="<?php echo (int) $item->id; ?>">
                        <?php if ($isImage) : ?>
                            <img src="<?php echo $view->escape(JedHelper::formatImage((string) $item->filename, ImageSize::SMALL)); ?>" alt="" class="jed-upload-thumb" loading="lazy">
                        <?php else : ?>
                            <span class="icon-file-alt jed-upload-file-icon" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="jed-upload-filename">
                            <?php echo $view->escape($isImage ? $item->filename : ($item->originalFile ?: $item->file)); ?>
                        </span>
                        <label class="jed-upload-delete">
                            <input type="checkbox" name="<?php echo $deleteFieldName; ?>[]" value="<?php echo (int) $item->id; ?>">
                            <?php echo Text::_('JACTION_DELETE'); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="jed-upload-dropzone" id="<?php echo $dropzoneId; ?>" tabindex="0" role="button"
                 aria-label="<?php echo Text::_('COM_JED_EXTENSION_UPLOAD_DROPZONE_LABEL'); ?>">
                <span class="icon-cloud-upload jed-upload-dropzone-icon" aria-hidden="true"></span>
                <p class="mb-0"><?php echo Text::_('COM_JED_EXTENSION_UPLOAD_DROPZONE_TEXT'); ?></p>
            </div>
        </div>
        <?php
    }
}

$fieldhiddenoptions = ['hidden' => true];
/**
 * $model->setUseExceptions(true)
*/

HTMLHelper::_('script', 'com_jed/jed.js', ['version' => 'auto', 'relative' => true]);

try {
    Factory::getApplication()->getDocument()->getWebAssetManager()
        ->useScript('form.validate')
        ->useScript('keepalive')
        ->usePreset('choicesjs')
        ->useScript('webcomponent.field-fancy-select')
        ->useScript('webcomponent.field-subform')
        ->useScript('com_jed.extensionUploadAreas')
        ->useStyle('com_tickets.Tickets')
        ->useStyle('com_jed.jquery_dataTables');
} catch (Exception) {
}

Text::script('COM_JED_EXTENSION_ERROR_DURING_SEND_EMAIL_LABEL', true);
Text::script('COM_JED_EXTENSION_MISSING_MESSAGE_ID_LABEL', true);
Text::script('COM_JED_EXTENSION_MISSING_DEVELOPER_ID', true);
Text::script('COM_JED_EXTENSION_MISSING_EXTENSION_ID_LABEL', true);
Text::script('COM_JED_EXTENSION_ERROR_SAVING_APPROVE_LABEL', true);
Text::script('COM_JED_EXTENSION_EXTENSION_APPROVED_REASON_REQUIRED_LABEL', true);
Text::script('COM_JED_EXTENSION_ERROR_SAVING_PUBLISH_LABEL', true);
Text::script('COM_JED_EXTENSION_EXTENSION_PUBLISHED_REASON_REQUIRED_LABEL', true);

$extensionUrl = Uri::root() . 'extension/' . $this->item->alias;
$downloadUrl  = 'index.php?option=com_jed&task=extension.download&id=' . $this->item->id;

// joomla.edit.title_alias expects ->title; the item only carries ->name (the #__jed_extensions_history column).
$this->item->title = $this->item->name ?? '';

$this->getDocument()
    ->addScriptOptions('joomla.userId', $this->getCurrentUser()->id, false);

?>
<style>
    .jed-upload-area { padding: .25rem; }
    .jed-upload-gallery { display: flex; flex-wrap: wrap; gap: 1rem; padding: .25rem 0; }
    .jed-upload-card {
        position: relative;
        flex: 0 0 160px;
        max-width: 160px;
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: .375rem;
        padding: .5rem;
        text-align: center;
    }
    .jed-upload-card.jed-upload-card-new { border-style: dashed; border-color: var(--bs-primary, #2a69b8); }
    .jed-upload-thumb { width: 100%; height: 100px; object-fit: cover; border-radius: .25rem; display: block; }
    .jed-upload-file-icon { display: block; font-size: 2.5rem; margin: 1rem 0; }
    .jed-upload-filename { display: block; font-size: .75rem; word-break: break-word; margin-top: .25rem; }
    .jed-upload-delete { display: block; font-size: .75rem; margin-top: .25rem; cursor: pointer; }
    .jed-upload-remove {
        position: absolute; top: -.5rem; right: -.5rem;
        width: 1.5rem; height: 1.5rem; line-height: 1;
        border: none; border-radius: 50%;
        background: var(--bs-danger, #dc3545); color: #fff;
    }
    .jed-upload-dropzone {
        border: 2px dashed var(--bs-border-color, #adb5bd);
        border-radius: .5rem;
        padding: 2rem 1rem;
        text-align: center;
        cursor: pointer;
        color: var(--bs-secondary-color, #6c757d);
    }
    .jed-upload-dropzone.jed-upload-dropzone-active {
        border-color: var(--bs-primary, #2a69b8);
        background: var(--bs-tertiary-bg, rgba(42, 105, 184, .05));
    }
    .jed-upload-dropzone-icon { display: block; font-size: 2rem; margin-bottom: .5rem; }
    .jed-upload-subform-hidden { display: none; }
</style>

<form action="index.php?option=com_jed&view=extension&layout=edit&id=<?php echo (int) ($this->item->extension_id ?: $this->item->id); ?>" method="post" name="adminForm" id="extension-form" class="form-validate">

    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php
        echo HTMLHelper::_('uitab.startTabSet', 'extensionTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]);

        foreach ($this->form->getFieldsets() as $fieldset) :
            echo HTMLHelper::_('uitab.addTab', 'extensionTab', $fieldset->name, Text::_($fieldset->label));
            ?>
                <div class="row">
                    <div class="col-12 col-lg-6">
                        <?php
                        if ($fieldset->name === 'media' || $fieldset->name === 'files') {
                            // Render the technical subform markup (needed for correct field names/ids and the
                            // joomla-field-subform web component), but keep it out of view: the custom gallery
                            // below is the actual user-facing UI for images/files.
                            echo '<div class="jed-upload-subform-hidden">';
                            echo $this->form->renderFieldset($fieldset->name);
                            echo '</div>';
                        } else {
                            echo $this->form->renderFieldset($fieldset->name);
                        }
                        ?>
                    </div>
                </div>
                <?php if ($fieldset->name === 'media') : ?>
                    <div class="row">
                        <div class="col-12">
                            <?php
                            jedRenderExtensionUploadArea(
                                $this,
                                Text::_('COM_JED_EXTENSION_IMAGES_LABEL'),
                                $this->images,
                                'image',
                                'jform[images]',
                                'jed-images-dropzone',
                                'jform[deleteImages]'
                            );
                            ?>
                        </div>
                    </div>
                <?php elseif ($fieldset->name === 'files') : ?>
                    <div class="row">
                        <div class="col-12">
                            <?php
                            jedRenderExtensionUploadArea(
                                $this,
                                Text::_('COM_JED_EXTENSION_FILES_LABEL'),
                                $this->files,
                                'file',
                                'jform[files]',
                                'jed-files-dropzone',
                                'jform[deleteFiles]'
                            );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php
                echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endforeach; ?>

        <?php
        echo HTMLHelper::_('uitab.addTab', 'extensionTab', 'viewextensionreviews', Text::_('COM_JED_EXTENSION_REVIEWS_TAB_LABEL', true));
        ?>

            <div class="container">
                <div class="row">
                    <?php if (empty($this->reviews)) : ?>
                        <div class="col-12">
                            <p><?php echo Text::_('COM_JED_EXTENSION_NO_REVIEWS'); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="col-12">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                                        <th><?php echo Text::_('JGLOBAL_FIELD_CREATED_BY_LABEL'); ?></th>
                                        <th><?php echo Text::_('COM_JED_REVIEWS_OVERALL_SCORE_LABEL'); ?></th>
                                        <th><?php echo Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'); ?></th>
                                        <th><?php echo Text::_('JSTATUS'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($this->reviews as $review) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo Route::_('index.php?option=com_jed&task=review.edit&id=' . (int) $review->id); ?>">
                                                <?php echo htmlspecialchars($review->title !== '' ? $review->title : '#' . $review->id, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars(JedHelper::getUserById($review->created_by)->name, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo number_format((float) $review->overall_score, 1); ?> / 5</td>
                                        <td><?php echo JedHelper::prettyDate($review->created_on); ?></td>
                                        <td><?php echo (int) $review->published === 1 ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="option" value="com_jed"/>
    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
