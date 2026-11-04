<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Checker\JedCheckerService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;

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

        // Null on a ticket that is being created - the typed properties need an int.
        $this->linked_item_type = (int) ($item->linked_item_type ?? 0);
        $this->linked_item_id   = (int) ($item->linked_item_id ?? 0);
        $this->ticket_creator   = (int) ($item->created_by ?? 0);

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
     * The JED team's internal notes on this ticket, newest first.
     *
     * For the team only: the site ticket view never asks for these, and a note is never mailed.
     * The ticket's own `internal_notes` column is a separate thing - the single block of free text
     * the JED3 migration carried across for each legacy ticket - and is shown alongside them.
     *
     * @return object[]
     *
     * @since 4.0.0
     */
    public function getTicketInternalNotes(): array
    {
        $ticketId = (int) ($this->item->id ?? 0);

        if ($ticketId <= 0) {
            return [];
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('a.*')
            ->select($db->quoteName('u.name', 'author_name'))
            ->from($db->quoteName('#__jed_ticket_internal_notes', 'a'))
            ->leftJoin($db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'))
            ->where($db->quoteName('a.ticket_id') . ' = :ticketId')
            ->bind(':ticketId', $ticketId, ParameterType::INTEGER)
            ->order($db->quoteName('a.created_on') . ' DESC, ' . $db->quoteName('a.id') . ' DESC');

        return (array) $db->setQuery($query)->loadObjectList();
    }

    /**
     * Everything the JED already knows about the person who opened the ticket - the "Ticket
     * Help" tab.
     *
     * A moderator reading "please publish my extension" wants the rest of the picture next to
     * it: who this is, what else they have listed, what they have reviewed, what they have
     * asked before, and whether the team has already decided something about them. JED 5.3
     * had this tab over the legacy tables (and a hard-coded sample user, for want of data);
     * this is the same idea over the JED4 tables, including the privilege and ban record P1-05
     * added and the migrated legacy tickets.
     *
     * Team-only, like everything on the admin ticket screen: the e-mail address and the ban
     * record are personal data and go no further than here.
     *
     * @return array{
     *     creator: \Joomla\CMS\User\User,
     *     access: ?object,
     *     extensions: object[],
     *     reviews: object[],
     *     tickets: object[]
     * }
     *
     * @since 4.0.0
     */
    public function getTicketHelp(): array
    {
        $creatorId = (int) ($this->item->created_by ?? 0);
        $ticketId  = (int) ($this->item->id ?? 0);
        $db        = $this->getDatabase();

        $creator = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($creatorId);

        $access = $creatorId > 0 ? $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__jed_user_access'))
                ->where($db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $creatorId, ParameterType::INTEGER)
        )->loadObject() : null;

        // Owned or created: the owner is the person answerable for a listing, created_by is
        // who typed it in, and a ticket can be about either relationship.
        $extensions = $creatorId > 0 ? (array) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['a.id', 'a.name', 'a.extension_version', 'a.state', 'a.approved', 'a.blocked', 'a.deleted', 'a.created', 'a.modified', 'a.owner']))
                ->from($db->quoteName('#__jed_extensions', 'a'))
                ->where('(' . $db->quoteName('a.owner') . ' = :owner OR ' . $db->quoteName('a.created_by') . ' = :creator)')
                ->bind([':owner', ':creator'], $creatorId, ParameterType::INTEGER)
                ->order($db->quoteName('a.modified') . ' DESC, ' . $db->quoteName('a.id') . ' DESC')
        )->loadObjectList() : [];

        $reviews = $creatorId > 0 ? (array) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['r.id', 'r.title', 'r.overall_score', 'r.state', 'r.created_on', 'r.extension_id']))
                ->select($db->quoteName('e.name', 'extension_name'))
                ->from($db->quoteName('#__jed_reviews', 'r'))
                ->leftJoin($db->quoteName('#__jed_extensions', 'e'), $db->quoteName('e.id') . ' = ' . $db->quoteName('r.extension_id'))
                ->where($db->quoteName('r.created_by') . ' = :uid')
                ->bind(':uid', $creatorId, ParameterType::INTEGER)
                ->order($db->quoteName('r.created_on') . ' DESC')
        )->loadObjectList() : [];

        $tickets = $creatorId > 0 ? (array) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['t.id', 't.ticket_subject', 't.ticket_status', 't.created_on', 't.linked_item_type']))
                ->select($db->quoteName('c.categorytype', 'categorytype_string'))
                ->from($db->quoteName('#__jed_tickets', 't'))
                ->leftJoin($db->quoteName('#__jed_ticket_categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('t.ticket_category_type'))
                ->where($db->quoteName('t.created_by') . ' = :uid')
                ->where($db->quoteName('t.id') . ' <> :ticketId')
                ->bind(':uid', $creatorId, ParameterType::INTEGER)
                ->bind(':ticketId', $ticketId, ParameterType::INTEGER)
                ->order($db->quoteName('t.created_on') . ' DESC')
        )->loadObjectList() : [];

        foreach ($tickets as $ticket) {
            $ticket->ticket_status_label = Text::_('COM_JED_TICKETS_TICKET_STATUS_OPTION_' . strtoupper((string) $ticket->ticket_status));
        }

        return [
            'creator'    => $creator,
            'access'     => $access,
            'extensions' => $extensions,
            'reviews'    => $reviews,
            'tickets'    => $tickets,
        ];
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

    /**
     * What the JED Checker tab shows for a listing.
     *
     * The check itself never runs on display - it unpacks and reads a whole package, which is
     * not something a page load should do - but on the `ticket.jedcheck` task, which stores its
     * result in the session under this ticket's id. This reads that back.
     *
     * @param int $extensionId The listing.
     * @param int $ticketId    The ticket.
     *
     * @return array{available: bool, package: ?array, result: ?array, error: ?string, audit: ?object}
     *
     * @since 4.0.0
     */
    public function loadJedChecker(int $extensionId, int $ticketId): array
    {
        $app     = Factory::getApplication();
        $service = new JedCheckerService($this->getDatabase());
        $stored  = (array) $app->getUserState('com_jed.ticket.jedcheck.' . $ticketId, []);

        $audit = null;

        if ($this->getCurrentUser()->authorise('jed.audit.view', 'com_jed')) {
            $db    = $this->getDatabase();
            $type  = 'extension.audit';
            $audit = $db->setQuery(
                $db->getQuery(true)
                            ->select($db->quoteName(['id', 'status', 'created', 'finished_time', 'last_error', 'result_meta', 'payload']))
                            ->from($db->quoteName('#__jed_queue_jobs'))
                            ->where($db->quoteName('type') . ' = :type')
                            ->where($db->quoteName('extension_id') . ' = :id')
                            ->bind(':type', $type)
                            ->bind(':id', $extensionId, ParameterType::INTEGER)
                            ->order($db->quoteName('id') . ' DESC')
                            ->setLimit(1)
            )->loadObject();
        }

        return [
                'available' => $service->isAvailable(),
                'package'   => $service->describePackage($extensionId),
                'result'    => isset($stored['result']) && \is_array($stored['result']) ? $stored['result'] : null,
                'error'     => isset($stored['error']) ? (string) $stored['error'] : null,
                'audit'     => $audit,
        ];
    }

    public function getDB(): DatabaseInterface
    {
        return $this->getDatabase();
    }
}
