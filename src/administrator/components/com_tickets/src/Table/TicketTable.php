<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Log\JedActionLog;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * Ticket table
 *
 * @since 4.0.0
 */
class TicketTable extends Table
{
    /**
     * The `ticket_status` values that mean the ticket is off the queue.
     *
     * Resolved counts as well as Closed: for the log the interesting fact is that somebody
     * decided the matter was finished, not which of the two words they picked.
     *
     * @var string[]
     *
     * @since 4.0.0
     */
    private const SETTLED_STATUSES = ['3', '4'];

    /**
     * Constructor
     *
     * @param DatabaseDriver $db A database connector object
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_jed.ticket';
        parent::__construct('#__jed_tickets', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see   Table::_getAssetName
     * @since 4.0.0
     */
    protected function _getAssetName(): string
    {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param array|object $src    An associative array or object to bind to the Table instance.
     * @param array|string $ignore An optional array or space separated list of properties to ignore while binding.
     *
     * @return bool  True on success.
     *
     * @see    Table:bind
     * @since  4.0.0
     * @throws Exception
     */
    public function bind($src, $ignore = '')
    {
        $date = Factory::getDate();
        $user = Factory::getApplication()->getIdentity();

        foreach (['ticket_origin', 'ticket_status'] as $field) {
            if (isset($src[$field]) && $src[$field]) {
                if (is_array($src[$field])) {
                    $src[$field] = implode(',', $src[$field]);
                }
            } else {
                $src[$field] = '';
            }
        }

        foreach (['ticket_category_type', 'allocated_group', 'linked_item_type'] as $field) {
            if (isset($src[$field]) && $src[$field]) {
                if (is_array($src[$field])) {
                    $src[$field] = implode(',', $src[$field]);
                }
            } else {
                $src[$field] = 0;
            }
        }

        $input = Factory::getApplication()->input;
        $task  = $input->getString('task', '');

        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = $user->id;
        }

        if ($src['id'] == 0) {
            $src['created_on'] = $date->toSql();
        }

        if ($src['id'] == 0 && empty($src['modified_by'])) {
            $src['modified_by'] = $user->id;
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified_by'] = $user->id;
            $src['modified_on'] = $date->toSql();
        }

        return parent::bind($src, $ignore);
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * Overridden to record the two ticket decisions `P1-22` asks for - who a ticket was handed
     * to, and when somebody declared it finished - in the core action log.
     *
     * Only changes to an existing ticket are considered. A ticket being *created* already says
     * everything it can say in its first message, and half the tickets in this system are opened
     * by machinery ({@see \Jed\Component\Tickets\Administrator\Traits\TicketHandlingTrait}) rather
     * than decided by anybody.
     *
     * @param bool $updateNulls True to update fields even if they are null.
     *
     * @return bool  True on success.
     *
     * @since 4.0.0
     */
    public function store($updateNulls = false): bool
    {
        $before = null;

        if ((int) $this->id > 0) {
            $db     = $this->getDatabase();
            $id     = (int) $this->id;
            $before = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['allocated_to', 'ticket_status']))
                    ->from($db->quoteName('#__jed_tickets'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $id, ParameterType::INTEGER)
            )->loadAssoc();
        }

        $result = parent::store($updateNulls);

        if ($result && $before !== null) {
            $this->logTicketDecisions($before);
        }

        return $result;
    }

    /**
     * Compare the row as it was with the row as it now is, and log what a person decided.
     *
     * @param array $before The `allocated_to` and `ticket_status` this ticket had a moment ago.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function logTicketDecisions(array $before): void
    {
        $ticketId = (int) $this->id;
        $subject  = (string) ($this->ticket_subject ?? '');
        $assignee = (int) ($this->allocated_to ?? 0);

        $wasAssignedTo = (int) ($before['allocated_to'] ?? 0);

        if ($assignee !== $wasAssignedTo) {
            if ($assignee > 0) {
                JedActionLog::record(JedActionLog::TICKET_ASSIGN, 'com_tickets.ticket', $ticketId, [
                    'title'        => $subject,
                    'assignee'     => $this->userName($assignee),
                    'assigneelink' => 'index.php?option=com_users&task=user.edit&id=' . $assignee,
                ]);
            } elseif ($wasAssignedTo > 0) {
                JedActionLog::record(JedActionLog::TICKET_UNASSIGN, 'com_tickets.ticket', $ticketId, [
                    'title'        => $subject,
                    'assignee'     => $this->userName($wasAssignedTo),
                    'assigneelink' => 'index.php?option=com_users&task=user.edit&id=' . $wasAssignedTo,
                ]);
            }
        }

        $was = (string) ($before['ticket_status'] ?? '');
        $now = (string) ($this->ticket_status ?? '');

        if ($was === $now) {
            return;
        }

        $wasSettled = \in_array($was, self::SETTLED_STATUSES, true);
        $nowSettled = \in_array($now, self::SETTLED_STATUSES, true);

        // Only the two crossings matter. Moving between "Awaiting User" and "Awaiting JED" is the
        // daily traffic of a queue, not a decision anybody will want to look up in a year.
        if (!$wasSettled && $nowSettled) {
            JedActionLog::record(JedActionLog::TICKET_CLOSE, 'com_tickets.ticket', $ticketId, [
                'title'  => $subject,
                'status' => $this->statusLabel($now),
            ]);
        } elseif ($wasSettled && !$nowSettled) {
            JedActionLog::record(JedActionLog::TICKET_REOPEN, 'com_tickets.ticket', $ticketId, [
                'title'  => $subject,
                'status' => $this->statusLabel($now),
            ]);
        }
    }

    /**
     * A user's name, falling back to their id when the account has gone.
     *
     * @param int $userId The account.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function userName(int $userId): string
    {
        try {
            return Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId)->name;
        } catch (Throwable) {
            return (string) $userId;
        }
    }

    /**
     * The word the ticket form uses for a status value.
     *
     * The language file is loaded explicitly: a ticket can be stored from outside the com_tickets
     * backend, and an untranslated key in the log would defeat the point of logging a word rather
     * than the number.
     *
     * The base path is the component folder, not `JPATH_ADMINISTRATOR`. The component keeps its
     * strings with itself rather than in `administrator/language`, and loading from the default
     * path finds nothing and reports success - which is how the first run of this produced
     * "set the ticket ... to COM_TICKETS_TICKETS_TICKET_STATUS_OPTION_4".
     *
     * @param string $status The `ticket_status` value.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function statusLabel(string $status): string
    {
        Factory::getApplication()->getLanguage()
            ->load('com_tickets', JPATH_ADMINISTRATOR . '/components/com_tickets');

        return Text::_('COM_TICKETS_TICKETS_TICKET_STATUS_OPTION_' . $status);
    }

    /**
     * Delete a record by id
     *
     * @param mixed $pk Primary key value to delete. Optional
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function delete($pk = null): bool
    {
        $this->load($pk);

        return parent::delete($pk);
    }

    /**
     * Get the type alias for the history table
     *
     * @return string  The alias as described above
     *
     * @since 4.0.0
     */
    public function getTypeAlias(): string
    {
        return $this->typeAlias;
    }
}
