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
 * The feed run log.
 *
 * @since 4.0.0
 */
class FeedrunsModel extends ListModel
{
    /**
     * @param array $config Configuration settings.
     *
     * @since 4.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'a.id', 'started', 'a.started', 'source', 'a.source', 'status', 'a.status'];
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
    protected function populateState($ordering = 'a.id', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * @return QueryInterface
     *
     * @since 4.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();

        return $db->getQuery(true)
            ->select('a.*')
            ->from($db->quoteName('#__vel_feed_runs', 'a'))
            ->order($db->quoteName('a.id') . ' DESC');
    }
}
