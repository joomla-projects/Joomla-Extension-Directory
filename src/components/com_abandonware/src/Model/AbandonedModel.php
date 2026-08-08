<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Abandonware\Administrator\Enum\CaseStatus;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/**
 * The public abandoned list - parity with the legacy one, minus what it should not have shown.
 *
 * Two rules govern what appears here, and they are both in `getListQuery()` rather than in a
 * template, so no future view can accidentally widen them:
 *
 *  - **`published = 1` and `status = 'abandoned'`.** The legacy list published *reports*, which
 *    meant one unverified submission put a developer's product on a public list. Here public means
 *    the JED team concluded it, after a recorded contact attempt and a grace period. 4.10 weighs
 *    the benefit to visitors against the commercial effect of a misjudgement, and this is where
 *    that weighing lands.
 *  - **The reports table is never joined.** Everything identifying a reporter lives there. The
 *    legacy `abandoneditem` view printed `reporter_fullname` on a public page; this cannot,
 *    because the query has no access to it.
 *
 * @since 4.1.0
 */
class AbandonedModel extends ListModel
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
                'extension_name', 'a.extension_name',
                'abandoned_time', 'a.abandoned_time',
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
    protected function populateState($ordering = 'a.abandoned_time', $direction = 'DESC'): void
    {
        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', ''));

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
        return parent::getStoreId($id . ':' . $this->getState('filter.search'));
    }

    /**
     * @return QueryInterface
     *
     * @since 4.1.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);
        $marked = CaseStatus::ABANDONED->value;

        $query->select(
            [
                $db->quoteName('a.id'),
                $db->quoteName('a.extension_id'),
                $db->quoteName('a.extension_name'),
                $db->quoteName('a.extension_version'),
                $db->quoteName('a.extension_url'),
                $db->quoteName('a.developer_name'),
                $db->quoteName('a.abandoned_time'),
                $db->quoteName('e.alias', 'listing_alias'),
                $db->quoteName('e.approved', 'listing_approved'),
                $db->quoteName('e.state', 'listing_state'),
                $db->quoteName('e.blocked', 'listing_blocked'),
                $db->quoteName('e.deleted', 'listing_deleted'),
            ]
        )
            ->from($db->quoteName('#__jed_abandonware_cases', 'a'))
            ->leftJoin($db->quoteName('#__jed_extensions', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('a.extension_id'))
            ->where($db->quoteName('a.published') . ' = 1')
            ->where($db->quoteName('a.status') . ' = :marked')
            ->bind(':marked', $marked);

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            $like = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where(
                '(' . $db->quoteName('a.extension_name') . ' LIKE :s1'
                . ' OR ' . $db->quoteName('a.developer_name') . ' LIKE :s2)'
            )
                ->bind(':s1', $like)
                ->bind(':s2', $like);
        }

        $ordering  = $this->getState('list.ordering', 'a.abandoned_time');
        $direction = $this->getState('list.direction', 'DESC');

        if (\in_array($ordering, $this->filter_fields, true)) {
            $query->order($db->escape($ordering) . ' ' . ($direction === 'ASC' ? 'ASC' : 'DESC'));
        }

        return $query;
    }
}
