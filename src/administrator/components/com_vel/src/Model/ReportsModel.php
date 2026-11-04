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
use Jed\Component\Vel\Administrator\Enum\ReportState;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * The intake queue (VEL2-PRD §6.3.1).
 *
 * Reporter identity is selected here because this is the backend and triage needs it; the
 * template hides it from anybody without `vel.communicate`.
 *
 * @since 4.0.0
 */
class ReportsModel extends ListModel
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
                'state', 'a.state',
                'kind', 'a.kind',
                'extension_name', 'a.extension_name',
                'created', 'a.created',
                'channel', 'a.channel',
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
        $this->setState('filter.state', $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', 'open'));
        $this->setState('filter.kind', $this->getUserStateFromRequest($this->context . '.filter.kind', 'filter_kind', ''));
        $this->setState('filter.channel', $this->getUserStateFromRequest($this->context . '.filter.channel', 'filter_channel', ''));

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
        return parent::getStoreId(
            $id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.state')
            . ':' . $this->getState('filter.kind') . ':' . $this->getState('filter.channel')
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
                $db->quoteName('a.kind'),
                $db->quoteName('a.state'),
                $db->quoteName('a.advisory_id'),
                $db->quoteName('a.reporter_name'),
                $db->quoteName('a.reporter_email'),
                $db->quoteName('a.reporter_organisation'),
                $db->quoteName('a.reporter_user_id'),
                $db->quoteName('a.extension_id'),
                $db->quoteName('a.extension_name'),
                $db->quoteName('a.extension_version'),
                $db->quoteName('a.vulnerability_class'),
                $db->quoteName('a.actively_exploited'),
                $db->quoteName('a.publicly_disclosed'),
                $db->quoteName('a.channel'),
                $db->quoteName('a.verified_time'),
                $db->quoteName('a.created'),
                $db->quoteName('a.checked_out'),
                $db->quoteName('a.checked_out_time'),
                $db->quoteName('adv.title', 'advisory_title'),
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__vel_attachments', 'att')
                . ' WHERE ' . $db->quoteName('att.report_id') . ' = ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('attachment_count'),
            ]
        )
            ->from($db->quoteName('#__vel_reports', 'a'))
            ->leftJoin($db->quoteName('#__vel_advisories', 'adv'), $db->quoteName('adv.id') . ' = ' . $db->quoteName('a.advisory_id'));

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            if (preg_match('/^(?:id:|VELR-?)0*(\d+)$/i', $search, $m)) {
                $id = (int) $m[1];
                $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            } else {
                $like = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' . $db->quoteName('a.extension_name') . ' LIKE :s1'
                    . ' OR ' . $db->quoteName('a.reporter_name') . ' LIKE :s2'
                    . ' OR ' . $db->quoteName('a.reporter_email') . ' LIKE :s3'
                    . ' OR ' . $db->quoteName('a.developer_name') . ' LIKE :s4'
                    . ' OR ' . $db->quoteName('a.tracking_reference') . ' LIKE :s5)'
                )
                    ->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like)->bind(':s4', $like)->bind(':s5', $like);
            }
        }

        $state = (string) $this->getState('filter.state');

        if ($state === 'open' || $state === '') {
            $open = array_map(static fn (ReportState $s): string => $s->value, ReportState::open());
            $query->whereIn($db->quoteName('a.state'), $open, ParameterType::STRING);
        } elseif ($state !== 'all' && ReportState::tryFrom($state) !== null) {
            $query->where($db->quoteName('a.state') . ' = :state')->bind(':state', $state);
        }

        $kind = (string) $this->getState('filter.kind');

        if ($kind !== '') {
            $query->where($db->quoteName('a.kind') . ' = :kind')->bind(':kind', $kind);
        }

        $channel = (string) $this->getState('filter.channel');

        if ($channel !== '') {
            $query->where($db->quoteName('a.channel') . ' = :channel')->bind(':channel', $channel);
        }

        $ordering  = $this->getState('list.ordering', 'a.created');
        $direction = $this->getState('list.direction', 'DESC');

        if (\in_array($ordering, $this->filter_fields, true)) {
            $query->order($db->escape($ordering) . ' ' . ($direction === 'ASC' ? 'ASC' : 'DESC'));
        }

        return $query;
    }
}
