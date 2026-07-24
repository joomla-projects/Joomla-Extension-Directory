<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects


use Exception;
use Jed\Component\Jed\Administrator\Helper\JedemailHelper;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * JED Ticket Controller class
 *
 * @since 4.0.0
 */
class TicketController extends FormController
{
    /**
     * A string showing the plural of the current object
     *
     * @var string
     *
     * @since 4.0.0
     */
    protected $view_list = 'tickets';


    /**
     * getTemplate
     *
     * function for ajax getting specific template
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTemplate()
    {
        //  Session::checkToken('post') or die;
        $app        = Factory::getApplication();
        $templateId = $app->getInput()->get('itemId', '', 'string');
        $db         = Factory::getContainer()->get('DatabaseDriver');

        $querySelect = $db->getQuery(true)
            ->select($db->qn(['subject', 'htmlbody', 'params']))
            ->from('#__mail_templates')
            ->where($db->qn('template_id') . ' = ' . $db->quote($templateId))
            ->where($db->qn('extension') . ' = ' . $db->quote('com_tickets'));
        $db->setQuery($querySelect);
        $result = $db->loadObject();

        if ($result === null) {
            echo '||';

            return;
        }

        $params       = json_decode((string) $result->params, true) ?: [];
        $ticketStatus = $params['ticket_status'] ?? '';

        echo $result->subject . '|' . $result->htmlbody . '|' . $ticketStatus;
    }

    /**
     * Ticket Send and Store Message
     *
     * @since  4.0.0
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function sendMessage()
    {
        $this->task = $_POST['task'];


        if ($this->task == "ticket.sendmessage") {
            /* Functionality
            1 - Verify
            2 - Send email with mesage
            3 - Store message to database
            4 - Redirect back to ticket so message history reloads
            */
            // var_dump($_POST['jform']);exit();
            $subject = $_POST['jform']['message_subject'];
            $message = $_POST['jform']['message_text'];

            $id          = $_POST['jform']['id'];
            $ticket_user = JedHelper::getUserById($_POST['jform']['created_by_num']);
            JedemailHelper::sendEmail($subject, $message, $ticket_user, 'mark@burninglight.co.uk');
            $this->storeMessage($id, $subject, $message);
            $this->setRedirect(Route::_('index.php?option=com_tickets&view=ticket&layout=edit&id=' . (int)$id, false));
        }
    }

    /**
     * Store Ticket Message back to database
     *
     * @param int $ticket_id
     * @param $subject
     * @param $message
     *
     * @since 4.0.0
     */
    public function storeMessage(int $ticket_id, $subject, $message)
    {
        $user                                = Factory::getApplication()->getIdentity();
        $ticket_message_model                = $this->getModel('Ticketmessage', 'Administrator');

        $ticket_message['id']                = 0;
        $ticket_message['ticket_id']         = $ticket_id;
        $ticket_message['subject']           = $subject;
        $ticket_message['message']           = $message;
        $ticket_message['message_direction'] = 0; /* 1 for coming in, 0 for going out */
        $ticket_message['created_by']        = $user->id;
        $ticket_message['created_on']        = 'now()';
        $ticket_message['modified_on']       = 'now()';

        $ticket_message_model->save($ticket_message);
    }
}
