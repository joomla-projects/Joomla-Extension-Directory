<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Master data for a `TicketType::VulnerableExtension` ticket ("solved vulnerability"
 * developer update) - see
 * Jed\Component\Tickets\Administrator\Ticket\DeveloperupdateTicketHandler::getMasterData().
 *
 * @var object|null $displayData
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;

$developerUpdate = $displayData->developerUpdate ?? null;
$vulnerableItem  = $displayData->vulnerableItem ?? null;
$fields          = [
    'vulnerable_item_name'    => 'COM_VEL_GENERAL_VULNERABLE_ITEM_NAME_LABEL',
    'vulnerable_item_version' => 'COM_VEL_GENERAL_VULNERABLE_ITEM_VERSION_LABEL',
    'new_version_number'      => 'COM_VEL_GENERAL_NEW_VERSION_NUMBER_LABEL',
    'contact_fullname'        => 'COM_VEL_GENERAL_CONTACT_FULLNAME_LABEL',
    'contact_email'           => 'COM_VEL_GENERAL_CONTACT_EMAIL_LABEL',
    'update_notice_url'       => 'COM_VEL_GENERAL_UPDATE_NOTICE_URL_LABEL',
    'changelog_url'           => 'COM_VEL_GENERAL_CHANGELOG_URL_LABEL',
    'download_url'            => 'COM_VEL_EXTENSION_DOWNLOAD_INTEGRATION_URL_LABEL',
];
?>
<?php if (!$developerUpdate) : ?>
    <p class="text-muted"><?php echo Text::_('COM_TICKETS_MASTERDATA_NOT_FOUND'); ?></p>
<?php else : ?>
    <dl class="row mb-2">
        <?php foreach ($fields as $fieldname => $labelKey) : ?>
            <dt class="col-sm-3"><?php echo Text::_($labelKey); ?></dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars((string) ($developerUpdate->{$fieldname} ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
        <?php endforeach; ?>
    </dl>

    <?php if ($vulnerableItem) : ?>
        <div class="alert alert-info mb-0">
            <strong><?php echo Text::_('COM_TICKETS_MASTERDATA_LINKED_VULNERABLE_ITEM_LABEL'); ?>:</strong>
            <?php echo htmlspecialchars($vulnerableItem->title ?: $vulnerableItem->vulnerable_item_name, ENT_QUOTES, 'UTF-8'); ?>
            &mdash;
            <?php echo Text::_('JSTATUS'); ?>: <?php echo Text::_('COM_VEL_GENERAL_STATUS_OPTION_' . (int) ($vulnerableItem->status ?? 0)); ?>
        </div>
    <?php else : ?>
        <p class="text-muted mb-0"><?php echo Text::_('COM_TICKETS_MASTERDATA_NO_LINKED_VULNERABLE_ITEM'); ?></p>
    <?php endif; ?>
<?php endif; ?>
