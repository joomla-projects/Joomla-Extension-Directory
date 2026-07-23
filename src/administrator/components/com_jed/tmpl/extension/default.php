<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Read-only view of an extension: the same data as tmpl/extension/edit.php, grouped into the
 * same tabs/fieldsets, but rendered as plain labels/values instead of form fields.
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Jed\Component\Jed\Administrator\View\Extension\HtmlView $this */

// Field types/names that should not get a plain label/value row (subforms have their own
// dedicated read-only sections below; these others carry no meaningful display value).
$jedSkipFieldsetTypes  = ['subform', 'hidden', 'spacer'];
// "categories" is rendered separately further down (from $this->categories, with resolved
// titles); the field's own item value is just a list of catids and would otherwise duplicate it.
$jedSkipFieldsetFields = ['id', 'checked_out', 'checked_out_time', 'categories'];

?>
<style>
    .jed-view-row { padding: .4rem 0; border-bottom: 1px solid var(--bs-border-color, #eee); }
    .jed-view-row dt { font-weight: 600; }
    .jed-view-row dd { margin-bottom: 0; }
    .jed-view-html { max-width: 60rem; }
    .jed-view-image { max-width: 240px; max-height: 240px; object-fit: contain; display: block; }
    .jed-view-gallery { display: flex; flex-wrap: wrap; gap: 1rem; }
    .jed-view-card {
        flex: 0 0 160px; max-width: 160px;
        border: 1px solid var(--bs-border-color, #dee2e6); border-radius: .375rem;
        padding: .5rem; text-align: center;
    }
    .jed-view-thumb { width: 100%; height: 100px; object-fit: cover; border-radius: .25rem; display: block; }
    .jed-view-filename { display: block; font-size: .75rem; word-break: break-word; margin-top: .25rem; }
</style>

<div class="main-card">
    <h2><?php echo $this->escape($this->item->name ?? ''); ?></h2>

    <?php
    $fieldsets          = $this->form->getFieldsets();
    $firstFieldsetName  = reset($fieldsets) ? reset($fieldsets)->name : '';

    echo HTMLHelper::_('uitab.startTabSet', 'extensionViewTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]);

    foreach ($fieldsets as $fieldset) :
        echo HTMLHelper::_('uitab.addTab', 'extensionViewTab', $fieldset->name, Text::_($fieldset->label));
        ?>
        <div class="row">
            <div class="col-12 col-lg-8">
                <?php foreach ($this->form->getFieldset($fieldset->name) as $field) :
                    if (
                        in_array(strtolower((string) $field->type), $jedSkipFieldsetTypes, true)
                        || in_array($field->fieldname, $jedSkipFieldsetFields, true)
                    ) {
                        continue;
                    }

                    $fieldValue = $this->item->{$field->fieldname} ?? null;
                    ?>
                    <div class="row jed-view-row">
                        <dt class="col-sm-4 col-lg-3"><?php echo $field->label; ?></dt>
                        <dd class="col-sm-8 col-lg-9"><?php echo JedHelper::displayFieldValue($field->fieldname, $fieldValue); ?></dd>
                    </div>
                <?php endforeach; ?>

                <?php if ($fieldset->name === 'media') : ?>
                    <div class="jed-view-gallery mt-3">
                        <?php foreach ($this->images as $image) : ?>
                            <div class="jed-view-card">
                                <img src="<?php echo $this->escape(JedHelper::formatImage((string) $image->filename, ImageSize::SMALL)); ?>" alt="" class="jed-view-thumb" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($this->images)) : ?>
                            <p class="text-muted"><?php echo Text::_('COM_JED_EXTENSION_NO_IMAGES'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($fieldset->name === 'files') : ?>
                    <ul class="list-unstyled mt-3">
                        <?php foreach ($this->files as $file) : ?>
                            <li><span class="icon-file-alt" aria-hidden="true"></span>
                                <?php echo $this->escape($file->originalFile ?: $file->file); ?></li>
                        <?php endforeach; ?>
                        <?php if (empty($this->files)) : ?>
                            <li class="text-muted">&#8212;</li>
                        <?php endif; ?>
                    </ul>
                <?php elseif ($fieldset->name === 'maintainers') : ?>
                    <ul class="list-unstyled mt-3">
                        <?php foreach ($this->maintainers as $maintainer) : ?>
                            <li><?php echo $this->escape($maintainer->name ?: $maintainer->username); ?></li>
                        <?php endforeach; ?>
                        <?php if (empty($this->maintainers)) : ?>
                            <li class="text-muted">&#8212;</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($fieldset->name === $firstFieldsetName && !empty($this->categories)) : ?>
                    <div class="row jed-view-row">
                        <dt class="col-sm-4 col-lg-3"><?php echo Text::_('COM_JED_EXTENSION_CATEGORIES_LABEL'); ?></dt>
                        <dd class="col-sm-8 col-lg-9">
                            <?php echo implode(', ', array_map(fn ($c) => $this->escape($c->title), $this->categories)); ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        echo HTMLHelper::_('uitab.endTab');
    endforeach;

    echo HTMLHelper::_('uitab.endTabSet');
    ?>
</div>
