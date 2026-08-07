<?php

/**
 * @package JED
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
use Jed\Component\Jed\Administrator\Access\JedAccessHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use RuntimeException;

/**
 * The per-user privilege list behind `#__jed_user_access`.
 *
 * Driven from `#__users` with a LEFT JOIN rather than from the access table, because the common
 * case is acting on somebody who has **no** row yet - an absent row means full privileges
 * (`P1-05`), so a list of existing rows would only ever show people who had already been dealt
 * with. The columns are read with COALESCE so the list shows the effective privilege, not NULL.
 *
 * @since 4.1.0
 */
class UseraccessModel extends ListModel
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
                'id', 'u.id',
                'name', 'u.name',
                'username', 'u.username',
                'banned', 'a.banned',
                'auto_approve_extensions', 'a.auto_approve_extensions',
                'set_time', 'a.set_time',
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
    protected function populateState($ordering = 'u.name', $direction = 'ASC'): void
    {
        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', ''));
        $this->setState('filter.status', $this->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', ''));

        parent::populateState($ordering, $direction);
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
                $db->quoteName('u.id'),
                $db->quoteName('u.name'),
                $db->quoteName('u.username'),
                $db->quoteName('u.block'),
                // COALESCE, so a user with no row reads as the defaults rather than as NULL -
                // the list has to show what is in force, not what happens to be stored.
                'COALESCE(' . $db->quoteName('a.create_listing') . ', 1) AS ' . $db->quoteName('create_listing'),
                'COALESCE(' . $db->quoteName('a.edit_listing') . ', 1) AS ' . $db->quoteName('edit_listing'),
                'COALESCE(' . $db->quoteName('a.update_xml') . ', 1) AS ' . $db->quoteName('update_xml'),
                'COALESCE(' . $db->quoteName('a.review') . ', 1) AS ' . $db->quoteName('review'),
                'COALESCE(' . $db->quoteName('a.report') . ', 1) AS ' . $db->quoteName('report'),
                'COALESCE(' . $db->quoteName('a.auto_approve_extensions') . ', 0) AS ' . $db->quoteName('auto_approve_extensions'),
                'COALESCE(' . $db->quoteName('a.auto_approve_reviews') . ', 0) AS ' . $db->quoteName('auto_approve_reviews'),
                'COALESCE(' . $db->quoteName('a.banned') . ', 0) AS ' . $db->quoteName('banned'),
                $db->quoteName('a.banned_reason'),
                $db->quoteName('a.banned_from'),
                $db->quoteName('a.banned_until'),
                $db->quoteName('a.set_time'),
                $db->quoteName('setter.name', 'set_by_name'),
                '(' . $db->quoteName('a.user_id') . ' IS NOT NULL) AS ' . $db->quoteName('has_row'),
            ]
        )
            ->from($db->quoteName('#__users', 'u'))
            ->leftJoin($db->quoteName('#__jed_user_access', 'a') . ' ON ' . $db->quoteName('a.user_id') . ' = ' . $db->quoteName('u.id'))
            ->leftJoin($db->quoteName('#__users', 'setter') . ' ON ' . $db->quoteName('setter.id') . ' = ' . $db->quoteName('a.set_by'));

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            $like = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where(
                '(' . $db->quoteName('u.name') . ' LIKE :s1'
                . ' OR ' . $db->quoteName('u.username') . ' LIKE :s2'
                . ' OR ' . $db->quoteName('u.email') . ' LIKE :s3)'
            )
                ->bind(':s1', $like)
                ->bind(':s2', $like)
                ->bind(':s3', $like);
        }

        // "Banned" filters on the ban being *in force now*, not on the flag - the same rule the
        // gate applies. A list that showed expired bans as bans would send somebody looking for
        // a restriction that is not there.
        switch ((string) $this->getState('filter.status')) {
            case 'banned':
                $query->where($db->quoteName('a.banned') . ' = 1')
                    ->where('(' . $db->quoteName('a.banned_from') . ' IS NULL OR ' . $db->quoteName('a.banned_from') . ' <= NOW())')
                    ->where('(' . $db->quoteName('a.banned_until') . ' IS NULL OR ' . $db->quoteName('a.banned_until') . ' >= NOW())');
                break;

            case 'trusted':
                $query->where('(' . $db->quoteName('a.auto_approve_extensions') . ' = 1 OR ' . $db->quoteName('a.auto_approve_reviews') . ' = 1)');
                break;

            case 'restricted':
                $query->where(
                    '(' . $db->quoteName('a.create_listing') . ' = 0 OR ' . $db->quoteName('a.edit_listing') . ' = 0'
                    . ' OR ' . $db->quoteName('a.update_xml') . ' = 0 OR ' . $db->quoteName('a.review') . ' = 0'
                    . ' OR ' . $db->quoteName('a.report') . ' = 0)'
                );
                break;

            case 'decided':
                $query->where($db->quoteName('a.user_id') . ' IS NOT NULL');
                break;
        }

        $ordering  = $this->getState('list.ordering', 'u.name');
        $direction = $this->getState('list.direction', 'ASC');

        if ($ordering) {
            $query->order($db->escape($ordering) . ' ' . $db->escape($direction));
        }

        return $query;
    }

    /**
     * Write a decision about one user, with the reason it required.
     *
     * Everything goes through here rather than each action writing its own UPDATE, so `set_by`
     * and `set_time` cannot be forgotten on one path - a privilege change nobody can attribute
     * is the thing the audit columns exist to prevent.
     *
     * The row is created on demand: most users never have one, and requiring the team to create
     * it first would be a step with no meaning.
     *
     * @param int    $userId  The user being decided about.
     * @param array  $columns Column => value pairs to write.
     * @param string $reason  Why. Mandatory.
     *
     * @return void
     *
     * @throws RuntimeException  When the reason is missing or the user does not exist.
     *
     * @since 4.1.0
     */
    public function applyDecision(int $userId, array $columns, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException(Text::_('COM_JED_USERACCESS_ERROR_REASON_REQUIRED'));
        }

        $db = $this->getDatabase();

        $exists = (bool) $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadResult();

        if (!$exists) {
            throw new RuntimeException(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));
        }

        $hasRow = (bool) $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_user_access'))
                ->where($db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadResult();

        $columns['set_by']   = (int) $this->getCurrentUser()->id;
        $columns['set_time'] = Factory::getDate()->toSql();

        // The reason belongs to the ban. For a privilege or trust change it is still required -
        // and is kept in banned_reason, which doubles as "the note behind the last decision"
        // rather than gaining a second, nearly identical column.
        $columns['banned_reason'] = $reason;

        if ($hasRow) {
            $query = $db->getQuery(true)->update($db->quoteName('#__jed_user_access'));

            foreach ($columns as $column => $value) {
                $query->set($db->quoteName($column) . ' = ' . ($value === null ? 'NULL' : $db->quote($value)));
            }

            $query->where($db->quoteName('user_id') . ' = ' . $userId);
            $db->setQuery($query)->execute();
        } else {
            $row = (object) array_merge(['user_id' => $userId], $columns);
            $db->insertObject('#__jed_user_access', $row);
        }

        // The gate caches rows per request; a decision made in this request has to be visible to
        // anything that asks afterwards.
        JedAccessHelper::clearCache();
    }
}
