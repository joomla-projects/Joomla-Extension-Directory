<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\QueryInterface;

/**
 * Dashboard model.
 *
 * @since 4.0.0
 */
class DashboardModel extends ItemModel
{
    /**
     * Rows per list, per page.
     *
     * @since 4.1.0
     */
    private const int LIST_LIMIT = 10;

    /**
     * @since 4.1.0
     */
    protected int $reviewsTotal = 0;

    /**
     * @since 4.1.0
     */
    protected int $extensionsTotal = 0;

    /**
     * @since 4.1.0
     */
    protected int $ticketsTotal = 0;

    /**
     * @since 4.1.0
     */
    protected int $favoritesTotal = 0;

    /**
     * @since 4.0.0
     * @throws \Exception
     */
    protected function populateState(): void
    {
        $app    = Factory::getApplication();
        $params = $app->getParams('com_jed');
        $this->setState('params', $params);
    }

    /**
     * @since 4.0.0
     */
    public function getItem($pk = null): array
    {
        return [];
    }

    /**
     * Returns the reviews written by the current user, plus published reviews on extensions the
     * current user owns or maintains (flagged via `is_own_extension` so the template can mark
     * them). Call after {@see getReviewsPagination()} has been built, or build the pagination
     * from {@see getReviewsPagination()} after calling this - the total is cached here.
     *
     * @return array
     * @since  4.0.0
     * @throws \Exception
     */
    public function getReviews(): array
    {
        $userId = Factory::getApplication()->getIdentity()->id;
        $db     = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select(
                [
                    'r.id', 'r.title', 'r.overall_score', 'r.published', 'r.created_on', 'r.extension_id',
                    'r.created_by', 'r.developer_response', 'r.developer_response_published',
                    $db->quoteName('e.name', 'extension_title'),
                    'CASE WHEN ' . $db->quoteName('e.owner') . ' = ' . $db->quote($userId)
                        . ' OR ' . $db->quoteName('m.user_id') . ' IS NOT NULL THEN 1 ELSE 0 END AS is_own_extension',
                ]
            )
            ->from($db->quoteName('#__jed_reviews', 'r'))
            ->innerJoin($db->quoteName('#__jed_extensions', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('r.extension_id'))
            ->leftJoin(
                $db->quoteName('#__jed_extensions_maintainers', 'm')
                . ' ON ' . $db->quoteName('m.extension_id') . ' = ' . $db->quoteName('r.extension_id')
                . ' AND ' . $db->quoteName('m.user_id') . ' = ' . $db->quote($userId)
            )
            ->where(
                '(' . $db->quoteName('r.created_by') . ' = ' . $db->quote($userId) . ')'
                . ' OR ((' . $db->quoteName('e.owner') . ' = ' . $db->quote($userId)
                . ' OR ' . $db->quoteName('m.user_id') . ' IS NOT NULL) AND ' . $db->quoteName('r.published') . ' = 1)'
            )
            ->order($db->quoteName('r.created_on') . ' DESC');

        $this->reviewsTotal = $this->countTotal($query);
        $query->setLimit(self::LIST_LIMIT, $this->getLimitStart('reviews_'));

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @return Pagination
     * @since  4.1.0
     */
    public function getReviewsPagination(): Pagination
    {
        return new Pagination($this->reviewsTotal, $this->getLimitStart('reviews_'), self::LIST_LIMIT, 'reviews_');
    }

    /**
     * Returns the extensions owned by the current user (owner field).
     *
     * @return array
     * @since  4.0.0
     * @throws \Exception
     */
    public function getExtensions(): array
    {
        $userId = Factory::getApplication()->getIdentity()->id;
        $db     = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('a.id, a.extension_version, a.state, a.created, a.owner')
            ->select('a.name')
            ->select('cat.title AS category_title')
            ->from($db->quoteName('#__jed_extensions', 'a'))
            ->leftJoin(
                $db->quoteName('#__categories', 'cat')
                . ' ON ' . $db->quoteName('cat.id') . ' = ' . $db->quoteName('a.catid')
            )
            ->where($db->quoteName('a.owner') . ' = ' . $db->quote($userId))
            ->order($db->quoteName('a.id') . ' DESC');

        $this->extensionsTotal = $this->countTotal($query);
        $query->setLimit(self::LIST_LIMIT, $this->getLimitStart('extensions_'));

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @return Pagination
     * @since  4.1.0
     */
    public function getExtensionsPagination(): Pagination
    {
        return new Pagination($this->extensionsTotal, $this->getLimitStart('extensions_'), self::LIST_LIMIT, 'extensions_');
    }

    /**
     * Returns the tickets created by the current user.
     *
     * @return array
     * @since  4.0.0
     * @throws \Exception
     */
    public function getTickets(): array
    {
        $userId = Factory::getApplication()->getIdentity()->id;
        $db     = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('a.id, a.ticket_subject, a.ticket_status, a.ticket_origin, a.created_on')
            ->select('jtc.categorytype AS categorytype_string')
            ->from($db->quoteName('#__jed_tickets', 'a'))
            ->leftJoin(
                $db->quoteName('#__jed_ticket_categories', 'jtc')
                . ' ON ' . $db->quoteName('jtc.id') . ' = ' . $db->quoteName('a.ticket_category_type')
            )
            ->where($db->quoteName('a.created_by') . ' = ' . $db->quote($userId))
            ->order($db->quoteName('a.created_on') . ' DESC');

        $this->ticketsTotal = $this->countTotal($query);
        $query->setLimit(self::LIST_LIMIT, $this->getLimitStart('tickets_'));

        $items = $db->setQuery($query)->loadObjectList() ?: [];

        foreach ($items as $item) {
            $item->ticket_status = Text::_('COM_JED_TICKETS_TICKET_STATUS_OPTION_' . strtoupper((string) $item->ticket_status));
        }

        return $items;
    }

    /**
     * @return Pagination
     * @since  4.1.0
     */
    public function getTicketsPagination(): Pagination
    {
        return new Pagination($this->ticketsTotal, $this->getLimitStart('tickets_'), self::LIST_LIMIT, 'tickets_');
    }

    /**
     * Returns the current user's bookmarked extensions, newest first by default.
     *
     * @return array
     * @since  4.1.0
     * @throws \Exception
     */
    public function getFavorites(): array
    {
        $userId = Factory::getApplication()->getIdentity()->id;
        $db     = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('f.id, f.created, e.id AS extension_id, e.name, e.logo')
            ->select('cat.title AS category_title')
            ->from($db->quoteName('#__jed_favorites', 'f'))
            ->innerJoin($db->quoteName('#__jed_extensions', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('f.extension_id'))
            ->leftJoin(
                $db->quoteName('#__categories', 'cat')
                . ' ON ' . $db->quoteName('cat.id') . ' = ' . $db->quoteName('e.catid')
            )
            ->where($db->quoteName('f.user_id') . ' = ' . $db->quote($userId))
            ->order($db->quoteName('f.created') . ' DESC');

        $this->favoritesTotal = $this->countTotal($query);
        $query->setLimit(self::LIST_LIMIT, $this->getLimitStart('favorites_'));

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @return Pagination
     * @since  4.1.0
     */
    public function getFavoritesPagination(): Pagination
    {
        return new Pagination($this->favoritesTotal, $this->getLimitStart('favorites_'), self::LIST_LIMIT, 'favorites_');
    }

    /**
     * @param string $prefix The pagination request-variable prefix for this list.
     *
     * @return int
     * @since  4.1.0
     */
    private function getLimitStart(string $prefix): int
    {
        return Factory::getApplication()->getInput()->getUint($prefix . 'limitstart', 0);
    }

    /**
     * Counts the rows a (yet unlimited) query would return, by wrapping it as a subquery -
     * works regardless of joins/grouping without having to clone and rewrite the select clause.
     *
     * @param QueryInterface $query The query to count.
     *
     * @return int
     * @since  4.1.0
     */
    private function countTotal(QueryInterface $query): int
    {
        $db         = $this->getDatabase();
        $countQuery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('(' . (string) $query . ') AS count_subquery');

        return (int) $db->setQuery($countQuery)->loadResult();
    }
}
