<?php

/**
 * @package       JED
 *
 * @subpackage    TICKETS
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedemailHelper;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * TicketForm model.
 *
 * @since 4.0.0
 */
class TicketformModel extends FormModel
{
    /**
     * The item object
     *
     * @var   object
     * @since 4.0.0
     */
    private $item = null;

    /**
     * Data Table
     *
     * @var   string
     * @since 4.0.0
     **/
    private string $dbtable = "#__jed_tickets";
    /**
     * Default ticket id
     *
     * @var   int
     * @since 4.0.0
     **/
    private int $id = -1;

    /**
     * Method to check in an item.
     *
     * @param   int  $pk  The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function checkin($pk = null): bool
    {
        // Get the id.
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('ticket.id');
        if (!$pk || JedHelper::userIDItem($pk, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($pk) {
                // Initialise the table
                $table = $this->getTable();

                // Attempt to check the row in.
                if (method_exists($table, 'checkin')) {
                    if (!$table->checkin($pk)) {
                        return false;
                    }
                }
            }

            return true;
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }

    /**
     * Method to check out an item for editing.
     *
     * @param   int|null  $pk  The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function checkout($pk = null): bool
    {
        // Get the user id.
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('ticket.id');
        if (!$pk || JedHelper::userIDItem($pk, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($pk) {
                // Initialise the table
                $table = $this->getTable();

                // Get the current user object.
                $user = Factory::getApplication()->getIdentity();

                // Attempt to check the row out.
                if (method_exists($table, 'checkout')) {
                    if (!$table->checkout($user->id, $pk)) {
                        return false;
                    }
                }
            }

            return true;
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }

    /**
     * Check if data can be saved
     *
     * @return bool
     * @since  4.0.0
     * @throws Exception
     */
    public function getCanSave(): bool
    {
        $table = $this->getTable();

        return $table !== false;
    }

    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param   array  $data      An optional array of data for the form to interogate.
     * @param   bool   $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return Form    A Form object on success, false on failure
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form
    {
        // Get the form.
        $form = $this->loadForm(
            'com_jed.ticket',
            'ticketform',
            [
                'control'   => $formname,
                'load_data' => $loadData,
            ]
        );

        if (!is_object($form)) {
            throw new Exception(Text::_('JERROR_LOADFILE_FAILED'), 500);
        }


        return $form;
    }

    /**
     *
     * @return int
     *
     * @since version
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Method to get an object.
     *
     * @param   int|null  $id  The id of the object to get.
     *
     * @return mixed Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem(int $id = null)
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('ticket.id');
            }

            // Get a level row instance.
            $table = $this->getTable();

            $properties = $table->getTableProperties();
            $this->item = ArrayHelper::toObject($properties, stdClass::class);

            if ($table !== false && $table->load($id) && !empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();
                $id   = $table->id;
                if (empty($id) || JedHelper::isAdminOrSuperUser() || $table->created_by == $user->id) {
                    // Convert the Table to a clean CMSObject.
                    $properties                       = $table->getTableProperties(1);
                    $this->item                       = ArrayHelper::toObject($properties, stdClass::class);
                    $this->item->ticket_messages      = self::getTicketMessages($id);
                    $this->item->ticket_status        = Text::_('COM_JED_TICKETS_TICKET_STATUS_OPTION_' . strtoupper($this->item->ticket_status));
                    $this->item->ticket_category_type = self::getTicketCategory($this->item->ticket_category_type);
                    if (isset($this->item->category_id) && is_object($this->item->category_id)) {
                        $this->item->category_id = ArrayHelper::fromObject($this->item->category_id);
                    }
                } else {
                    throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
                }
            }
        }


        return $this->item;
    }

    public function getTicketMessages($ticketId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('*')->from('#__jed_ticket_messages')->where('ticket_id = ' . $db->quote($ticketId));

        return $db->setQuery($query)->loadObjectList();
    }

    /**
     * Method to delete data
     *
     * @param   int  $pk  Item primary key
     *
     * @return int  The id of the deleted item
     *
     * @since  4.0.0
     * @throws Exception
     */
    /*public function delete($pk) : int
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$pk || JedHelper::userIDItem($pk, $this->dbtable) || JedHelper::isAdminOrSuperUser())
        {
            if (empty($pk))
            {
                $pk = (int) $this->getState('ticket.id');
            }

            if ($pk == 0 || $this->getItem($pk) == null)
            {
                throw new Exception(Text::_('COM_JED_ITEM_DOESNT_EXIST'), 404);
            }

            if ($user->authorise('core.delete', 'com_jed') !== true)
            {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $table = $this->getTable();

            if ($table->delete($pk) !== true)
            {
                throw new Exception(Text::_('JERROR_FAILED'), 501);
            }

            return $pk;
        }
        else
        {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }*/

    /**
     * Method to get the table
     *
     * @param   string  $name
     * @param   string  $prefix  Optional prefix for the table class name
     * @param   array   $options
     *
     * @return Table|bool Table if found, bool false on failure
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Ticket', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return array  The default data is an empty array.
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jed.edit.ticket.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }


        return $data;
    }

    /**
     * Method to autopopulate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_jed.edit.ticket.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_jed.edit.ticket.id', $id);
        }

        $this->setState('ticket.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('ticket.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function save(array $data): bool
    {
        $isLoggedIn      = JedHelper::isLoggedIn();
        $data['user_ip'] = $_SERVER['REMOTE_ADDR'];
        $user            = Factory::getApplication()->getIdentity();
        if ($isLoggedIn) {
            /* Any logged-in user can make a new ticket */
            $table = $this->getTable();

            if ($table->save($data) === true) {
                $this->id                            = $table->id;
                $ticket_message                      = JedHelper::createEmptyTicketMessage();
                $ticket_message['subject']           = $data['ticket_subject'];
                $ticket_message['message']           = $data['ticket_text'];
                $ticket_message['message_direction'] = 1; /*  1 for coming in, 0 for going out */
                $ticket_message['ticket_id']         = $this->id;
                $ticket_message_model                = new TicketmessageformModel();
                $ticket_message_model->save($ticket_message);
                /* We need to email standard message to user and store message in ticket */
                $message_out = JedHelper::getMessageTemplate(1000);
                if (isset($message_out->subject)) {
                    JedemailHelper::sendEmail($message_out->subject, $message_out->template, $user, 'dummy@dummy.com');

                    $ticket_message['id']                = 0;
                    $ticket_message['subject']           = $message_out->subject;
                    $ticket_message['message']           = $message_out->template;
                    $ticket_message['message_direction'] = 0; /* 1 for coming in, 0 for going out */
                    $ticket_message['created_by']        = -1;
                    $ticket_message['modified_by']       = -1;
                    $ticket_message_model->save($ticket_message);
                }

                return $table->id;
            } else {
                echo "can't save";

                return false;
            }
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }

    public function getTicketCategory($categoryId): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('categorytype')->from('#__jed_ticket_categories')->where('id = ' . $db->quote($categoryId));

        return $db->setQuery($query)->loadResult();
    }
}
