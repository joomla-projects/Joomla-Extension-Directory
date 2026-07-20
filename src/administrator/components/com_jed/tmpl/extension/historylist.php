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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

?>
<div class="p-3">

    <h4 class="mb-3"><?php echo $this->escape($this->item->name); ?></h4>

    <?php if (empty($this->history)) : ?>
        <p class="text-muted"><?php echo Text::_('COM_JED_EXTENSION_NO_HISTORY'); ?></p>
    <?php else : ?>
    <table class="table table-sm table-striped table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th scope="col"><?php echo Text::_('COM_JED_GENERAL_DATE_LABEL'); ?></th>
                <th scope="col"><?php echo Text::_('COM_JED_EXTENSION_HISTORY_AUTHOR_LABEL'); ?></th>
                <th scope="col" class="text-center"><?php echo Text::_('COM_JED_EXTENSION_ACTIVE_LABEL'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($this->history as $entry) :
                $isActive    = (int) $entry->active === 1;
                $extensionId = (int) $entry->extension_id;
                $historyId   = (int) $entry->id;
                $date        = $entry->modified ?: $entry->created;
            ?>
            <tr>
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
                        <a href="#"
                           class="jed-activate-version"
                           data-extension-id="<?php echo $extensionId; ?>"
                           data-version-id="<?php echo $historyId; ?>"
                           title="<?php echo Text::_('COM_JED_EXTENSION_ACTIVATE_VERSION_LABEL'); ?>">
                            <span class="icon-times-circle text-danger fs-5" aria-hidden="true"></span>
                            <span class="visually-hidden"><?php echo Text::_('COM_JED_EXTENSION_ACTIVATE_VERSION_LABEL'); ?></span>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

</div>
