<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Master data for a `TicketType::Extension` ticket - see
 * Jed\Component\Tickets\Administrator\Ticket\ExtensionTicketHandler::getMasterData().
 *
 * @var object $displayData
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$extension = $displayData->pending ?? $displayData->live ?? null;
$fields    = [
    'name'             => 'JGLOBAL_TITLE',
    'catid'            => 'COM_JED_GENERAL_CATEGORY_ID_LABEL',
    'type'             => 'COM_JED_GENERAL_TYPE_LABEL',
    'state'            => 'JSTATUS',
    'extension_version' => 'COM_JED_GENERAL_VERSION_LABEL',
    'developer_url'    => 'COM_JED_EXTENSION_DEVELOPER_URL_LABEL',
    'download_url'     => 'COM_JED_EXTENSION_DOWNLOAD_LINK_LABEL',
    'created_by'       => 'JGLOBAL_FIELD_CREATED_BY_LABEL',
    'created'          => 'COM_JED_GENERAL_CREATED_ON_LABEL',
];
?>
<?php if (!$extension) : ?>
    <p class="text-muted"><?php echo Text::_('COM_TICKETS_MASTERDATA_NOT_FOUND'); ?></p>
<?php else : ?>
    <dl class="row mb-0">
        <?php foreach ($fields as $fieldname => $labelKey) : ?>
            <dt class="col-sm-3"><?php echo Text::_($labelKey); ?></dt>
            <dd class="col-sm-9"><?php echo JedHelper::displayFieldValue($fieldname, $extension->{$fieldname} ?? null); ?></dd>
        <?php endforeach; ?>
    </dl>

    <?php if ($displayData->isPending) : ?>
        <div class="alert alert-warning mb-0 mt-2">
            <?php echo Text::_('COM_TICKETS_MASTERDATA_EXTENSION_PENDING'); ?>
            <a href="<?php echo Route::_('index.php?option=com_jed&task=extension.compareHistory&extension_id=' . (int) $displayData->extensionId . '&right=' . (int) $displayData->pendingHistoryId); ?>">
                <?php echo Text::_('COM_JED_EXTENSION_COMPARE_LABEL'); ?>
            </a>
        </div>
    <?php endif; ?>
<?php endif; ?>
