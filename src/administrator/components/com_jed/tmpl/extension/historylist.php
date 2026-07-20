<?php

/** @var \Jed\Component\Jed\Administrator\View\Extension\HtmlView $this */
/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Rendered two ways: as a quick-view modal fragment (tmpl/extensions/default.php
 * fetches this with tmpl=component, which suppresses the toolbar/form chrome below
 * regardless of what addToolbar() populated), and as a full standalone page when
 * opened directly - only the full-page case can actually show/use the Compare
 * toolbar button, since Joomla.submitbutton() needs a real form.form-validate on
 * the page.
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$extensionId = (int) ($this->item->extension_id ?: $this->item->id);
?>
<div class="p-3">

    <h4 class="mb-3"><?php echo $this->escape($this->item->name); ?></h4>

    <?php if (empty($this->history)) : ?>
        <p class="text-muted"><?php echo Text::_('COM_JED_EXTENSION_NO_HISTORY'); ?></p>
    <?php else : ?>
    <table class="table table-sm table-striped table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th scope="col" class="text-center"><?php echo Text::_('COM_JED_EXTENSION_COMPARE_LABEL'); ?></th>
                <th scope="col"><?php echo Text::_('COM_JED_GENERAL_DATE_LABEL'); ?></th>
                <th scope="col"><?php echo Text::_('COM_JED_EXTENSION_HISTORY_AUTHOR_LABEL'); ?></th>
                <th scope="col" class="text-center"><?php echo Text::_('COM_JED_EXTENSION_ACTIVE_LABEL'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($this->history as $entry) :
                $isActive  = (int) $entry->active === 1;
                $historyId = (int) $entry->id;
                $date      = $entry->modified ?: $entry->created;
            ?>
            <tr>
                <td class="text-center">
                    <input type="checkbox" name="history[]" value="<?php echo $historyId; ?>"
                           form="jed-history-form"
                           aria-label="<?php echo Text::_('COM_JED_EXTENSION_COMPARE_LABEL'); ?>">
                </td>
                <td>
                    <?php echo $date ? HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC2')) : '&ndash;'; ?>
                </td>
                <td>
                    <?php echo $this->escape($entry->editor_name ?? ''); ?>
                </td>
                <td class="text-center">
                    <?php if ($isActive) : ?>
                        <span class="icon-check-circle text-success fs-5" aria-hidden="true"
                              title="<?php echo Text::_('JYES'); ?>"></span>
                        <span class="visually-hidden"><?php echo Text::_('JYES'); ?></span>
                    <?php else : ?>
                        <form action="index.php" method="post" class="d-inline">
                            <input type="hidden" name="option" value="com_jed"/>
                            <input type="hidden" name="task" value="extension.activateVersion"/>
                            <input type="hidden" name="extension_id" value="<?php echo $extensionId; ?>"/>
                            <input type="hidden" name="id" value="<?php echo $historyId; ?>"/>
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <button type="submit"
                                    class="jed-activate-version btn btn-link p-0 border-0"
                                    data-extension-id="<?php echo $extensionId; ?>"
                                    data-version-id="<?php echo $historyId; ?>"
                                    title="<?php echo Text::_('COM_JED_EXTENSION_ACTIVATE_VERSION_LABEL'); ?>">
                                <span class="icon-times-circle text-danger fs-5" aria-hidden="true"></span>
                                <span class="visually-hidden"><?php echo Text::_('COM_JED_EXTENSION_ACTIVATE_VERSION_LABEL'); ?></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <form action="index.php" method="post" name="adminForm" id="jed-history-form" class="form-validate">
        <input type="hidden" name="option" value="com_jed"/>
        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="extension_id" value="<?php echo $extensionId; ?>"/>
        <input type="hidden" name="boxchecked" value="0"/>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

</div>
