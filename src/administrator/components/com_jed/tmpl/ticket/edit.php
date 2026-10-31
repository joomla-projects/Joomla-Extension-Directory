<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Enum\TicketType;
use Jed\Component\Jed\Administrator\Ticket\TicketAction;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Administrator\View\Ticket\HtmlView $this */

//var_dump($this);
HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('jquery.framework');

$wa = $this->getDocument()->getWebAssetManager();

$wa->useStyle('com_jed.tickets');
    //->useStyle('com_jed.jquery_dataTables');
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('com_jed.ticketGetmessagetemplate');
    //->useScript('com_jed.jquery_dataTables');
if ($this->linked_item_type === TicketType::Review->value) {
    $wa->useScript('com_jed.ticketPublishUnPublishReview');
}
//->useScript('com_jed.bootstrap_dataTables')
//->useScript('com_jed.responsive_dataTables')
//->useScript('com_jed.responsive_bootstrap')
HTMLHelper::_('bootstrap.tooltip');

$headerlabeloptions = ['hiddenLabel' => true];
$fieldhiddenoptions = ['hidden' => true];

$container   = Factory::getContainer();
$userFactory = $container->get('user.factory');

?>


<form
        action="<?php echo Route::_('index.php?option=com_jed&view=ticket&layout=edit&id=' . (int) $this->item->id); ?>"
        method="post" enctype="multipart/form-data" name="adminForm" id="ticket-form"
        class="form-validate form-horizontal">

    <div class="com_jed_ticket">
        <div class="row-fluid">
            <!-- header boxes -->
            <?php echo LayoutHelper::render('ticket.header', $this->form); ?>

        </div>

    </div> <!-- end div class  com_jed_ticket -->


    <br/>

    <?php
    // recall: the JED Checker run and the internal note both post and come back to this screen,
    // and the tab the team was on is the one they want to land on.
    echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'ticket', 'recall' => true]);
    ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'ticket', Text::_('COM_JED_TAB_TICKET', true)); ?>
    <!-- Ticket Summary Tab -->
    <div class="row">
        <div class="col-8">

            <div class="widget">
                <h1>Message History</h1>
                <div class="container">
                    <div class="row">
                        <?php

                        $slidesOptions = ["active" => 'ticket_messages_group' . '_slide' . $this->latest_ticket_message, // It is the ID of the active tab.
                        ];
                        echo HTMLHelper::_('bootstrap.startAccordion', 'ticket_messages_group', $slidesOptions);

                        $slideid = 0;
                        foreach ($this->ticket_messages as $ticketMessage) {
                            if ($ticketMessage->message_direction == 0) {
                                $inout = "jed-ticket-message-out";
                            } else {
                                $inout = "jed-ticket-message-in";
                            }

                            echo HTMLHelper::_('bootstrap.addSlide', 'ticket_messages_group', '<span class="' . $inout . '">' . $ticketMessage->subject . ' - ' . JedHelper::prettyDate($ticketMessage->created_on) . '</span>', 'ticket_messages_group' . '_slide' . ($slideid++));
                            echo  $ticketMessage->message ;
                            echo HTMLHelper::_('bootstrap.endSlide');
                        }
                        echo HTMLHelper::_('bootstrap.endAccordion');

                        ?>


                    </div>

                </div>
            </div>

            <div class="widget">
                <h1>Message Templates</h1>
                <div class="container">
                    <div class="row">
                        <?php echo $this->form->renderField('messagetemplates', null, null, $headerlabeloptions); ?>

                    </div>
                </div>
            </div>

            <div class="widget">
                <h1>Compose Message</h1>
                <div class="container">
                    <div class="row">
                        <?php echo $this->form->renderField('message_subject', null, null, $headerlabeloptions); ?>
                        <?php echo $this->form->renderField('message_text', null, null, $headerlabeloptions); ?>

                        <button type="button" class="btn btn-primary"
                                onclick="Joomla.submitbutton('ticket.sendmessage')">


                            <?php echo Text::_('Send Message'); ?>

                        </button>


                    </div>
                </div>
            </div>


        </div>
        <div class="col-4">
            <div class="widget">
                <h1>Created By</h1>
                <div class="container">
                    <div class="row">
                        <div class="col"><?php echo $this->form->renderField('created_by', null, null, $headerlabeloptions); ?></div>
                        <div class="col"><?php
                            echo 'on ';

                            echo JedHelper::prettyDate($this->item->created_on);


                            ?></div>
                    </div>

                </div>


            </div>
            <div class="widget">
                <h1><?php echo Text::_('COM_JED_TICKETS_LINKED_ITEM_TYPE_LABEL'); ?></h1>
                <div class="container">
                    <p><?php echo Text::_('COM_JED_TICKETS_LINKED_ITEM_TYPE_OPTION_' . strtoupper($this->linked_item_type_name)) . " is displayed in 'Linked ". Text::_('COM_JED_TICKETS_LINKED_ITEM_TYPE_OPTION_' . strtoupper($this->linked_item_type_name))."' tab.";?></p>
                </div>
            </div>

            <?php if ($this->canManage) : ?>
            <!-- Internal notes: the JED team's own record on the ticket, never shown to its creator. -->
            <div class="widget jed-ticket-internal-notes">
                <h1><?php echo Text::_('COM_JED_TICKETS_INTERNAL_NOTES_LABEL'); ?></h1>
                <div class="container">
                    <?php if (trim((string) $this->item->internal_notes) !== '') : ?>
                        <div class="jed-ticket-legacy-notes small mb-2">
                            <?php echo $this->item->internal_notes; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($this->internal_notes)) : ?>
                        <p class="text-muted small"><?php echo Text::_('COM_JED_TICKET_INTERNAL_NOTES_NONE'); ?></p>
                    <?php else : ?>
                        <?php
                        echo HTMLHelper::_('bootstrap.startAccordion', 'internal_notes_group', []);

                        $slideid = 0;

                        foreach ($this->internal_notes as $internalNote) {
                            $heading = $this->escape($internalNote->summary)
                                . ' - ' . JedHelper::prettyDate($internalNote->created_on)
                                . ' ' . Text::sprintf('COM_JED_TICKET_INTERNAL_NOTE_BY', $this->escape($internalNote->author_name ?: $internalNote->created_by));

                            echo HTMLHelper::_('bootstrap.addSlide', 'internal_notes_group', $heading, 'internal_notes_group_slide' . ($slideid++));
                            echo $internalNote->note;
                            echo HTMLHelper::_('bootstrap.endSlide');
                        }

                        echo HTMLHelper::_('bootstrap.endAccordion');
                        ?>
                    <?php endif; ?>

                    <div class="widget">
                        <h1>
                            <?php echo Text::_('COM_JED_TICKET_INTERNAL_NOTE_ADD'); ?>&nbsp;&nbsp;
                            <button type="button" class="btn btn-sm btn-primary"
                                    onclick="Joomla.submitbutton('ticket.storeInternalNote')">
                                <span class="icon-save" aria-hidden="true"></span>
                            </button>
                        </h1>
                        <?php echo $this->form->renderField('note_summary', null, null, $headerlabeloptions); ?>
                        <?php echo $this->form->renderField('note_text', null, null, $headerlabeloptions); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php

    echo HTMLHelper::_(
        'uitab.addTab',
        'myTab',
        'linked' . strtolower($this->linked_item_type_name),
        Text::sprintf('COM_JED_TICKET_LINKED_ITEM_TAB', Text::_('COM_JED_TICKETS_LINKED_ITEM_TYPE_OPTION_' . strtoupper($this->linked_item_type_name)))
    );
?>
<div class="position-relative">
  <?php  if ($this->linkedItemActions) : ?>
    <div class="position-absolute top-0 end-0 p-3 m-3 bg-light border rounded shadow" style="max-width: 320px; z-index: 1050;">

   <!-- <div class="row jed-ticket-actions mt-2">-->
        <?php // echo "<pre>";print_r($this->linkedItemActions);echo "</pre>"; ?>
        <?php foreach ($this->linkedItemActions as $action) :
            /** @var TicketAction $action */
            echo LayoutHelper::render('ticket.action_button', ['action' => $action]);
        endforeach; ?>
    </div></div>
    <?php endif;
    echo LayoutHelper::render($this->linkedItemLayout, $this->linkedItemData);
    echo HTMLHelper::_('uitab.endTab');

    if ($this->canManage && $this->extensionId !== null) {
        // JED Checker: the rules the JED team reviews every package with, run against the
        // listing this ticket is about. Anything that resolves to a listing - an extension
        // ticket, a review, a link-check ticket, an abandonware case - gets the tab.
        $jedCheckerCounts = $this->jedChecker['result']['totals'] ?? null;
        $jedCheckerBadge  = $jedCheckerCounts === null
            ? ''
            : ' <span class="badge ' . ($jedCheckerCounts['errors'] > 0 ? 'bg-danger' : ($jedCheckerCounts['warnings'] > 0 ? 'bg-warning text-dark' : 'bg-success')) . '">'
                . (int) $jedCheckerCounts['errors'] . '/' . (int) $jedCheckerCounts['warnings'] . '</span>';

        echo HTMLHelper::_('uitab.addTab', 'myTab', 'jedchecker', Text::_('COM_JED_JEDCHECKER_TAB') . $jedCheckerBadge);

        echo LayoutHelper::render('ticket.jedchecker', $this->jedChecker + ['ticketId' => (int) $this->item->id]);

        echo HTMLHelper::_('uitab.endTab');
    }

    if ($this->canManage && !empty($this->item->id)) {
        // Ticket Help: who opened the ticket and what else the JED knows about them.
        echo HTMLHelper::_('uitab.addTab', 'myTab', 'tickethelp', Text::_('COM_JED_TICKET_HELP_TAB'));

        echo LayoutHelper::render('ticket.ticket_help', $this->ticketHelp);

        echo HTMLHelper::_('uitab.endTab');
    }
    ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'Publishing', Text::_('COM_JED_TAB_PUBLISHING', true)); ?>
    <div class="row-fluid">
        <div class="span10 form-horizontal">
            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_JED_GENERAL_PUBLISHING_LABEL'); ?></legend>
                <!-- The below field does not make sense, it should be a published/unpublished state but as all tickets should be shown regardless of state. I am hiding it for now. MF -->
                <?php //echo $this->form->renderField('state'); ?>

                <?php echo $this->form->renderField('created_on'); ?>
                <?php echo $this->form->renderField('modified_by'); ?>
                <?php echo $this->form->renderField('modified_on'); ?>
                <input type="hidden" name="jform[created_by_num]"
                       value="<?php echo $this->item->created_by; ?>"/>
                <?php echo $this->form->renderField('id'); ?>
                <?php echo $this->form->renderField('uploaded_files_preview'); //,null,null,$fieldhiddenoptions);?>
                <?php echo $this->form->renderField('uploaded_files_location'); //,null,null,$fieldhiddenoptions);?>
                <?php echo $this->form->renderField('linked_item_type', null, null, $fieldhiddenoptions); ?>
                <?php echo $this->form->renderField('linked_item_id', null, null, $fieldhiddenoptions); ?>
                <?php echo $this->form->renderField('parent_id', null, null, $fieldhiddenoptions); ?>
                <?php echo $this->form->renderField('id', null, null, $fieldhiddenoptions); ?>
            </fieldset>
        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>


    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>


    <input type="hidden" name="task" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>

</form>
