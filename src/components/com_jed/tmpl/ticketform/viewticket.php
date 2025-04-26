<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$canEdit = JedHelper::canUserEdit($this->item);
$wa      = Factory::getApplication()->getDocument()->getWebAssetManager();

$wa->getRegistry()->addExtensionRegistryFile('com_jed');
$wa->useStyle('com_jed.Tickets');
HTMLHelper::_('bootstrap.tooltip');
$headerlabeloptions = ['hiddenLabel' => true];
$fieldhiddenoptions = ['hidden' => true];
//echo "<pre>";print_r($this->item);echo "</pre>";exit();

$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();

echo LayoutHelper::render('ticket.ticket_edit_header', $this->item);

if (!$isLoggedIn) {
    try {
        $app = Factory::getApplication();
    } catch (Exception $e) {
    }

    $app->enqueueMessage(Text::_('COM_JED_TICKET_NO_ACCESS'), 'success');
    $app->redirect($redirectURL);
} else {
    ?>

    <div class="ticket-edit front-end-edit">
        <?php
        if (!$canEdit) : ?>
            <h3>
                <?php
                throw new Exception(Text::_('COM_JED_GENERAL_ERROR_MESSAGE_NOT_AUTHORISED'), 403); ?>
            </h3>
        <?php else : ?>
            <form id="form-ticket"
                  action="<?php
                    echo Route::_('index.php?option=com_jed&task=ticketform.save'); ?>"
                  method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

                <div class="row">
                    <div class="col-12">


                        <div class="widget ticket-header-row">
                            <h1 class="ticket-header-16">Message History</h1>
                            <div class="container">
                                <div class="row">
                                    <?php

                                    $slidesOptions = ["active" => 'ticket_messages_group' . '_slide0' , // It is the ID of the active tab.
                                    ];
                                    echo HTMLHelper::_('bootstrap.startAccordion', 'ticket_messages_group', $slidesOptions);

                                    $slideid = 0;
                                    foreach ($this->item->ticket_messages as $ticketMessage) {
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

                        <div class="row">
                            <div class="col-12">


                                <div class="widget">
                                    <h1 class="ticket-header-16">Reply?</h1>
                                    <div class="container">
                                        <div class="row">
                                            <?php echo $this->form->renderField('updated_ticket_text', null, null, $headerlabeloptions); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                <div class="control-group">
                    <div class="controls">

                        <?php
                        if ($this->canSave) : ?>
                            <button type="submit" class="validate btn btn-primary">
                                <span class="fas fa-check" aria-hidden="true"></span>
                                <?php
                                echo Text::_('JSUBMIT'); ?>
                            </button>
                            <?php
                        endif; ?>
                        <a class="btn btn-danger"
                           href="<?php
                            echo Route::_('index.php?option=com_jed&task=ticketform.cancel'); ?>"
                           title="<?php
                            echo Text::_('JCANCEL'); ?>">
                            <span class="fas fa-times" aria-hidden="true"></span>
                            <?php
                            echo Text::_('JCANCEL'); ?>
                        </a>
                    </div>
                </div>

                <input type="hidden" name="option" value="com_jed"/>
                <input type="hidden" name="task"
                       value="ticketform.save"/>
                <?php
                echo HTMLHelper::_('form.token'); ?>
            </form>
                <?php
        endif; ?>
    </div>
    <?php
}
?>