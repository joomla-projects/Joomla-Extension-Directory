<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Ticket;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Enum\TicketType;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

/**
 * Handler for `TicketType::LinkCheck` tickets - the ticket the periodic link check opens for a
 * developer whose link has stopped answering (P1-09). `linked_item_id` is the extension, exactly
 * as for an Extension ticket, so the master data is the listing; what differs is that there is
 * nothing to approve or reject, so there are no actions. The ticket closes itself when the link
 * answers again (LinkCheckService::annotateTicket()).
 *
 * @since 4.0.0
 */
final class LinkcheckTicketHandler implements TicketTypeHandlerInterface
{
    /**
     * The listing is shown exactly as an Extension ticket shows it.
     *
     * @var ExtensionTicketHandler
     *
     * @since 4.0.0
     */
    private ExtensionTicketHandler $extensionHandler;

    /**
     * @param DatabaseInterface $db The database connector object.
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseInterface $db)
    {
        $this->extensionHandler = new ExtensionTicketHandler($db);
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public static function type(): TicketType
    {
        return TicketType::LinkCheck;
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getMasterData(int $linkedItemId): ?object
    {
        return $this->extensionHandler->getMasterData($linkedItemId);
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getMasterDataLayout(): string
    {
        return $this->extensionHandler->getMasterDataLayout();
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getExtensionId(int $linkedItemId): ?int
    {
        return $this->extensionHandler->getExtensionId($linkedItemId);
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getActions(int $linkedItemId, User $user): array
    {
        return [];
    }
}
