<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Ticket;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Handler for `TicketType::Review` tickets - a submitted review awaiting
 * moderation. Master-data query moved here from the old
 * `TicketModel::getReviewData()`.
 *
 * @since 4.1.0
 */
final class ReviewTicketHandler implements TicketTypeHandlerInterface
{
    /**
     * @param DatabaseInterface $db The database connector object.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public static function type(): TicketType
    {
        return TicketType::Review;
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getMasterData(int $linkedItemId): ?object
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select('a.*')
            ->select($db->quoteName('uc.name', 'review_creator'))
            ->from($db->quoteName('#__jed_reviews', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON ' . $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.created_by'))
            ->where($db->quoteName('a.id') . ' = :id')
            ->bind(':id', $linkedItemId, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getMasterDataLayout(): string
    {
        return 'ticket.masterdata_review';
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getActions(int $linkedItemId, User $user): array
    {
        $actions = [];

        if ($user->authorise('core.edit.state', 'com_jed')) {
            $actions[] = new TicketAction(
                label: 'COM_TICKETS_ACTION_APPROVE',
                task: 'reviews.publish',
                icon: 'publish',
                hiddenFields: ['cid[]' => $linkedItemId, 'boxchecked' => 1],
                option: 'com_jed'
            );
        }

        if ($user->authorise('core.delete', 'com_jed')) {
            $actions[] = new TicketAction(
                label: 'COM_TICKETS_ACTION_DELETE',
                task: 'reviews.delete',
                icon: 'delete',
                confirmMessage: 'COM_TICKETS_ACTION_DELETE_CONFIRM',
                hiddenFields: ['cid[]' => $linkedItemId, 'boxchecked' => 1],
                option: 'com_jed'
            );
        }

        return $actions;
    }
}
