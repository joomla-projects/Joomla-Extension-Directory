<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Master data for a `TicketType::AbandonedExtension` ticket - see
 * Jed\Component\Tickets\Administrator\Ticket\AbandonedreportTicketHandler::getMasterData().
 *
 * @var object|null $displayData
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Language\Text;

$report = $displayData;
$fields = [
    'extension_name'        => 'COM_VEL_ABANDONEDREPORTS_EXTENSION_NAME_LABEL',
    'extension_version'     => 'COM_VEL_ABANDONEDREPORTS_EXTENSION_VERSION_LABEL',
    'extension_url'         => 'COM_VEL_ABANDONEDREPORTS_EXTENSION_URL_LABEL',
    'developer_name'        => 'COM_VEL_GENERAL_DEVELOPER_NAME_LABEL',
    'reporter_fullname'     => 'COM_VEL_GENERAL_CONTACT_FULLNAME_LABEL',
    'reporter_email'        => 'COM_VEL_GENERAL_CONTACT_EMAIL_LABEL',
    'reporter_organisation' => 'COM_VEL_GENERAL_REPORTER_ORGANISATION_LABEL',
];
?>
<?php if (!$report) : ?>
    <p class="text-muted"><?php echo Text::_('COM_TICKETS_MASTERDATA_NOT_FOUND'); ?></p>
<?php else : ?>
    <dl class="row mb-0">
        <?php foreach ($fields as $fieldname => $labelKey) : ?>
            <dt class="col-sm-3"><?php echo Text::_($labelKey); ?></dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars((string) ($report->{$fieldname} ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
        <?php endforeach; ?>

        <dt class="col-sm-3"><?php echo Text::_('COM_VEL_ABANDONEDREPORTS_ABANDONED_REASON_LABEL'); ?></dt>
        <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($report->abandoned_reason ?? '', ENT_QUOTES, 'UTF-8')); ?></dd>

        <dt class="col-sm-3"><?php echo Text::_('JSTATUS'); ?></dt>
        <dd class="col-sm-9"><?php echo JedHelper::displayFieldValue('state', (int) ($report->state ?? 0)); ?></dd>
    </dl>
<?php endif; ?>
