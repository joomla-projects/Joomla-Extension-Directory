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
 * Handler for `TicketType::VulnerableExtension` tickets - a submitted "solved
 * vulnerability" developer update. Master-data query moved here from the old
 * `TicketModel::getVelDeveloperUpdateData()`, plus the linked vulnerable item's
 * current status/patch version when one is already assigned.
 *
 * @since 4.1.0
 */
final class DeveloperupdateTicketHandler implements TicketTypeHandlerInterface
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
        return TicketType::VulnerableExtension;
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
            ->from($db->quoteName('#__vel_developer_update', 'a'))
            ->where($db->quoteName('a.id') . ' = :id')
            ->bind(':id', $linkedItemId, ParameterType::INTEGER);
        $developerUpdate = $db->setQuery($query)->loadObject();

        if (!$developerUpdate) {
            return null;
        }

        $vulnerableItem = null;

        if ((int) ($developerUpdate->vel_item_id ?? 0) > 0) {
            $velQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__vel_vulnerable_item'))
                ->where($db->quoteName('id') . ' = :velId')
                ->bind(':velId', $developerUpdate->vel_item_id, ParameterType::INTEGER);
            $vulnerableItem = $db->setQuery($velQuery)->loadObject() ?: null;
        }

        return (object) [
            'developerUpdate' => $developerUpdate,
            'vulnerableItem'  => $vulnerableItem,
        ];
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getMasterDataLayout(): string
    {
        return 'ticket.masterdata_developerupdate';
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getActions(int $linkedItemId, User $user): array
    {
        if (!$user->authorise('core.edit', 'com_vel')) {
            return [];
        }

        return [
            new TicketAction(
                label: 'COM_TICKETS_ACTION_MARK_PATCHED',
                task: 'vulnerability.markDeveloperUpdatePatched',
                icon: 'publish',
                hiddenFields: ['linked_item_id' => $linkedItemId],
                option: 'com_vel'
            ),
        ];
    }
}
