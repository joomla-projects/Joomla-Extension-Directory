<?php

/** @var \Jed\Component\Tickets\Administrator\View\Ticket\HtmlView $this */
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
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Jed\Component\Tickets\Administrator\Ticket\TicketAction;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

//var_dump($this);
HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('jquery.framework');

$wa = $this->document->getWebAssetManager();

$wa->useStyle('com_tickets.Tickets');
    //->useStyle('com_jed.jquery_dataTables');
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('com_tickets.ticketGetmessagetemplate');
    //->useScript('com_jed.jquery_dataTables');
if ($this->linked_item_type === TicketType::Review->value) {
    $wa->useScript('com_tickets.ticketPublishUnPublishReview');
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
        action="<?php echo Route::_('index.php?option=com_tickets&layout=edit&id=' . (int) $this->item->id); ?>"
        method="post" enctype="multipart/form-data" name="adminForm" id="ticket-form"
        class="form-validate form-horizontal">


    <div class="com_jed_ticket">
        <div class="row-fluid">
            <!-- header boxes -->
            <?php echo LayoutHelper::render('ticket.header', $this->form); ?>

        </div>

    </div> <!-- end div class  com_jed_ticket -->


    <br/>

    <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'ticket']); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'ticket', Text::_('COM_TICKETS_TAB_TICKET', true)); ?>
    <!-- Ticket Summary Tab -->
    <div class="row">
        <div class="col-8">

            <div class="widget jed-ticket-masterdata">
                <h1><?php echo Text::_('COM_TICKETS_MASTERDATA_LABEL'); ?></h1>
                <div class="container">
                    <?php echo LayoutHelper::render($this->linkedItemLayout, $this->linkedItemData); ?>
                    <?php if ($this->linkedItemActions) : ?>
                        <div class="row jed-ticket-actions mt-2">
                            <?php foreach ($this->linkedItemActions as $action) :
                                /** @var TicketAction $action */
                                echo LayoutHelper::render('ticket.action_button', ['action' => $action]);
                            endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="widget">
                <h1>Message History</h1>
                <div class="container">
                    <div class="row">
                        <?php

                        $slidesOptions = ["active" => 'ticket_messages_group' . '_slide' . count($this->ticket_messages), // It is the ID of the active tab.
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
                <h1><?php echo Text::_('COM_TICKETS_TICKETS_LINKED_ITEM_TYPE_LABEL'); ?></h1>
                <div class="container">
                    <p><?php echo Text::_('COM_TICKETS_TICKETS_LINKED_ITEM_TYPE_OPTION_' . strtoupper($this->linked_item_type_name)); ?></p>
                </div>
            </div>

        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'Publishing', Text::_('COM_TICKETS_TAB_PUBLISHING', true)); ?>
    <div class="row-fluid">
        <div class="span10 form-horizontal">
            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_TICKETS_GENERAL_PUBLISHING_LABEL'); ?></legend>
                <?php echo $this->form->renderField('state'); ?>

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
