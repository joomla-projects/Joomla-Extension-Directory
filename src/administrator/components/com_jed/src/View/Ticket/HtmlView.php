<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Ticket;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Checker\JedCheckerService;
use Jed\Component\Jed\Administrator\Enum\TicketType;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Model\TicketModel;
use Jed\Component\Jed\Administrator\Ticket\TicketAction;
use Jed\Component\Jed\Administrator\Ticket\TicketTypeHandlerRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * View class for a display of JED Ticket.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected Registry $state;
    protected mixed $item;
    protected mixed $form;

    /**
     * What type of object is linked to the ticket
     *
     * @var int
     *
     * @since 4.0.0
     */
    protected int $linked_item_type;

    /**
     * What id of object is linked to the ticket
     *
     * @var int
     *
     * @since 4.0.0
     */
    protected int $linked_item_id;

    /**
     * The resolved TicketType enum case name (e.g. "Extension", "Review") for the
     * linked item, used to look up COM_JED_TICKETS_LINKED_ITEM_TYPE_OPTION_*.
     *
     * @var string
     *
     * @since 4.0.0
     */
    protected string $linked_item_type_name = 'Other';

    /**
     * Index of the last inbound message, used to open that tab
     *
     * @var int
     *
     * @since 4.0.0
     */
    protected ?int $latest_ticket_message = null;

    /**
     * A list of messages sent / received for this ticket
     *
     * @var mixed
     *
     * @since 4.0.0
     */
    protected mixed $ticket_messages;

    /**
     * The JED team's internal notes on this ticket (team only, newest first).
     *
     * @var object[]
     *
     * @since 4.0.0
     */
    protected array $internal_notes = [];

    /**
     * The "Ticket Help" context: who opened the ticket and what else the JED knows about them.
     * See TicketModel::getTicketHelp().
     *
     * @var array
     *
     * @since 4.0.0
     */
    protected array $ticketHelp = [];

    /**
     * The listing the ticket is about, resolved through the ticket-type handler; null when the
     * ticket is not about one (a manual support ticket).
     *
     * @var int|null
     *
     * @since 4.0.0
     */
    protected ?int $extensionId = null;

    /**
     * The JED Checker tab: whether the checker is installed, what package it would check, the
     * last result this session produced for the ticket, and the latest JED audit-pipeline job
     * for the listing when the user may see audit results.
     *
     * @var array{available: bool, package: ?array, result: ?array, error: ?string, audit: ?object}
     *
     * @since 4.0.0
     */
    protected array $jedChecker = ['available' => false, 'package' => null, 'result' => null, 'error' => null, 'audit' => null];

    /**
     * Whether the current user is JED team and may see and write internal notes.
     *
     * @var bool
     *
     * @since 4.0.0
     */
    protected bool $canManage = false;

    /**
     * The linked entity's master data, as loaded by the ticket-type handler
     * ({@see TicketTypeHandlerRegistry}). Null if the linked row no longer exists.
     *
     * @var object|null
     *
     * @since 4.0.0
     */
    protected ?object $linkedItemData = null;

    /**
     * The Joomla layout name that renders $linkedItemData.
     *
     * @var string
     *
     * @since 4.0.0
     */
    protected string $linkedItemLayout = 'ticket.masterdata_other';

    /**
     * Admin action buttons for the linked item (Approve, Delete, ...).
     *
     * @var TicketAction[]
     *
     * @since 4.0.0
     */
    protected array $linkedItemActions = [];

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws \Exception
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user  = $this->getCurrentUser();
        $isNew = ($this->item->id == 0);

        if (isset($this->item->checked_out)) {
            $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->id);
        } else {
            $checkedOut = false;
        }

        $canDo = JedHelper::getActions('com_jed');

        ToolbarHelper::title(Text::_('COM_JED_TITLE_TICKET'), "generic");

        // If not checked out, can save the item.
        if (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create')))) {
            ToolbarHelper::apply('ticket.apply');
            ToolbarHelper::save('ticket.save');
        }

        if (!$checkedOut && ($canDo->get('core.create'))) {
            ToolbarHelper::custom(
                'ticket.save2new',
                'save-new.png',
                'save-new_f2.png',
                'JTOOLBAR_SAVE_AND_NEW',
                false
            );
        }

        // If an existing item, can save to a copy.
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::custom(
                'ticket.save2copy',
                'save-copy.png',
                'save-copy_f2.png',
                'JTOOLBAR_SAVE_AS_COPY',
                false
            );
        }

        if (empty($this->item->id)) {
            ToolbarHelper::cancel('ticket.cancel');
        } else {
            ToolbarHelper::cancel('ticket.cancel', 'JTOOLBAR_CLOSE');
        }
    }

    /**
     * Display the view
     *
     * @param string $tpl Template name
     *
     * @return void
     *
     * @since  4.0.0
     * @throws \Exception
     */
    public function display($tpl = null): void
    {
        /** @var TicketModel $model */
        $model                  = $this->getModel();
        $model->setUseExceptions(true);

        $this->state            = $model->getState();
        $this->item             = $model->getItem();
        $this->form             = $model->getForm();
        $this->ticket_messages  = $model->getTicketMessages();

        $this->latest_ticket_message = self::getLastInboundMessageIndex($this->ticket_messages);
        $this->canManage             = $this->getCurrentUser()->authorise('core.manage', 'com_jed');
        $this->internal_notes        = $this->canManage ? $model->getTicketInternalNotes() : [];
        $this->linked_item_type      = (int) ($this->item->linked_item_type ?? 0);
        $this->linked_item_id        = (int) ($this->item->linked_item_id ?? 0);

        $registry = TicketTypeHandlerRegistry::createDefault($model->getDB());
        $type     = TicketType::tryFrom((int) $this->linked_item_type) ?? TicketType::Other;
        $handler  = $registry->get($type);

        $this->linked_item_type_name  = $type->name;
        $this->linkedItemData         = $handler->getMasterData((int) $this->linked_item_id);
        $this->linkedItemLayout       = $handler->getMasterDataLayout();
        $this->linkedItemActions      = $handler->getActions((int) $this->linked_item_id, $this->getCurrentUser());
        $this->extensionId            = $handler->getExtensionId((int) $this->linked_item_id);

        if ($this->canManage && !empty($this->item->id)) {
            $this->ticketHelp = $model->getTicketHelp();
        }

        if ($this->canManage && $this->extensionId !== null) {
            $this->jedChecker = $model->loadJedChecker($this->extensionId, (int) $this->item->id);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Get the index of the latest inbound ticket message.
     *
     * @param array $messages List of ticket message objects.
     *
     * @return int|null Returns the array key/index of the last inbound message, or null if none found.
     */
    protected function getLastInboundMessageIndex(array $messages): ?int
    {
        // Preserve array keys while iterating in reverse order
        foreach (array_reverse($messages, true) as $index => $message) {
            if (isset($message->message_direction) && (int) $message->message_direction === 1) {
                return $index;
            }
        }

        return null;
    }
}
