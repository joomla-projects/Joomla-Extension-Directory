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

/**
 * One implementation per {@see TicketType}: knows how to load and render the
 * "master data" of whatever a ticket of that type is linked to, and which admin
 * actions are available for it. Looked up via {@see TicketTypeHandlerRegistry},
 * replacing the old per-type if-chains in the ticket views.
 *
 * Reused as-is by both the admin and site ticket views - the site view simply
 * never calls getActions(), which is what makes it the simpler, read-only view.
 *
 * @since 4.1.0
 */
interface TicketTypeHandlerInterface
{
    /**
     * @return TicketType The ticket type this handler is responsible for.
     *
     * @since 4.1.0
     */
    public static function type(): TicketType;

    /**
     * Load the linked entity's master data for display.
     *
     * @param int $linkedItemId The ticket's `linked_item_id`.
     *
     * @return object|null Null if the linked row no longer exists (deleted extension, etc).
     *
     * @since 4.1.0
     */
    public function getMasterData(int $linkedItemId): ?object;

    /**
     * @return string The Joomla layout name (LayoutHelper::render) that renders getMasterData()'s result.
     *
     * @since 4.1.0
     */
    public function getMasterDataLayout(): string;

    /**
     * Admin-only action buttons, already filtered by what $user is allowed to do.
     *
     * @param int  $linkedItemId The ticket's `linked_item_id`.
     * @param User $user         The current user.
     *
     * @return TicketAction[]
     *
     * @since 4.1.0
     */
    public function getActions(int $linkedItemId, User $user): array;
}
