<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * The "Ticket Help" tab: who opened the ticket and what else the JED knows about them - their
 * account, the team's privilege/ban record on them, their listings, their reviews and their
 * other tickets. See TicketModel::getTicketHelp(). Team-only.
 *
 * @var array{creator: \Joomla\CMS\User\User, access: ?object, extensions: object[], reviews: object[], tickets: object[]} $displayData
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$creator    = $displayData['creator'];
$access     = $displayData['access'];
$extensions = $displayData['extensions'];
$reviews    = $displayData['reviews'];
$tickets    = $displayData['tickets'];

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$never = static fn (?string $date): string => ($date === null || $date === '' || str_starts_with($date, '0000'))
    ? Text::_('JNEVER')
    : JedHelper::prettyDate($date);

$yesNo = static fn ($value): string => (int) $value === 1
    ? '<span class="icon-check text-success" aria-hidden="true"></span> ' . Text::_('JYES')
    : '<span class="icon-times text-danger" aria-hidden="true"></span> ' . Text::_('JNO');

$banInForce = $access !== null && (int) $access->banned === 1;
?>
<div class="jed-ticket-help">

    <!-- The person -->
    <div class="widget">
        <h1><?php echo Text::_('COM_JED_TICKET_HELP_CREATOR'); ?></h1>
        <div class="container">
            <?php if (!$creator->id) : ?>
                <p class="text-muted"><?php echo Text::_('COM_JED_TICKET_HELP_CREATOR_UNKNOWN'); ?></p>
            <?php else : ?>
                <div class="row ticket-header-row">
                    <div class="col-md-3 ticket-header">
                        <h1><?php echo Text::_('JGLOBAL_FIELD_NAME_LABEL'); ?></h1>
                        <span>
                            <a href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $creator->id); ?>">
                                <?php echo $escape($creator->name); ?>
                            </a>
                            <?php if ((int) $creator->block === 1) : ?>
                                <span class="badge bg-danger"><?php echo Text::_('JBLOCKED'); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="col-md-2 ticket-header">
                        <h1><?php echo Text::_('JGLOBAL_USERNAME'); ?></h1>
                        <span><?php echo $escape($creator->username); ?></span>
                    </div>
                    <div class="col-md-3 ticket-header">
                        <h1><?php echo Text::_('JGLOBAL_EMAIL'); ?></h1>
                        <span><a href="mailto:<?php echo $escape($creator->email); ?>"><?php echo $escape($creator->email); ?></a></span>
                    </div>
                    <div class="col-md-2 ticket-header">
                        <h1><?php echo Text::_('COM_JED_TICKET_HELP_REGISTERED'); ?></h1>
                        <span><?php echo $never($creator->registerDate); ?></span>
                    </div>
                    <div class="col-md-2 ticket-header">
                        <h1><?php echo Text::_('COM_JED_TICKET_HELP_LAST_VISIT'); ?></h1>
                        <span><?php echo $never($creator->lastvisitDate); ?></span>
                    </div>
                </div>

                <!-- The team's record on them (P1-05): an absent row means default privileges, not banned. -->
                <div class="row ticket-header-row">
                    <div class="col-md-12 ticket-header">
                        <h1>
                            <?php echo Text::_('COM_JED_USERACCESS_PRIVILEGES'); ?>
                            <a class="small ms-2" href="<?php echo Route::_('index.php?option=com_jed&view=useraccess&filter[search]=' . urlencode($creator->username)); ?>">
                                <?php echo Text::_('COM_JED_TITLE_USERACCESS'); ?>
                            </a>
                        </h1>
                        <?php if ($access === null) : ?>
                            <span class="text-muted"><?php echo Text::_('COM_JED_USERACCESS_NEVER_DECIDED'); ?> - <?php echo Text::_('COM_JED_TICKET_HELP_PRIVILEGES_DEFAULT'); ?></span>
                        <?php else : ?>
                            <?php if ($banInForce) : ?>
                                <div class="alert alert-danger py-1 my-1">
                                    <strong><?php echo Text::_('COM_JED_USERACCESS_BANNED'); ?></strong>
                                    <?php echo $escape($access->banned_reason); ?>
                                    <?php if (!empty($access->banned_until) && !str_starts_with((string) $access->banned_until, '0000')) : ?>
                                        (<?php echo Text::sprintf('COM_JED_USERACCESS_BANNED_UNTIL', JedHelper::prettyDate($access->banned_until)); ?>)
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4"><?php echo Text::_('COM_JED_USERACCESS_PRIVILEGE_CREATE_LISTING'); ?></dt>
                                <dd class="col-sm-2"><?php echo $yesNo($access->create_listing); ?></dd>
                                <dt class="col-sm-4"><?php echo Text::_('COM_JED_USERACCESS_TRUST_EXTENSIONS'); ?></dt>
                                <dd class="col-sm-2"><?php echo $yesNo($access->auto_approve_extensions); ?></dd>
                                <dt class="col-sm-4"><?php echo Text::_('COM_JED_USERACCESS_PRIVILEGE_EDIT_LISTING'); ?></dt>
                                <dd class="col-sm-2"><?php echo $yesNo($access->edit_listing); ?></dd>
                                <dt class="col-sm-4"><?php echo Text::_('COM_JED_USERACCESS_TRUST_REVIEWS'); ?></dt>
                                <dd class="col-sm-2"><?php echo $yesNo($access->auto_approve_reviews); ?></dd>
                                <dt class="col-sm-4"><?php echo Text::_('COM_JED_USERACCESS_PRIVILEGE_REVIEW'); ?></dt>
                                <dd class="col-sm-2"><?php echo $yesNo($access->review); ?></dd>
                                <dt class="col-sm-4"><?php echo Text::_('COM_JED_USERACCESS_PRIVILEGE_UPDATE_XML'); ?></dt>
                                <dd class="col-sm-2"><?php echo $yesNo($access->update_xml); ?></dd>
                                <dt class="col-sm-4"><?php echo Text::_('COM_JED_USERACCESS_PRIVILEGE_REPORT'); ?></dt>
                                <dd class="col-sm-2"><?php echo $yesNo($access->report); ?></dd>
                            </dl>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    echo HTMLHelper::_('uitab.startTabSet', 'ticketHelpTab', ['active' => 'tickethelp-extensions']);

    // ---- Their listings
    echo HTMLHelper::_(
        'uitab.addTab',
        'ticketHelpTab',
        'tickethelp-extensions',
        Text::_('COM_JED_TICKET_HELP_EXTENSIONS') . ' <span class="badge bg-secondary">' . \count($extensions) . '</span>'
    );
    ?>
    <div class="widget">
        <h1><?php echo Text::_('COM_JED_TICKET_HELP_EXTENSIONS'); ?></h1>
        <div class="container">
            <?php if (empty($extensions)) : ?>
                <p class="text-muted"><?php echo Text::_('COM_JED_TICKET_HELP_NONE'); ?></p>
            <?php else : ?>
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('JGLOBAL_FIELD_ID_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_GENERAL_VERSION_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('JSTATUS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_EXTENSION_APPROVED_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_TICKET_HELP_ROLE'); ?></th>
                            <th scope="col"><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_LABEL'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($extensions as $extension) : ?>
                        <tr>
                            <td><?php echo (int) $extension->id; ?></td>
                            <td>
                                <a href="<?php echo Route::_('index.php?option=com_jed&task=extension.edit&id=' . (int) $extension->id); ?>">
                                    <?php echo $escape($extension->name); ?>
                                </a>
                                <?php if ((int) $extension->blocked === 1) : ?>
                                    <span class="badge bg-danger"><?php echo Text::_('COM_JED_EXTENSION_BLOCKED_LABEL'); ?></span>
                                <?php endif; ?>
                                <?php if ((int) $extension->deleted === 1) : ?>
                                    <span class="badge bg-dark"><?php echo Text::_('JTRASHED'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $escape($extension->extension_version); ?></td>
                            <td><?php echo JedHelper::getPublishedIcon((int) $extension->state); ?></td>
                            <td><?php echo JedHelper::getApprovedIcon((int) $extension->approved); ?></td>
                            <td><?php echo Text::_((int) $extension->owner === (int) $creator->id ? 'COM_JED_EXTENSION_OWNER_LABEL' : 'JGLOBAL_FIELD_CREATED_BY_LABEL'); ?></td>
                            <td><?php echo $never($extension->modified ?: $extension->created); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
    echo HTMLHelper::_('uitab.endTab');

    // ---- Their reviews
    echo HTMLHelper::_(
        'uitab.addTab',
        'ticketHelpTab',
        'tickethelp-reviews',
        Text::_('COM_JED_TITLE_REVIEWS') . ' <span class="badge bg-secondary">' . \count($reviews) . '</span>'
    );
    ?>
    <div class="widget">
        <h1><?php echo Text::_('COM_JED_TITLE_REVIEWS'); ?></h1>
        <div class="container">
            <?php if (empty($reviews)) : ?>
                <p class="text-muted"><?php echo Text::_('COM_JED_TICKET_HELP_NONE'); ?></p>
            <?php else : ?>
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('JGLOBAL_FIELD_ID_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_TICKETS_LINKED_ITEM_TYPE_OPTION_EXTENSION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_REVIEWS_OVERALL_SCORE_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('JSTATUS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reviews as $review) : ?>
                        <tr>
                            <td><?php echo (int) $review->id; ?></td>
                            <td>
                                <a href="<?php echo Route::_('index.php?option=com_jed&task=review.edit&id=' . (int) $review->id); ?>">
                                    <?php echo $escape($review->title); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ((int) $review->extension_id > 0) : ?>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&task=extension.edit&id=' . (int) $review->extension_id); ?>">
                                        <?php echo $escape($review->extension_name ?: $review->extension_id); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format((float) $review->overall_score, 1); ?></td>
                            <td><?php echo JedHelper::getPublishedIcon((int) $review->state); ?></td>
                            <td><?php echo $never($review->created_on); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
    echo HTMLHelper::_('uitab.endTab');

    // ---- Their other tickets (the migrated legacy ones included - they are all in #__jed_tickets now)
    echo HTMLHelper::_(
        'uitab.addTab',
        'ticketHelpTab',
        'tickethelp-tickets',
        Text::_('COM_JED_TICKET_HELP_OTHER_TICKETS') . ' <span class="badge bg-secondary">' . \count($tickets) . '</span>'
    );
    ?>
    <div class="widget">
        <h1><?php echo Text::_('COM_JED_TICKET_HELP_OTHER_TICKETS'); ?></h1>
        <div class="container">
            <?php if (empty($tickets)) : ?>
                <p class="text-muted"><?php echo Text::_('COM_JED_TICKET_HELP_NONE'); ?></p>
            <?php else : ?>
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('JGLOBAL_FIELD_ID_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_GENERAL_SUBJECT_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_GENERAL_TYPE_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('JSTATUS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tickets as $ticket) : ?>
                        <tr>
                            <td><?php echo (int) $ticket->id; ?></td>
                            <td>
                                <a href="<?php echo Route::_('index.php?option=com_jed&task=ticket.edit&id=' . (int) $ticket->id); ?>">
                                    <?php echo $escape($ticket->ticket_subject); ?>
                                </a>
                            </td>
                            <td><?php echo $escape($ticket->categorytype_string); ?></td>
                            <td><?php echo $escape($ticket->ticket_status_label); ?></td>
                            <td><?php echo $never($ticket->created_on); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
    echo HTMLHelper::_('uitab.endTab');
    echo HTMLHelper::_('uitab.endTabSet');
    ?>
</div>
