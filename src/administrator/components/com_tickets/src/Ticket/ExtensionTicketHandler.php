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

use Jed\Component\Jed\Administrator\Model\ExtensionModel;
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

/**
 * Handler for `TicketType::Extension` tickets - a new or edited extension pending
 * review. Master data is the same live-vs-pending-history compare
 * {@see \Jed\Component\Jed\Administrator\Model\ExtensionModel::getCompareItems()}
 * already computes for the standalone compare layout.
 *
 * @since 4.1.0
 */
final class ExtensionTicketHandler implements TicketTypeHandlerInterface
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
        return TicketType::Extension;
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getMasterData(int $linkedItemId): ?object
    {
        $model = new ExtensionModel();
        [$live, $pending, $pendingHistoryId] = $model->getCompareItems($linkedItemId, null, null);

        if (!$live && !$pending) {
            return null;
        }

        return (object) [
            'extensionId'      => $linkedItemId,
            'live'             => $live,
            'pending'          => $pending,
            'pendingHistoryId' => $pendingHistoryId,
            'isPending'        => $pending !== null
                && (!$live || (int) ($live->entry_version ?? 0) !== $pendingHistoryId),
        ];
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getMasterDataLayout(): string
    {
        return 'ticket.masterdata_extension';
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getActions(int $linkedItemId, User $user): array
    {
        if (!$user->authorise('core.edit', 'com_jed')) {
            return [];
        }

        $model = new ExtensionModel();
        [, , $pendingHistoryId] = $model->getCompareItems($linkedItemId, null, null);

        if (!$pendingHistoryId) {
            return [];
        }

        return [
            new TicketAction(
                label: 'COM_TICKETS_ACTION_APPROVE',
                task: 'extension.approve',
                icon: 'publish',
                hiddenFields: ['extension_id' => $linkedItemId, 'history_id' => $pendingHistoryId],
                option: 'com_jed'
            ),
        ];
    }
}
