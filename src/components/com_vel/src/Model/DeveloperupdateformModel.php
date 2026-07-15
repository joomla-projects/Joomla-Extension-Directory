<?php

/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Tickets\Site\Helper\TicketHelper;
use Jed\Component\Tickets\Site\Model\TicketformModel;
use Jed\Component\Tickets\Site\Model\TicketmessageformModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * VEL Developer Update Form Model Class.
 *
 * @since 4.0.0
 */
class DeveloperupdateformModel extends FormModel
{
    /**
     * The item object
     *
     * @var   mixed
     * @since 4.0.0
     */
    private mixed $item = null;

    /**
     * Data Table
     *
     * @since 4.0.0
     **/
    private string $dbtable = "#__jed_vel_developer_update";

    /**
     * Method to check in an item.
     *
     * @param int|null  $pk  The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    /*public function checkin($pk = null): bool
    {
        // Get the id.
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('developerupdate.id');
        if (!$pk || JedHelper::userIDItem($pk, $this->dbtable) || JedHelper::isAdminOrSuperUser())
        {
            if ($pk)
            {
                // Initialise the table
                $table = $this->getTable();

                // Attempt to check the row in.
                if (method_exists($table, 'checkin'))
                {
                    if (!$table->checkin($pk))
                    {
                        return false;
                    }
                }
            }

            return true;
        }
        else
        {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }*/

    /**
     * Method to check out an item for editing.
     *
     * @param int|null $pk The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function checkout($pk = null): bool
    {
        // Get the user id.
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('developerupdate.id');
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
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }

    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return mixed    A Form object on success, false on failure
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form
    {
        // Get the form.
        $form = $this->loadForm(
            'com_vel.developerupdate',
            'developerupdateform',
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
     * Method to get an object.
     *
     * @param null $id The id of the object to get.
     *
     * @return false|object|null
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem($id = null): mixed
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('developerupdate.id');
            }

            // Get a level row instance.
            $table = $this->getTable();

            if (isset($table) !== false && $table->load($id) && !empty($table->id)) {
                $table_data = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);
                $user       = Factory::getApplication()->getIdentity();
                $id         = $table->id;
                if (empty($id) || JedHelper::isAdminOrSuperUser() || $table_data->created_by == Factory::getApplication()->getIdentity()->id) {
                    $canEdit = $user->authorise('core.edit', 'com_vel') || $user->authorise('core.create', 'com_vel');

                    if (!$canEdit && $user->authorise('core.edit.own', 'com_vel')) {
                        $canEdit = $user->id == $table_data->created_by;
                    }

                    if (!$canEdit) {
                        throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
                    }

                    // Check published state.
                    if ($published = $this->getState('filter.published')) {
                        if (isset($table->state) && $table->state != $published) {
                            return $this->item;
                        }
                    }

                    // Convert the JTable to a clean JObject.
                    $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

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

    /**
     * Method to get the table
     *
     * @param string $name    Name of the JTable class
     * @param string $prefix  Optional prefix for the table class name
     * @param array  $options Optional configuration array for JTable object
     *
     * @return Table Table if found, throws exception on failure
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Developerupdate', $prefix = 'Administrator', $options = []): Table
    {

        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed  The default data is an empty array.
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_vel.edit.developerupdate.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {
            // Support for multiple or not foreign key field: consent_to_process
            $array = [];

            foreach ((array) $data->consent_to_process as $value) {
                if (!is_array($value)) {
                    $array[] = $value;
                }
            }
            if (!empty($array)) {
                $data->consent_to_process = $array;
            }
            // Support for multiple or not foreign key field: update_data_source
            $array = [];

            foreach ((array) $data->update_data_source as $value) {
                if (!is_array($value)) {
                    $array[] = $value;
                }
            }
            if (!empty($array)) {
                $data->update_data_source = $array;
            }

            return (array)$data;
        }

        return [];
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
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_vel.edit.developerupdate.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_vel.edit.developerupdate.id', $id);
        }

        $this->setState('developerupdate.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('developerupdate.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to delete data
     *
     * Commented out as Reports should not be deleted from front-end
     *
     * @param int  $pk  Item primary key
     *
     * @return int  The id of the deleted item
     *
     * @since  4.0.0
     * @throws Exception
     */
    /*public function delete($pk)
    {
        /*$user = Factory::getApplication()->getIdentity();

        if (!$pk || JedHelper::userIDItem($pk,$this->dbtable) || JedHelper::isAdminOrSuperUser())
        {
            if (empty($pk))
            {
                $pk = (int) $this->getState('developerupdate.id');
            }

            if ($pk == 0 || $this->getItem($pk) == null)
            {
                throw new Exception(Text::_('COM_VEL_ITEM_DOESNT_EXIST'), 404);
            }

            if ($user->authorise('core.delete', 'com_vel') !== true)
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
    } */

    /**
     * Method to save the form data.
     *
     * @param array $data The form data
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function save(array $data): bool
    {
        $id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('developerupdate.id');

        $data['user_ip'] = $_SERVER['REMOTE_ADDR'];
        $isLoggedIn      = JedHelper::isLoggedIn();
        $user            = Factory::getApplication()->getIdentity();

        if ((!$id || JedHelper::isAdminOrSuperUser()) && $isLoggedIn) {
            /* Any logged-in user can report a vulnerable Item */

            $table = $this->getTable();

            if ($table->save($data) === true) {
                $ticket                              = TicketHelper::createVELTicket(2, $table->id);
                $ticket_message                      = TicketHelper::createEmptyTicketMessage();
                $ticket_message['subject']           = $ticket['ticket_subject'];
                $ticket_message['message']           = $ticket['ticket_text'];
                $ticket_message['message_direction'] = 1; /*  1 for coming in, 0 for going out */

                $ticket_model = new TicketformModel();
                //$ticket_model = BaseDatabaseModel::getInstance('Ticketform', 'JedModel', ['ignore_request' => true]);
                $ticket_model->save($ticket);
                $ticket_id = $ticket_model->getId();
                /* We need to store the incoming ticket message */
                $ticket_message['ticket_id'] = $ticket_id;

                //$ticket_message_model = BaseDatabaseModel::getInstance('Ticketmessageform', 'JedModel', ['ignore_request' => true]);
                //$ticket_message_model = $this->getModel('Ticketmessageform', 'Site');
                $ticket_message_model = new TicketmessageformModel();

                $ticket_message_model->save($ticket_message);

                /* We need to email standard message to user and store message in ticket */
                $message_out = JedHelper::sendMailTemplate(TicketHelper::MAIL_TEMPLATE_TICKET_CONFIRMATION, $user);
                if ($message_out !== null) {
                    $ticket_message['id']                        = 0;
                    $ticket_message['subject']                   = $message_out->subject;
                    $ticket_message['message']                   = $message_out->htmlbody;
                    $ticket_message['message_direction']         = 0; /* 1 for coming in, 0 for going out */
                    $ticket_message['created_by']                = -1;
                    $ticket_message['modified_by']               = -1;
                    $ticket_message_model->save($ticket_message);
                }

                return $table->id;
            }
            return false;
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }
}
