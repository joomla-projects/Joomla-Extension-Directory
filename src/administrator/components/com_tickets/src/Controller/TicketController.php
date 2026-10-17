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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;

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
        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.edit', 'com_tickets')) {
            echo '||';
            $app->close();
        }

        $templateId = $app->getInput()->get('itemId', '', 'string');
        $db         = Factory::getContainer()->get('DatabaseDriver');

        $querySelect = $db->getQuery(true)
            ->select($db->qn(['subject', 'htmlbody', 'params']))
            ->from($db->quoteName('#__mail_templates'))
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
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.edit', 'com_tickets')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $jform   = $this->input->post->get('jform', [], 'array');
        $id      = (int) ($jform['id'] ?? 0);
        $subject = trim((string) InputFilter::getInstance()->clean($jform['message_subject'] ?? '', 'string'));
        $message = ComponentHelper::filterText((string) ($jform['message_text'] ?? ''));

        // The recipient is whoever opened the ticket, never a value posted with the form.
        $db        = Factory::getContainer()->get('DatabaseDriver');
        $createdBy = (int) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('created_by'))
                ->from($db->quoteName('#__jed_tickets'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
        )->loadResult();

        if ($id <= 0 || $createdBy <= 0 || $subject === '' || $message === '') {
            throw new Exception(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 400);
        }

        JedemailHelper::sendEmail($subject, $message, JedHelper::getUserById($createdBy), $user->name);
        $this->storeMessage($id, $subject, $message);
        $this->setRedirect(Route::_('index.php?option=com_tickets&view=ticket&layout=edit&id=' . $id, false));
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
