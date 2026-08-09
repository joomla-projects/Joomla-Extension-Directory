<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Jed\Component\Jed\Administrator\View\Extension\HtmlView $this */

// A soft-deleted listing stays readable here and nowhere else: the frontend answers 410. 8.8 asks
// for read-only by absence of the action, not for a disabled form whose controller is still
// reachable, so this page carries no editable field and no save task - the only thing offered is
// Restore, from the toolbar.
$fields = [
    'name',
    'alias',
    'catid',
    'owner',
    'state',
    'approved',
    'blocked',
    'block_reason_code',
    'block_reason_text',
    'intro',
    'description',
    'extension_version',
    'joomla_versions',
    'download_url',
    'developer_url',
    'internal_note',
];
?>

<form action="index.php?option=com_jed&view=extension&layout=deleted&id=<?php echo (int) ($this->item->extension_id ?: $this->item->id); ?>"
      method="post" name="adminForm" id="adminForm">
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<div class="p-3">
    <joomla-alert type="warning">
        <h2><?php echo Text::_('COM_JED_EXTENSION_DELETED_HEADING'); ?></h2>
        <p>
            <?php echo Text::sprintf(
                'COM_JED_EXTENSION_DELETED_NOTICE',
                $this->escape((string) ($this->item->deleted_time ?? '')),
                $this->escape(JedHelper::displayFieldValue('deleted_by', $this->item->deleted_by ?? null))
            ); ?>
        </p>
    </joomla-alert>

    <dl class="row">
        <?php foreach ($fields as $field) : ?>
            <dt class="col-sm-3"><?php echo Text::_('COM_JED_EXTENSION_' . strtoupper($field) . '_LABEL'); ?></dt>
            <dd class="col-sm-9">
                <?php echo JedHelper::displayFieldValue($field, $this->item->{$field} ?? null); ?>
            </dd>
        <?php endforeach; ?>
    </dl>

    <?php if (!empty($this->images)) : ?>
        <h3><?php echo Text::_('COM_JED_FIELDSET_MEDIA_LABEL'); ?></h3>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($this->images as $image) : ?>
                <img src="<?php echo $this->escape(JedHelper::formatImage((string) $image->filename)); ?>"
                     alt="" class="jed-view-thumb" loading="lazy">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($this->history)) : ?>
        <h3><?php echo Text::_('COM_JED_EXTENSION_HISTORY_LABEL'); ?></h3>
        <table class="table">
            <thead>
                <tr>
                    <th><?php echo Text::_('JGLOBAL_FIELD_ID_LABEL'); ?></th>
                    <th><?php echo Text::_('COM_JED_EXTENSION_MODIFIED_LABEL'); ?></th>
                    <th><?php echo Text::_('COM_JED_EXTENSION_BLOCKED_LABEL'); ?></th>
                    <th><?php echo Text::_('COM_JED_EXTENSION_BLOCK_REASON_CODE_LABEL'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->history as $revision) : ?>
                    <tr>
                        <td><?php echo (int) $revision->id; ?></td>
                        <td><?php echo $this->escape((string) ($revision->modified ?? '')); ?></td>
                        <td><?php echo (int) ($revision->blocked ?? 0) === 1 ? Text::_('JYES') : Text::_('JNO'); ?></td>
                        <td><?php echo $this->escape((string) ($revision->block_reason_code ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
