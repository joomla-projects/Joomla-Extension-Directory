<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Registry\Registry;
use Joomla\CMS\Table\Table;

/**
 * JED Ticket model.
 *
 * @since 4.0.0
 */
class TicketModel extends AdminModel
{
    /**
     * @var   string    Alias to manage history control
     * @since 4.0.0
     */
    public $typeAlias = 'com_jed.ticket';
    /**
     * @var   string    The prefix to use with controller messages.
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_JED';
    /**
     * @var   null  Item data
     * @since 4.0.0
     */
    protected $item = null;

    /**
     * @var   int  Linked Item Type
     * @since 4.0.0
     */
    protected int $linked_item_type;
    /**
     * @var   int  Id of linked Item
     * @since 4.0.0
     */
    protected int $linked_item_id;
    /**
     * @var   int  User Id of ticket creator
     * @since 4.0.0
     */
    protected int $ticket_creator;

    /**
     * Method to get the record form.
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form|bool  A Form object on success, false on failure
     *
     * @since 4.0.0
     *
     * @throws
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form
    {
        // Get the form.
        $form = $this->loadForm(
            'com_jed.ticket',
            'ticket',
            ['control'        => $formname,
                  'load_data' => $loadData,
            ]
        );


        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get a single record.
     *
     * @param null $pk The id of the primary key.
     *
     * @return mixed Object on success
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem($pk = null): mixed
    {

        $pk   = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');
        $item = parent::getItem($pk);

        $this->linked_item_type = $item->linked_item_type;
        $this->linked_item_id   = $item->linked_item_id;
        $this->ticket_creator   = $item->created_by;

        return $item;
    }

    /**
     * Returns a reference to a Table object, always creating it.
     *
     * @param string $name
     * @param string $prefix  A prefix for the table class name. Optional.
     * @param array  $options
     *
     * @return Table    A database object
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Ticket', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed  The data for the form.
     *
     * @since 4.0.0
     *
     * @throws
     */
    protected function loadFormData(): mixed
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jed.edit.ticket.data', []);

        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;
        }

        return $data;
    }

    /**
     * Method to get Ticket Messages
     *
     * @retun array|bool    An array on success, false on failure
     *
     * @since 4.0.0
     */
    public function getTicketMessages(): array
    {
        // Create a new query object.
        $db = $this->getDatabase();

        $query = $db->getQuery(true);

        // Select some fields
        $query->select('a.*');

        // From the jed_ticket_messages table
        $query->from($db->quoteName('#__jed_ticket_messages', 'a'));

        // Filter by Ticket Id

        $ticketId = $this->item->id;
        if (is_numeric($ticketId)) {
            $query->where('a.ticket_id = ' . (int) $ticketId);
        } elseif (is_string($ticketId)) {
            $query->where('a.ticket_id = ' . $db->quote($ticketId));
        } else {
            $query->where('a.ticket_id = -5');
        }


        // Load the items
        $db->setQuery($query);
        return $db->loadObjectList();
    }

}
