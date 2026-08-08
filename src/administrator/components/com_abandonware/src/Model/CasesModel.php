<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Abandonware\Administrator\Enum\CaseStatus;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * The JED team's case queue.
 *
 * The default filter is "open", not "all". A case list that opened on every case ever filed would
 * be a list of history with today's work somewhere inside it, and on a catalogue this size the
 * history wins on volume within a year.
 *
 * @since 4.0.0
 */
class CasesModel extends ListModel
{
    /**
     * @param array $config Configuration settings.
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'extension_name', 'a.extension_name',
                'status', 'a.status',
                'source', 'a.source',
                'assigned_to', 'a.assigned_to',
                'created', 'a.created',
                'grace_until', 'a.grace_until',
                'abandoned_time', 'a.abandoned_time',
                'published', 'a.published',
                'report_count',
            ];
        }

        parent::__construct($config);
    }

    /**
     * @param string $ordering  Default ordering column.
     * @param string $direction Default ordering direction.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function populateState($ordering = 'a.created', $direction = 'DESC'): void
    {
        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', ''));
        $this->setState('filter.status', $this->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', 'open'));
        $this->setState('filter.source', $this->getUserStateFromRequest($this->context . '.filter.source', 'filter_source', ''));
        $this->setState('filter.assigned', $this->getUserStateFromRequest($this->context . '.filter.assigned', 'filter_assigned', ''));

        parent::populateState($ordering, $direction);
    }

    /**
     * The state key has to include the filters, or Joomla serves a cached list from a different
     * filter set.
     *
     * @param string $id A prefix for the store id.
     *
     * @return string
     *
     * @since 4.0.0
     */
    protected function getStoreId($id = ''): string
    {
        return parent::getStoreId(
            $id . ':' . $this->getState('filter.search')
            . ':' . $this->getState('filter.status')
            . ':' . $this->getState('filter.source')
            . ':' . $this->getState('filter.assigned')
        );
    }

    /**
     * @return QueryInterface
     *
     * @since 4.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            [
                $db->quoteName('a.id'),
                $db->quoteName('a.extension_id'),
                $db->quoteName('a.extension_name'),
                $db->quoteName('a.extension_version'),
                $db->quoteName('a.status'),
                $db->quoteName('a.source'),
                $db->quoteName('a.assigned_to'),
                $db->quoteName('a.ticket_id'),
                $db->quoteName('a.contact_time'),
                $db->quoteName('a.grace_until'),
                $db->quoteName('a.abandoned_time'),
                $db->quoteName('a.resolution'),
                $db->quoteName('a.published'),
                $db->quoteName('a.created'),
                $db->quoteName('a.checked_out'),
                $db->quoteName('a.checked_out_time'),
                $db->quoteName('assignee.name', 'assignee_name'),
                $db->quoteName('e.name', 'listing_name'),
                $db->quoteName('e.blocked', 'listing_blocked'),
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__jed_abandonware_reports', 'r')
                . ' WHERE ' . $db->quoteName('r.case_id') . ' = ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('report_count'),
            ]
        )
            ->from($db->quoteName('#__jed_abandonware_cases', 'a'))
            ->leftJoin($db->quoteName('#__users', 'assignee') . ' ON ' . $db->quoteName('assignee.id') . ' = ' . $db->quoteName('a.assigned_to'))
            ->leftJoin($db->quoteName('#__jed_extensions', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('a.extension_id'));

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id);
            } else {
                $like = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' . $db->quoteName('a.extension_name') . ' LIKE :s1'
                    . ' OR ' . $db->quoteName('a.developer_name') . ' LIKE :s2'
                    . ' OR ' . $db->quoteName('a.extension_url') . ' LIKE :s3)'
                )
                    ->bind(':s1', $like)
                    ->bind(':s2', $like)
                    ->bind(':s3', $like);
            }
        }

        $status = (string) $this->getState('filter.status');

        if ($status === 'open' || $status === '') {
            $open = array_map(static fn (CaseStatus $s): string => $s->value, CaseStatus::open());
            $query->whereIn($db->quoteName('a.status'), $open, ParameterType::STRING);
        } elseif ($status === 'closed') {
            $closed = [CaseStatus::RESOLVED->value, CaseStatus::DISMISSED->value];
            $query->whereIn($db->quoteName('a.status'), $closed, ParameterType::STRING);
        } elseif ($status === 'awaiting_contact') {
            // The one filter the team needs most: cases sitting at step 2 with nobody written to.
            // Step 3 is the step 4.10 says gets skipped, and this is what makes skipping it visible.
            $pending = [CaseStatus::RECEIVED->value, CaseStatus::REVIEWING->value];
            $query->whereIn($db->quoteName('a.status'), $pending, ParameterType::STRING)
                ->where($db->quoteName('a.contact_time') . ' IS NULL');
        } elseif ($status !== 'all' && CaseStatus::tryFrom($status) !== null) {
            $query->where($db->quoteName('a.status') . ' = :status')->bind(':status', $status);
        }

        $source = (string) $this->getState('filter.source');

        if ($source !== '') {
            $query->where($db->quoteName('a.source') . ' = :source')->bind(':source', $source);
        }

        $assigned = (string) $this->getState('filter.assigned');

        if ($assigned === 'none') {
            $query->where('(' . $db->quoteName('a.assigned_to') . ' IS NULL OR ' . $db->quoteName('a.assigned_to') . ' = 0)');
        } elseif ($assigned !== '') {
            $assignedId = (int) $assigned;
            $query->where($db->quoteName('a.assigned_to') . ' = :assigned')->bind(':assigned', $assignedId);
        }

        $ordering  = $this->getState('list.ordering', 'a.created');
        $direction = $this->getState('list.direction', 'DESC');

        if (\in_array($ordering, $this->filter_fields, true)) {
            $query->order($db->escape($ordering) . ' ' . ($direction === 'ASC' ? 'ASC' : 'DESC'));
        }

        return $query;
    }
}
