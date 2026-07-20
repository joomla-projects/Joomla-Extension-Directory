<?php

/** @var \Jed\Component\Jed\Administrator\View\Extension\HtmlView $this */
/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Field-by-field diff between two "sides" of an extension: the live #__jed_extensions
 * row and/or a specific #__jed_extensions_history row. See
 * View\Extension\HtmlView::display()'s 'compare' branch for how left/right are resolved.
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Same skip list as tmpl/extension/default.php's read-only view - subforms/media
// galleries and bookkeeping fields don't carry a meaningful single diff-able value.
$jedSkipFieldsetTypes  = ['subform', 'hidden', 'spacer'];
$jedSkipFieldsetFields = ['id', 'checked_out', 'checked_out_time', 'categories'];

$leftLabel = $this->compareLeftId
    ? Text::sprintf('COM_JED_EXTENSION_COMPARE_HISTORY_LABEL', $this->compareLeftId)
    : Text::_('COM_JED_EXTENSION_COMPARE_LIVE_LABEL');
$rightLabel = Text::sprintf('COM_JED_EXTENSION_COMPARE_HISTORY_LABEL', $this->compareRightId);
?>
<style>
    .jed-compare-row { padding: .4rem 0; border-bottom: 1px solid var(--bs-border-color, #eee); }
    .jed-compare-row.jed-compare-diff { background-color: var(--bs-warning-bg-subtle, #fff3cd); }
    .jed-compare-row dt { font-weight: 600; }
    .jed-compare-row dd { margin-bottom: 0; }
</style>

<div class="main-card">
    <h2><?php echo $this->escape(($this->compareLeft->name ?? $this->compareRight->name ?? '') ?: ''); ?></h2>

    <?php if (!$this->compareLeft || !$this->compareRight) : ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_JED_EXTENSION_COMPARE_NO_DATA'); ?>
        </div>
    <?php else : ?>
        <div class="row jed-compare-row">
            <dt class="col-sm-4 col-lg-3"><?php echo Text::_('COM_JED_GENERAL_TITLE_LABEL'); ?></dt>
            <dd class="col-sm-4 col-lg-4"><strong><?php echo $this->escape($leftLabel); ?></strong></dd>
            <dd class="col-sm-4 col-lg-5"><strong><?php echo $this->escape($rightLabel); ?></strong></dd>
        </div>

        <?php if ($this->form) : ?>
            <?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
                <?php foreach ($this->form->getFieldset($fieldset->name) as $field) :
                    if (
                        in_array(strtolower((string) $field->type), $jedSkipFieldsetTypes, true)
                        || in_array($field->fieldname, $jedSkipFieldsetFields, true)
                    ) {
                        continue;
                    }

                    $leftValue  = $this->compareLeft->{$field->fieldname} ?? null;
                    $rightValue = $this->compareRight->{$field->fieldname} ?? null;
                    $isDiff     = $leftValue !== $rightValue;
                    ?>
                    <div class="row jed-compare-row<?php echo $isDiff ? ' jed-compare-diff' : ''; ?>">
                        <dt class="col-sm-4 col-lg-3"><?php echo $field->label; ?></dt>
                        <dd class="col-sm-4 col-lg-4"><?php echo JedHelper::displayFieldValue($field->fieldname, $leftValue); ?></dd>
                        <dd class="col-sm-4 col-lg-5"><?php echo JedHelper::displayFieldValue($field->fieldname, $rightValue); ?></dd>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<form action="index.php" method="post" name="adminForm" id="extension-form" class="form-validate">
    <input type="hidden" name="option" value="com_jed"/>
    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="extension_id" value="<?php echo (int) $this->compareExtensionId; ?>"/>
    <input type="hidden" name="history_id" value="<?php echo (int) $this->compareApprovableId; ?>"/>
    <input type="hidden" name="boxchecked" value="0"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
