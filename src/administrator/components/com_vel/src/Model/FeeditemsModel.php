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
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/**
 * The feed review queue (VEL2-PRD §6.3.1).
 *
 * @since 4.0.0
 */
class FeeditemsModel extends ListModel
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
            $config['filter_fields'] = ['id', 'a.id', 'external_id', 'a.external_id', 'fetched', 'a.fetched', 'external_updated', 'a.external_updated', 'decision', 'a.decision', 'classification', 'a.classification', 'source', 'a.source'];
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
    protected function populateState($ordering = 'a.fetched', $direction = 'DESC'): void
    {
        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', ''));
        $this->setState('filter.decision', $this->getUserStateFromRequest($this->context . '.filter.decision', 'filter_decision', 'pending'));
        $this->setState('filter.classification', $this->getUserStateFromRequest($this->context . '.filter.classification', 'filter_classification', ''));
        $this->setState('filter.source', $this->getUserStateFromRequest($this->context . '.filter.source', 'filter_source', ''));

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
        return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.decision') . ':' . $this->getState('filter.classification') . ':' . $this->getState('filter.source'));
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

        $query->select([
            $db->quoteName('a.id'),
            $db->quoteName('a.source'),
            $db->quoteName('a.external_id'),
            $db->quoteName('a.assigner'),
            $db->quoteName('a.title'),
            $db->quoteName('a.fetched'),
            $db->quoteName('a.external_updated'),
            $db->quoteName('a.classification'),
            $db->quoteName('a.classification_reason'),
            $db->quoteName('a.confidence'),
            $db->quoteName('a.decision'),
            $db->quoteName('a.advisory_id'),
            $db->quoteName('adv.title', 'advisory_title'),
            $db->quoteName('adv.state', 'advisory_state'),
        ])
            ->from($db->quoteName('#__vel_feed_items', 'a'))
            ->leftJoin($db->quoteName('#__vel_advisories', 'adv'), $db->quoteName('adv.id') . ' = ' . $db->quoteName('a.advisory_id'));

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            $like = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where('(' . $db->quoteName('a.external_id') . ' LIKE :s1 OR ' . $db->quoteName('a.title') . ' LIKE :s2 OR ' . $db->quoteName('a.assigner') . ' LIKE :s3)')
                ->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like);
        }

        $decision = (string) $this->getState('filter.decision');

        if ($decision !== '' && $decision !== 'all') {
            $query->where($db->quoteName('a.decision') . ' = :decision')->bind(':decision', $decision);
        }

        $classification = (string) $this->getState('filter.classification');

        if ($classification !== '') {
            $query->where($db->quoteName('a.classification') . ' = :class')->bind(':class', $classification);
        }

        $source = (string) $this->getState('filter.source');

        if ($source !== '') {
            $query->where($db->quoteName('a.source') . ' = :source')->bind(':source', $source);
        }

        $ordering  = $this->getState('list.ordering', 'a.fetched');
        $direction = $this->getState('list.direction', 'DESC');

        if (\in_array($ordering, $this->filter_fields, true)) {
            $query->order($db->escape($ordering) . ' ' . ($direction === 'ASC' ? 'ASC' : 'DESC'));
        }

        return $query;
    }
}
