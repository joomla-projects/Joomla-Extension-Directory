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

use Jed\Component\Jed\Administrator\Transfer\TransferState;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\ParameterType;
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
     * @since 4.0.0
     */
    private const int LIST_LIMIT = 10;

    /**
     * @since 4.0.0
     */
    protected int $reviewsTotal = 0;

    /**
     * @since 4.0.0
     */
    protected int $extensionsTotal = 0;

    /**
     * @since 4.0.0
     */
    protected int $ticketsTotal = 0;

    /**
     * @since 4.0.0
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
                    'r.id', 'r.title', 'r.overall_score', 'r.state', 'r.created_on', 'r.extension_id',
                    'r.created_by', 'r.developer_response', 'r.developer_response_published',
                    $db->quoteName('e.name', 'extension_title'),
                    'CASE WHEN ' . $db->quoteName('e.owner') . ' = ' . $db->quote($userId)
                        . ' OR ' . $db->quoteName('m.user_id') . ' IS NOT NULL THEN 1 ELSE 0 END AS is_own_extension',
                ]
            )
            ->from($db->quoteName('#__jed_reviews', 'r'))
            ->innerJoin($db->quoteName('#__jed_extensions', 'e'), $db->quoteName('e.id') . ' = ' . $db->quoteName('r.extension_id'))
            ->leftJoin($db->quoteName('#__jed_extensions_maintainers', 'm'), $db->quoteName('m.extension_id') . ' = ' . $db->quoteName('r.extension_id')
                . ' AND ' . $db->quoteName('m.user_id') . ' = ' . $db->quote($userId))
            ->where(
                '(' . $db->quoteName('r.created_by') . ' = ' . $db->quote($userId) . ')'
                . ' OR ((' . $db->quoteName('e.owner') . ' = ' . $db->quote($userId)
                . ' OR ' . $db->quoteName('m.user_id') . ' IS NOT NULL) AND ' . $db->quoteName('r.state') . ' = 1)'
            )
            ->order($db->quoteName('r.created_on') . ' DESC');

        $this->reviewsTotal = $this->countTotal($query);
        $query->setLimit(self::LIST_LIMIT, $this->getLimitStart('reviews_'));

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @return Pagination
     * @since  4.0.0
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
    /**
     * The open maintainer invitations for the current user.
     *
     * The dashboard is the only place an invited person finds out they were named at all - the
     * invitation grants nothing until they answer it, so without this the state column would
     * just be a way to make maintainers never work.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getMaintainerInvitations(): array
    {
        $userId = (int) (Factory::getApplication()->getIdentity()->id ?? 0);

        if ($userId <= 0) {
            return [];
        }

        $db      = $this->getDatabase();
        $invited = JedHelper::MAINTAINER_INVITED;

        return (array) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['m.extension_id', 'm.invited_time']))
                ->select($db->quoteName('e.name', 'extension_name'))
                ->select($db->quoteName('u.name', 'invited_by_name'))
                ->from($db->quoteName('#__jed_extensions_maintainers', 'm'))
                ->innerJoin($db->quoteName('#__jed_extensions', 'e'), $db->quoteName('e.id') . ' = ' . $db->quoteName('m.extension_id'))
                ->leftJoin($db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('m.invited_by'))
                ->where($db->quoteName('m.user_id') . ' = ' . $userId)
                ->where($db->quoteName('m.state') . ' = ' . $invited)
                ->where($db->quoteName('e.deleted') . ' = 0')
                ->order($db->quoteName('m.invited_time') . ' DESC')
        )->loadObjectList();
    }

    /**
     * Open ownership handovers the current user is part of, on either side.
     *
     * Shown on the dashboard so nobody has to guess what a handover is waiting on - 8.8.1 asks
     * for exactly that, because the alternative is two people each assuming the other has not
     * acted yet. The other party is named by name; their address is never shown.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getTransfers(): array
    {
        $userId = (int) (Factory::getApplication()->getIdentity()->id ?? 0);

        if ($userId <= 0) {
            return [];
        }

        $db   = $this->getDatabase();
        $open = [
            TransferState::PENDING->value,
            TransferState::FROM_CONFIRMED->value,
            TransferState::TO_CONFIRMED->value,
        ];

        $rows = (array) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['t.id', 't.extension_id', 't.state', 't.expires', 't.from_user_id', 't.to_user_id']))
                ->select($db->quoteName('e.name', 'extension_name'))
                ->select($db->quoteName('uf.name', 'from_name'))
                ->select($db->quoteName('ut.name', 'to_name'))
                ->from($db->quoteName('#__jed_extension_transfers', 't'))
                ->innerJoin($db->quoteName('#__jed_extensions', 'e'), $db->quoteName('e.id') . ' = ' . $db->quoteName('t.extension_id'))
                ->leftJoin($db->quoteName('#__users', 'uf'), $db->quoteName('uf.id') . ' = ' . $db->quoteName('t.from_user_id'))
                ->leftJoin($db->quoteName('#__users', 'ut'), $db->quoteName('ut.id') . ' = ' . $db->quoteName('t.to_user_id'))
                ->whereIn($db->quoteName('t.state'), $open, ParameterType::STRING)
                ->where('(' . $db->quoteName('t.from_user_id') . ' = ' . $userId
                    . ' OR ' . $db->quoteName('t.to_user_id') . ' = ' . $userId . ')')
                ->order($db->quoteName('t.id') . ' DESC')
        )->loadObjectList();

        foreach ($rows as $row) {
            $isRecipient      = (int) $row->to_user_id === $userId;
            $row->other_name  = $isRecipient ? $row->from_name : $row->to_name;
            $state            = TransferState::from((string) $row->state);

            // "Waiting for you" is the only thing the reader can act on, so work it out here
            // rather than leaving the template to reason about the state machine.
            $row->awaiting_me = $isRecipient
                ? $state !== TransferState::TO_CONFIRMED
                : $state !== TransferState::FROM_CONFIRMED;
        }

        return $rows;
    }

    /**
     * The listings the current user could hand over right now.
     *
     * Owned only, never maintained: the 8.8 matrix puts transfer in the owner-only column, and
     * offering a maintainer a listing in this picker would be an invitation to an error the
     * controller then has to refuse.
     *
     * Listings with a handover already under way are left out, because a second request would be
     * refused anyway - they are shown in the open-transfers list above the form instead.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getTransferableExtensions(): array
    {
        $userId = (int) (Factory::getApplication()->getIdentity()->id ?? 0);

        if ($userId <= 0) {
            return [];
        }

        $db   = $this->getDatabase();
        $open = [
            TransferState::PENDING->value,
            TransferState::FROM_CONFIRMED->value,
            TransferState::TO_CONFIRMED->value,
        ];

        $openTransfer = 'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jed_extension_transfers', 'tr')
            . ' WHERE ' . $db->quoteName('tr.extension_id') . ' = ' . $db->quoteName('a.id')
            . ' AND ' . $db->quoteName('tr.state') . ' IN (' . implode(',', array_map([$db, 'quote'], $open)) . '))';

        return (array) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['a.id', 'a.name']))
                ->from($db->quoteName('#__jed_extensions', 'a'))
                ->where($db->quoteName('a.owner') . ' = ' . $userId)
                ->where($db->quoteName('a.deleted') . ' = 0')
                ->where($openTransfer)
                ->order($db->quoteName('a.name') . ' ASC')
        )->loadObjectList();
    }

    public function getExtensions(): array
    {
        $userId = Factory::getApplication()->getIdentity()->id;
        $db     = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('a.id, a.extension_version, a.state, a.created, a.owner')
            ->select('a.name, a.approved, a.approved_time, a.approved_reason, a.approved_notes')
            ->select('a.blocked, a.block_reason_code, a.deleted')
            ->select('cat.title AS category_title')
            ->from($db->quoteName('#__jed_extensions', 'a'))
            ->leftJoin($db->quoteName('#__categories', 'cat'), $db->quoteName('cat.id') . ' = ' . $db->quoteName('a.catid'))
            // Owned *and* maintained (8.8) - a maintainer manages the listing from here too, so
            // a plain owner filter left them without a way in.
            ->where(JedHelper::getOwnedOrMaintainedCondition($db))
            // The dashboard is where a developer manages their listings, so it deliberately
            // shows the ones that are not public - pending approval, taken offline, blocked. Only
            // soft-deleted rows are gone from the frontend entirely (4.8).
            ->where($db->quoteName('a.deleted') . ' = 0')
            ->order($db->quoteName('a.id') . ' DESC');

        $this->extensionsTotal = $this->countTotal($query);
        $query->setLimit(self::LIST_LIMIT, $this->getLimitStart('extensions_'));

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @return Pagination
     * @since  4.0.0
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
            ->leftJoin($db->quoteName('#__jed_ticket_categories', 'jtc'), $db->quoteName('jtc.id') . ' = ' . $db->quoteName('a.ticket_category_type'))
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
     * @since  4.0.0
     */
    public function getTicketsPagination(): Pagination
    {
        return new Pagination($this->ticketsTotal, $this->getLimitStart('tickets_'), self::LIST_LIMIT, 'tickets_');
    }

    /**
     * Returns the current user's bookmarked extensions, newest first by default.
     *
     * @return array
     * @since  4.0.0
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
            ->innerJoin($db->quoteName('#__jed_extensions', 'e'), $db->quoteName('e.id') . ' = ' . $db->quoteName('f.extension_id'))
            ->leftJoin($db->quoteName('#__categories', 'cat'), $db->quoteName('cat.id') . ' = ' . $db->quoteName('e.catid'))
            ->where($db->quoteName('f.user_id') . ' = ' . $db->quote($userId))
            ->order($db->quoteName('f.created') . ' DESC');

        $this->favoritesTotal = $this->countTotal($query);
        $query->setLimit(self::LIST_LIMIT, $this->getLimitStart('favorites_'));

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @return Pagination
     * @since  4.0.0
     */
    public function getFavoritesPagination(): Pagination
    {
        return new Pagination($this->favoritesTotal, $this->getLimitStart('favorites_'), self::LIST_LIMIT, 'favorites_');
    }

    /**
     * @param string $prefix The pagination request-variable prefix for this list.
     *
     * @return int
     * @since  4.0.0
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
     * @since  4.0.0
     */
    private function countTotal(QueryInterface $query): int
    {
        $db         = $this->getDatabase();
        // A derived table: the "table" is the wrapped query, so only its alias is a name that
        // quoteName() can take.
        $countQuery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('(' . (string) $query . ') AS ' . $db->quoteName('count_subquery'));

        return (int) $db->setQuery($countQuery)->loadResult();
    }
}
