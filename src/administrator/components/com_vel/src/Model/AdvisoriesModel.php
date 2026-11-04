<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\Severity;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * The advisory list.
 *
 * @since 4.0.0
 */
class AdvisoriesModel extends ListModel
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
                'title', 'a.title',
                'state', 'a.state',
                'status', 'a.status',
                'severity', 'a.severity',
                'cvss_score', 'a.cvss_score',
                'cve_id', 'a.cve_id',
                'date_published', 'a.date_published',
                'date_updated', 'a.date_updated',
                'modified', 'a.modified',
                'created', 'a.created',
                'source', 'a.source',
                'assigned_to', 'a.assigned_to',
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
    protected function populateState($ordering = 'a.modified', $direction = 'DESC'): void
    {
        foreach (['search', 'state', 'status', 'severity', 'cve', 'source', 'assigned'] as $filter) {
            $this->setState('filter.' . $filter, $this->getUserStateFromRequest($this->context . '.filter.' . $filter, 'filter_' . $filter, ''));
        }

        parent::populateState($ordering, $direction);
    }

    /**
     * @param string $id A prefix for the store id.
     *
     * @return string
     *
     * @since 4.0.0
     */
    protected function getStoreId($id = ''): string
    {
        foreach (['search', 'state', 'status', 'severity', 'cve', 'source', 'assigned'] as $filter) {
            $id .= ':' . $this->getState('filter.' . $filter);
        }

        return parent::getStoreId($id);
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
                $db->quoteName('a.title'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.state'),
                $db->quoteName('a.status'),
                $db->quoteName('a.severity'),
                $db->quoteName('a.cvss_score'),
                $db->quoteName('a.cvss_version'),
                $db->quoteName('a.cve_id'),
                $db->quoteName('a.cve_state'),
                $db->quoteName('a.source'),
                $db->quoteName('a.date_published'),
                $db->quoteName('a.date_updated'),
                $db->quoteName('a.date_public'),
                $db->quoteName('a.created'),
                $db->quoteName('a.modified'),
                $db->quoteName('a.assigned_to'),
                $db->quoteName('a.checked_out'),
                $db->quoteName('a.checked_out_time'),
                $db->quoteName('assignee.name', 'assignee_name'),
                $db->quoteName('editor.name', 'editor_name'),
                // The products as one column, so the list does not query per row.
                '(SELECT GROUP_CONCAT(DISTINCT ' . $db->quoteName('p.vendor') . ' SEPARATOR ", ") FROM '
                . $db->quoteName('#__vel_advisory_products', 'p') . ' WHERE ' . $db->quoteName('p.advisory_id') . ' = ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('vendors'),
                '(SELECT GROUP_CONCAT(' . $db->quoteName('p2.product') . ' ORDER BY ' . $db->quoteName('p2.ordering') . ' SEPARATOR ", ") FROM '
                . $db->quoteName('#__vel_advisory_products', 'p2') . ' WHERE ' . $db->quoteName('p2.advisory_id') . ' = ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('products'),
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__vel_reports', 'r')
                . ' WHERE ' . $db->quoteName('r.advisory_id') . ' = ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('report_count'),
            ]
        )
            ->from($db->quoteName('#__vel_advisories', 'a'))
            ->leftJoin($db->quoteName('#__users', 'assignee'), $db->quoteName('assignee.id') . ' = ' . $db->quoteName('a.assigned_to'))
            ->leftJoin($db->quoteName('#__users', 'editor'), $db->quoteName('editor.id') . ' = ' . $db->quoteName('a.checked_out'));

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            } elseif (preg_match('/^VEL-?0*(\d+)$/i', $search, $m)) {
                $id = (int) $m[1];
                $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            } elseif (preg_match('/^CVE-\d{4}-\d+$/i', $search)) {
                $cve = strtoupper($search);
                $query->where($db->quoteName('a.cve_id') . ' = :cve')->bind(':cve', $cve);
            } else {
                $like = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' . $db->quoteName('a.title') . ' LIKE :s1'
                    . ' OR ' . $db->quoteName('a.cve_id') . ' LIKE :s2'
                    . ' OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__vel_advisory_products', 'ps')
                    . ' WHERE ' . $db->quoteName('ps.advisory_id') . ' = ' . $db->quoteName('a.id')
                    . ' AND (' . $db->quoteName('ps.vendor') . ' LIKE :s3 OR ' . $db->quoteName('ps.product') . ' LIKE :s4 OR ' . $db->quoteName('ps.element') . ' LIKE :s5)))'
                )
                    ->bind(':s1', $like)
                    ->bind(':s2', $like)
                    ->bind(':s3', $like)
                    ->bind(':s4', $like)
                    ->bind(':s5', $like);
            }
        }

        $state = (string) $this->getState('filter.state');

        if ($state === 'working') {
            $query->whereIn($db->quoteName('a.state'), [AdvisoryState::DRAFT->value, AdvisoryState::READY->value], ParameterType::STRING);
        } elseif (AdvisoryState::tryFrom($state) !== null) {
            $query->where($db->quoteName('a.state') . ' = :state')->bind(':state', $state);
        }

        $status = (string) $this->getState('filter.status');

        if (AdvisoryStatus::tryFrom($status) !== null) {
            $query->where($db->quoteName('a.status') . ' = :status')->bind(':status', $status);
        }

        $severity = (string) $this->getState('filter.severity');

        if ($severity === 'unset') {
            $query->where($db->quoteName('a.severity') . ' IS NULL');
        } elseif (Severity::tryFrom($severity) !== null) {
            $query->where($db->quoteName('a.severity') . ' = :severity')->bind(':severity', $severity);
        }

        $cve = (string) $this->getState('filter.cve');

        if ($cve === 'yes') {
            $query->where($db->quoteName('a.cve_id') . ' IS NOT NULL');
        } elseif ($cve === 'no') {
            $query->where($db->quoteName('a.cve_id') . ' IS NULL');
        }

        $source = (string) $this->getState('filter.source');

        if ($source !== '') {
            $query->where($db->quoteName('a.source') . ' = :source')->bind(':source', $source);
        }

        $assigned = (string) $this->getState('filter.assigned');

        if ($assigned === 'none') {
            $query->where('(' . $db->quoteName('a.assigned_to') . ' IS NULL OR ' . $db->quoteName('a.assigned_to') . ' = 0)');
        } elseif ($assigned === 'me') {
            $me = (int) Factory::getApplication()->getIdentity()->id;
            $query->where($db->quoteName('a.assigned_to') . ' = :me')->bind(':me', $me, ParameterType::INTEGER);
        } elseif ($assigned !== '') {
            $assignedId = (int) $assigned;
            $query->where($db->quoteName('a.assigned_to') . ' = :assigned')->bind(':assigned', $assignedId, ParameterType::INTEGER);
        }

        $ordering  = $this->getState('list.ordering', 'a.modified');
        $direction = $this->getState('list.direction', 'DESC');

        if (\in_array($ordering, $this->filter_fields, true)) {
            $query->order($db->escape($ordering) . ' ' . ($direction === 'ASC' ? 'ASC' : 'DESC'));
        }

        return $query;
    }
}
