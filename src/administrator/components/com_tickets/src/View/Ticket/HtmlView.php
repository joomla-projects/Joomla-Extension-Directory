<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\View\Ticket;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Jed\Component\Tickets\Administrator\Model\TicketModel;
use Jed\Component\Tickets\Administrator\Ticket\TicketAction;
use Jed\Component\Tickets\Administrator\Ticket\TicketTypeHandlerRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use Joomla\CMS\Toolbar\ToolbarHelper;

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
     * linked item, used to look up COM_TICKETS_TICKETS_LINKED_ITEM_TYPE_OPTION_*.
     *
     * @var string
     *
     * @since 4.1.0
     */
    protected string $linked_item_type_name = 'Other';

    /**
     * A list of messages sent / received for this ticket
     *
     * @var mixed
     *
     * @since 4.0.0
     */
    protected mixed $ticket_messages;

    /**
     * The linked entity's master data, as loaded by the ticket-type handler
     * ({@see TicketTypeHandlerRegistry}). Null if the linked row no longer exists.
     *
     * @var object|null
     *
     * @since 4.1.0
     */
    protected ?object $linkedItemData = null;

    /**
     * The Joomla layout name that renders $linkedItemData.
     *
     * @var string
     *
     * @since 4.1.0
     */
    protected string $linkedItemLayout = 'ticket.masterdata_other';

    /**
     * Admin action buttons for the linked item (Approve, Delete, ...).
     *
     * @var TicketAction[]
     *
     * @since 4.1.0
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

        $canDo = JedHelper::getActions('com_tickets');

        ToolbarHelper::title(Text::_('COM_TICKETS_TITLE_TICKET'), "generic");

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
        $this->linked_item_type = $this->item->linked_item_type;
        $this->linked_item_id   = $this->item->linked_item_id;

        $registry = TicketTypeHandlerRegistry::createDefault(Factory::getDbo());
        $type     = TicketType::tryFrom((int) $this->linked_item_type) ?? TicketType::Other;
        $handler  = $registry->get($type);

        $this->linked_item_type_name = $type->name;
        $this->linkedItemData         = $handler->getMasterData((int) $this->linked_item_id);
        $this->linkedItemLayout       = $handler->getMasterDataLayout();
        $this->linkedItemActions      = $handler->getActions((int) $this->linked_item_id, $this->getCurrentUser());

        $this->addToolbar();

        parent::display($tpl);
    }
}
