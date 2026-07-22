<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
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

/** @var \Jed\Component\Tickets\Site\View\Ticket\HtmlView $this */

$canEdit = JedHelper::canUserEdit($this->item);
$wa      = Factory::getApplication()->getDocument()->getWebAssetManager();

$wa->getRegistry()->addExtensionRegistryFile('com_tickets');
$wa->useStyle('com_tickets.Tickets');
HTMLHelper::_('bootstrap.tooltip');
$headerlabeloptions = ['hiddenLabel' => true];
$fieldhiddenoptions = ['hidden' => true];
//echo "<pre>";print_r($this->item);echo "</pre>";exit();

$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();

echo LayoutHelper::render('ticket.ticket_edit_header', $this->item);
?>
<div class="ticket-edit front-end-edit">
    <form id="form-ticket"
        action="<?php echo Route::_('index.php?option=com_tickets&task=ticket.save'); ?>"
        method="post" class="form-validate form-horizontal" enctype="multipart/form-data">
        <div class="row">
            <div class="col-12">
                <div class="widget ticket-header-row">
                    <h1 class="ticket-header-16"><?php echo Text::_('COM_TICKETS_MASTERDATA_LABEL'); ?></h1>
                    <div class="container">
                        <?php
                        /**
                         * The master-data layouts (layouts/ticket/ticket/masterdata_*.php) live
                         * under the admin com_tickets component - shared with the admin ticket
                         * view rather than duplicated here, mirroring how this codebase already
                         * reuses Administrator-namespace classes (e.g. ExtensionUtilities) from
                         * Site code.
                         */
                        echo LayoutHelper::render(
                            $this->linkedItemLayout,
                            $this->linkedItemData,
                            JPATH_ADMINISTRATOR . '/components/com_tickets/layouts'
                        );
                        ?>
                    </div>
                </div>
                <div class="widget ticket-header-row">
                    <h1 class="ticket-header-16">Message History</h1>
                    <div class="container">
                        <div class="row">
                            <?php
                            $slidesOptions = ["active" => 'ticket_messages_group_slide0'];
                            echo HTMLHelper::_('bootstrap.startAccordion', 'ticket_messages_group', $slidesOptions);

                            $slideid = 0;
                            foreach ($this->messages as $ticketMessage) {
                                if ($ticketMessage->message_direction == 0) {
                                    $inout = "jed-ticket-message-out";
                                } else {
                                    $inout = "jed-ticket-message-in";
                                }

                                echo HTMLHelper::_('bootstrap.addSlide', 'ticket_messages_group', '<span class="' . $inout . '">' . $ticketMessage->subject . ' - ' . JedHelper::prettyDate($ticketMessage->created_on) . '</span>', 'ticket_messages_group' . '_slide' . ($slideid++), $ticketMessage->internal ? 'text-bg-danger' : '');
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
                                    <?php echo $this->form->renderFieldset('message'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="control-group">
                    <div class="controls">
                        <button type="submit" class="validate btn btn-primary">
                            <span class="fas fa-check" aria-hidden="true"></span>
                            <?php
                            echo Text::_('JSUBMIT'); ?>
                        </button>
                    </div>
                </div>

                <input type="hidden" name="option" value="com_tickets"/>
                <input type="hidden" name="task" value="ticket.save"/>
                <input type="hidden" name="id" value="<?php echo $this->item->id; ?>"/>
                <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
