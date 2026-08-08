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
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/**
 * The submissions behind the cases.
 *
 * A backend-only list, and the only place reporter identity is ever shown. It exists for two
 * things the case list cannot answer: what a reporter actually wrote, and whether one account has
 * been filing reports against one developer - the abuse pattern 4.10 names, which is invisible
 * unless the rejected submissions are kept and can be grouped by who sent them.
 *
 * @since 4.1.0
 */
class ReportsModel extends ListModel
{
    /**
     * @param array $config Configuration settings.
     *
     * @throws Exception
     *
     * @since 4.1.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'extension_name', 'a.extension_name',
                'reporter_name', 'a.reporter_name',
                'reporter_user_id', 'a.reporter_user_id',
                'case_id', 'a.case_id',
                'state', 'a.state',
                'created', 'a.created',
                'consent_to_process', 'a.consent_to_process',
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
     * @since 4.1.0
     */
    protected function populateState($ordering = 'a.created', $direction = 'DESC'): void
    {
        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', ''));
        $this->setState('filter.origin', $this->getUserStateFromRequest($this->context . '.filter.origin', 'filter_origin', ''));

        parent::populateState($ordering, $direction);
    }

    /**
     * @param string $id A prefix for the store id.
     *
     * @return string
     *
     * @since 4.1.0
     */
    protected function getStoreId($id = ''): string
    {
        return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.origin'));
    }

    /**
     * @return QueryInterface
     *
     * @since 4.1.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            [
                $db->quoteName('a.id'),
                $db->quoteName('a.case_id'),
                $db->quoteName('a.extension_id'),
                $db->quoteName('a.extension_name'),
                $db->quoteName('a.extension_version'),
                $db->quoteName('a.extension_url'),
                $db->quoteName('a.developer_name'),
                $db->quoteName('a.reason'),
                $db->quoteName('a.reporter_user_id'),
                $db->quoteName('a.reporter_name'),
                $db->quoteName('a.reporter_email'),
                $db->quoteName('a.reporter_organisation'),
                $db->quoteName('a.consent_to_process'),
                $db->quoteName('a.consent_time'),
                $db->quoteName('a.state'),
                $db->quoteName('a.legacy_form_id'),
                $db->quoteName('a.created'),
                $db->quoteName('c.status', 'case_status'),
                // How many reports this account has filed in total. The abuse signal: one report
                // is a citizen doing their bit, twenty against the same developer is something else.
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__jed_abandonware_reports', 'o')
                . ' WHERE ' . $db->quoteName('o.reporter_user_id') . ' = ' . $db->quoteName('a.reporter_user_id')
                . ' AND ' . $db->quoteName('o.reporter_user_id') . ' > 0) AS ' . $db->quoteName('reporter_total'),
            ]
        )
            ->from($db->quoteName('#__jed_abandonware_reports', 'a'))
            ->leftJoin($db->quoteName('#__jed_abandonware_cases', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.case_id'));

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            $like = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where(
                '(' . $db->quoteName('a.extension_name') . ' LIKE :s1'
                . ' OR ' . $db->quoteName('a.reporter_name') . ' LIKE :s2'
                . ' OR ' . $db->quoteName('a.reporter_email') . ' LIKE :s3'
                . ' OR ' . $db->quoteName('a.developer_name') . ' LIKE :s4)'
            )
                ->bind(':s1', $like)
                ->bind(':s2', $like)
                ->bind(':s3', $like)
                ->bind(':s4', $like);
        }

        switch ((string) $this->getState('filter.origin')) {
            case 'legacy':
                $query->where($db->quoteName('a.legacy_form_id') . ' IS NOT NULL');
                break;

            case 'new':
                $query->where($db->quoteName('a.legacy_form_id') . ' IS NULL');
                break;

            case 'no_consent':
                // Imported rows where the legacy form recorded no consent. Worth being able to
                // find: 4.6 makes consent a P1 item, and these are the rows where there is none.
                $query->where($db->quoteName('a.consent_to_process') . ' = 0');
                break;
        }

        $ordering  = $this->getState('list.ordering', 'a.created');
        $direction = $this->getState('list.direction', 'DESC');

        if (\in_array($ordering, $this->filter_fields, true)) {
            $query->order($db->escape($ordering) . ' ' . ($direction === 'ASC' ? 'ASC' : 'DESC'));
        }

        return $query;
    }
}
