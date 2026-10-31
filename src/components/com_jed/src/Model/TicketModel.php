<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Enum\TicketType;
use Jed\Component\Jed\Administrator\Ticket\TicketTypeHandlerInterface;
use Jed\Component\Jed\Administrator\Ticket\TicketTypeHandlerRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
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
        $pk = (int) (!empty($pk)) ? $pk : $this->getState('ticket.id');

        // Get a row instance.
        $table = $this->getTable();
        $table->setUseExceptions(true);

        // Attempt to load the row.
        $table->load($pk);

        $properties = $table->getProperties(1);
        $value      = ArrayHelper::toObject($properties);

        // The header layout (layouts/ticket/ticket_edit_header.php) prints these as words, not
        // as the numbers the table stores them as - the same translation TicketformModel applies.
        $value->ticket_status        = Text::_('COM_JED_TICKETS_TICKET_STATUS_OPTION_' . strtoupper((string) $value->ticket_status));
        $value->ticket_category_type = $this->getTicketCategory((int) $value->ticket_category_type);

        return $value;
    }

    /**
     * The category type name for a ticket category id.
     *
     * @param int $categoryId The `#__jed_ticket_categories` id.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function getTicketCategory(int $categoryId): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('categorytype'))
            ->from($db->quoteName('#__jed_ticket_categories'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $categoryId, ParameterType::INTEGER);

        return (string) $db->setQuery($query)->loadResult();
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
        $form = $this->loadForm('com_jed.ticket', 'ticket', ['control' => 'jform', 'load_data' => $loadData]);

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

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('*')->from($db->quoteName('#__jed_ticket_messages'))->where($db->quoteName('ticket_id') . ' = ' . $db->quote($pk));

        if (!$this->getCurrentUser()->authorise('core.manage', 'com_jed')) {
            $query->where($db->quoteName('internal') . ' = 0');
        }

        $db->setQuery($query);
        $messages = $db->loadObjectList();

        return $messages;
    }

    /**
     * Method to get the record form.
     *
     * @param   mixed  $item  Object, false or null. False or null will try and re-retrieve the item.
     *
     * @return  TicketTypeHandlerInterface  A TicketTypeHandlerInterface object
     *
     * @since   4.0.0
     * @throws  \Exception on failure
     */
    public function getTicketTypeHandler($item = null): TicketTypeHandlerInterface
    {
        if (!$item) {
            $item = $this->getItem();
        }
        $registry = TicketTypeHandlerRegistry::createDefault($this->getDatabase());
        $type     = TicketType::tryFrom((int) ($item->linked_item_type ?? 0)) ?? TicketType::Other;

        return $registry->get($type);
    }
}
