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
use Joomla\Database\DatabaseInterface;

/**
 * Maps a {@see TicketType} to the {@see TicketTypeHandlerInterface} responsible
 * for it. No DI container is involved - com_tickets' service provider and
 * TicketsComponent::boot() are both empty stubs, and nothing else in this repo
 * resolves site-side services through the container across the admin/site
 * boundary, so a plain static factory keeps this callable from both.
 *
 * @since 4.1.0
 */
final class TicketTypeHandlerRegistry
{
    /**
     * @var array<int, TicketTypeHandlerInterface>
     *
     * @since 4.1.0
     */
    private array $handlers = [];

    /**
     * Build a registry with every built-in handler registered.
     *
     * @param DatabaseInterface $db The database connector object.
     *
     * @return self
     *
     * @since 4.1.0
     */
    public static function createDefault(DatabaseInterface $db): self
    {
        $registry = new self();
        $registry->register(new ExtensionTicketHandler($db));
        $registry->register(new ReviewTicketHandler($db));
        $registry->register(new DeveloperresponseTicketHandler($db));
        $registry->register(new OtherTicketHandler($db));

        return $registry;
    }

    /**
     * @param TicketTypeHandlerInterface $handler The handler to register.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function register(TicketTypeHandlerInterface $handler): void
    {
        $this->handlers[$handler::type()->value] = $handler;
    }

    /**
     * Get the handler for a ticket type, falling back to the "Other"/unknown
     * handler if none is registered for it.
     *
     * @param TicketType $type The ticket type.
     *
     * @return TicketTypeHandlerInterface
     *
     * @since 4.1.0
     */
    public function get(TicketType $type): TicketTypeHandlerInterface
    {
        return $this->handlers[$type->value] ?? $this->handlers[TicketType::Other->value];
    }
}
