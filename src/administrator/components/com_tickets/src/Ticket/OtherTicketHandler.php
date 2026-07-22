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

/**
 * Handler for manually-created tickets with no linked item
 * (`linked_item_type = 0`/`TicketType::Other`). No master data, no actions -
 * the fallback the registry uses for anything unrecognised too.
 *
 * (Fills what was previously an empty `OtherTicket.php` stub - renamed to match
 * this file's class name, since a filename/classname mismatch is exactly the bug
 * that made several other ticket-related classes unreachable before this change.)
 *
 * @since 4.1.0
 */
final class OtherTicketHandler implements TicketTypeHandlerInterface
{
    /**
     * @param DatabaseInterface $db The database connector object (unused, kept for constructor parity with the other handlers).
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
        return TicketType::Other;
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getMasterData(int $linkedItemId): ?object
    {
        return null;
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getMasterDataLayout(): string
    {
        return 'ticket.masterdata_other';
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function getActions(int $linkedItemId, User $user): array
    {
        return [];
    }
}
