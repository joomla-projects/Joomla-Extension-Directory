<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;

/**
 * Ticket model.
 *
 * @since 4.0.0
 */
class TicketModel extends AdminModel
{
    /**
     * Method to get an object.
     *
     * @param int $pk The id of the object to get.
     *
     * @return mixed    Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws \Exception
     */
    public function getItem($pk = null)
    {
        $itemId = (int) (!empty($itemId)) ? $itemId : $this->getState('ticket.id');

        // Get a row instance.
        $table = $this->getTable();
        $table->setUseExceptions(true);

        // Attempt to load the row.
        $return = $table->load($itemId);

        $properties = $table->getProperties(1);
        $value      = ArrayHelper::toObject($properties);

        return $value;
    }


    /**
     * Get an instance of Table class
     *
     * @param string $name
     * @param string $prefix  Prefix for the table class name. Optional.
     * @param array  $options
     *
     * @return Table|bool Table if success, false on failure.
     *
     * @since  4.0.0
     * @throws \Exception
     */
    public function getTable($name = 'Ticket', $prefix = 'Administrator', $options = []): Table|bool
    {
        return parent::getTable($name, $prefix, $options);
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
     * @throws \Exception
     */
    protected function populateState(): void
    {
        $app  = Factory::getApplication();
        $user = Factory::getApplication()->getIdentity();

        // Check published state
        if ((!$user->authorise('core.edit.state', 'com_jed')) && (!$user->authorise('core.edit', 'com_jed'))) {
            $this->setState('filter.published', 1);
            $this->setState('filter.archived', 2);
        }

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
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form  A Form object
     *
     * @since   1.6
     * @throws  \Exception on failure
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_tickets.ticket', 'ticket', ['control' => 'jform', 'load_data' => $loadData]);

        return $form;
    }

    /**
     * getMessages
     *
     * Returns the messages for the ticket
     *
     * @since 4.0
     */
    public function getMessages($pk = null)
    {
        if (empty($pk)) {
            $pk = $this->getState('ticket.id');
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('*')->from('#__jed_ticket_messages')->where($db->quoteName('ticket_id') . ' = ' . $db->quote($pk));

        if (!$this->getCurrentUser()->authorise('core.manage', 'com_tickets')) {
            $query->where($db->quoteName('internal') . ' = 0');
        }

        $db->setQuery($query);
        $messages = $db->loadObjectList();

        return $messages;
    }
}
