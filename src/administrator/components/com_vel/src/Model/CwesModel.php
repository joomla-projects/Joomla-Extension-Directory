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

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/**
 * The CWE reference list.
 *
 * @since 4.0.0
 */
class CwesModel extends ListModel
{
    /**
     * @param array $config Configuration settings.
     *
     * @since 4.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['cwe_id', 'a.cwe_id', 'name', 'a.name'];
        }

        parent::__construct($config);
    }

    /**
     * @param string $ordering  Default ordering column.
     * @param string $direction Default ordering direction.
     *
     * @return void
     *
     * @since 4.0.0
     */
    protected function populateState($ordering = 'a.cwe_id', $direction = 'ASC'): void
    {
        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', ''));

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
        return parent::getStoreId($id . ':' . $this->getState('filter.search'));
    }

    /**
     * @return QueryInterface
     *
     * @since 4.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.*')
            ->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__vel_advisory_cwes', 'c') . ' WHERE ' . $db->quoteName('c.cwe_id') . ' = ' . $db->quoteName('a.cwe_id') . ') AS ' . $db->quoteName('used'))
            ->from($db->quoteName('#__vel_cwe', 'a'));

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            $like = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where('(' . $db->quoteName('a.cwe_id') . ' LIKE :s1 OR ' . $db->quoteName('a.name') . ' LIKE :s2)')->bind(':s1', $like)->bind(':s2', $like);
        }

        $query->order('CAST(SUBSTRING(' . $db->quoteName('a.cwe_id') . ', 5) AS UNSIGNED) ASC');

        return $query;
    }
}
