<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Tickets\Administrator\Model\TicketmessageModel;
use Jed\Component\Tickets\Site\Model\TicketModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;

/**
 * Ticket class.
 *
 * @since 4.0.0
 */
class TicketController extends BaseController
{
    /**
     * Method to save a new message on an existing ticket, using the "message"/"internal" fields
     * defined in forms/ticket.xml, persisted into #__jed_ticket_messages.
     *
     * Only a user with core.manage on com_tickets, or the ticket's own creator (#__jed_tickets.
     * created_by), may post a message. A user without core.manage can never mark a message as
     * internal - that flag is always forced to 0 for them, regardless of what was submitted.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function save(): void
    {
        $this->checkToken();

        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        $ticketId = $app->getInput()->getInt('id', 0);

        if (!$ticketId) {
            throw new Exception(Text::_('COM_TICKETS_ITEM_DOESNT_EXIST'), 404);
        }

        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['created_by', 'ticket_subject']))
            ->from($db->quoteName('#__jed_tickets'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $ticketId, ParameterType::INTEGER);
        $ticket = $db->setQuery($query)->loadObject();

        if (!$ticket) {
            throw new Exception(Text::_('COM_TICKETS_ITEM_DOESNT_EXIST'), 404);
        }

        $canManage = $user->authorise('core.manage', 'com_tickets');

        if (!$canManage && (int) $ticket->created_by !== (int) $user->id) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var TicketModel $ticketModel */
        $ticketModel = $this->getModel('Ticket', 'Site');
        $data        = $app->getInput()->post->get('jform', [], 'array');
        $form        = $ticketModel->getForm($data, false);

        if (!$form) {
            throw new Exception(Text::_('JERROR_LOADFILE_FAILED'), 500);
        }

        $validatedData = $ticketModel->validate($form, $data);

        if ($validatedData === false) {
            $this->setMessage(Text::_('JGLOBAL_ERROR_SAVE_FAILED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_tickets&view=ticket&id=' . $ticketId, false));

            return;
        }

        $messageModel = new TicketmessageModel();

        $messageData = [
            'id'                => 0,
            'ticket_id'         => $ticketId,
            'subject'           => 'Re: ' . $ticket->ticket_subject,
            'message'           => $validatedData['message'] ?? '',
            'message_direction' => $canManage ? 0 : 1,
            'internal'          => $canManage ? (int) ($validatedData['internal'] ?? 0) : 0,
            'created_by'        => $user->id,
            'created_on'        => date('Y-m-d H:i:s'),
        ];

        if (!$messageModel->save($messageData)) {
            $this->setMessage(Text::_('JGLOBAL_ERROR_SAVE_FAILED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_tickets&view=ticket&id=' . $ticketId, false));

            return;
        }

        $this->setMessage(Text::_('COM_TICKETS_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));
        $this->setRedirect(Route::_('index.php?option=com_tickets&view=ticket&id=' . $ticketId, false));
    }
}
