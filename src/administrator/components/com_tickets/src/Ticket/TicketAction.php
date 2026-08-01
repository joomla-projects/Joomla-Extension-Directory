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

/**
 * One admin action button offered above a ticket's messages, e.g. "Approve" or
 * "Delete". Rendered as its own small mini-form (layouts/ticket/ticket/action_button.php)
 * so each action's extra hidden fields stay isolated from the others.
 *
 * @since 4.1.0
 */
final class TicketAction
{
    /**
     * @param string      $label          Language key for the button text.
     * @param string      $task           The controller task this action submits, e.g. "extension.approve".
     * @param string      $icon           Icon class suffix (as used by ToolbarHelper-style icon naming).
     * @param string|null $confirmMessage Language key for a JS confirm() prompt before submitting, or null for none.
     * @param array       $hiddenFields   Every hidden input name => value pair the target task needs
     *                                    (e.g. ['extension_id' => 12, 'history_id' => 42], or
     *                                    ['cid[]' => 5] for a bulk AdminController task) - the action
     *                                    button's mini-form renders exactly these, nothing implicit.
     * @param string      $option         The component the task belongs to (e.g. "com_jed") - ticket
     *                                    actions can span multiple components, not just com_tickets.
     *
     * @since 4.1.0
     */
    public function __construct(
        public readonly string $label,
        public readonly string $task,
        public readonly string $icon = 'publish',
        public readonly ?string $confirmMessage = null,
        public readonly array $hiddenFields = [],
        public readonly string $option = 'com_tickets'
    ) {
    }
}
