<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Actionlog.jed
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Actionlog\Jed\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Component\Actionlogs\Administrator\Plugin\ActionLogPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

/**
 * Writes JED administrative decisions into Joomla's core action log (`8.15`, `P1-22`).
 *
 * The components raise one event, `onJedAdministrativeDecision`, and this turns it into a row in
 * `#__action_logs`. Everything the JED team then gets - the backend list, the filters, the CSV
 * export, the retention job, the privacy integration that `P1-18` leans on - is core's, which is
 * the entire reason for logging here rather than into a table of our own.
 *
 * Turning this plugin off stops the recording and changes nothing else. That is deliberate:
 * `8.15` is a recommendation, and a recommendation you cannot decline is not one.
 *
 * ## What this plugin decides, and what it does not
 *
 * It decides **wording**: which language key an action gets, and which of its values become
 * links. It does **not** decide *whether* something is worth logging - that judgement belongs to
 * the call site, which is the only place that knows whether this was a decision or the 4,800th
 * link check of the night. See `JedActionLog` for the three boundaries that judgement follows.
 *
 * The action strings below are duplicated from that class as literals on purpose. A plugin is
 * loaded on requests where `com_jed` may be disabled or half-installed, and a class constant
 * referencing a component class would turn that into a fatal error while loading the plugin.
 *
 * @since 4.1.0
 */
final class Jed extends ActionLogPlugin implements SubscriberInterface
{
    /**
     * Action to language key.
     *
     * An action missing from here is silently ignored rather than logged with its raw name, so a
     * new event that nobody has written wording for cannot put `extension.quarantine` in front of
     * the team as though it were a sentence.
     *
     * @var array<string, string>
     *
     * @since 4.1.0
     */
    private const MESSAGES = [
        'extension.approve'  => 'PLG_ACTIONLOG_JED_EXTENSION_APPROVE',
        'extension.reject'   => 'PLG_ACTIONLOG_JED_EXTENSION_REJECT',
        'extension.block'    => 'PLG_ACTIONLOG_JED_EXTENSION_BLOCK',
        'extension.unblock'  => 'PLG_ACTIONLOG_JED_EXTENSION_UNBLOCK',
        'extension.delete'   => 'PLG_ACTIONLOG_JED_EXTENSION_DELETE',
        'extension.restore'  => 'PLG_ACTIONLOG_JED_EXTENSION_RESTORE',
        'transfer.force'     => 'PLG_ACTIONLOG_JED_TRANSFER_FORCE',
        'maintainer.add'     => 'PLG_ACTIONLOG_JED_MAINTAINER_ADD',
        'maintainer.remove'  => 'PLG_ACTIONLOG_JED_MAINTAINER_REMOVE',
        'review.publish'     => 'PLG_ACTIONLOG_JED_REVIEW_PUBLISH',
        'review.unpublish'   => 'PLG_ACTIONLOG_JED_REVIEW_UNPUBLISH',
        'response.publish'   => 'PLG_ACTIONLOG_JED_RESPONSE_PUBLISH',
        'response.unpublish' => 'PLG_ACTIONLOG_JED_RESPONSE_UNPUBLISH',
        'ticket.assign'      => 'PLG_ACTIONLOG_JED_TICKET_ASSIGN',
        'ticket.unassign'    => 'PLG_ACTIONLOG_JED_TICKET_UNASSIGN',
        'ticket.close'       => 'PLG_ACTIONLOG_JED_TICKET_CLOSE',
        'ticket.reopen'      => 'PLG_ACTIONLOG_JED_TICKET_REOPEN',
        'user.ban'           => 'PLG_ACTIONLOG_JED_USER_BAN',
        'user.unban'         => 'PLG_ACTIONLOG_JED_USER_UNBAN',
        'user.trust.grant'   => 'PLG_ACTIONLOG_JED_USER_TRUST_GRANT',
        'user.trust.revoke'  => 'PLG_ACTIONLOG_JED_USER_TRUST_REVOKE',
        'user.privilege'     => 'PLG_ACTIONLOG_JED_USER_PRIVILEGE',
        'link.broken'        => 'PLG_ACTIONLOG_JED_LINK_BROKEN',
        'link.recovered'     => 'PLG_ACTIONLOG_JED_LINK_RECOVERED',
    ];

    /**
     * Where `{itemlink}` points, per context.
     *
     * `%d` is the item id. A context that is not here simply gets no link - the entry is still
     * readable, it just does not take you anywhere.
     *
     * @var array<string, string>
     *
     * @since 4.1.0
     */
    private const ITEM_LINKS = [
        'com_jed.extension'  => 'index.php?option=com_jed&task=extension.edit&id=%d',
        'com_jed.review'     => 'index.php?option=com_jed&task=review.edit&id=%d',
        'com_jed.useraccess' => 'index.php?option=com_users&task=user.edit&id=%d',
        'com_tickets.ticket' => 'index.php?option=com_tickets&task=ticket.edit&id=%d',
    ];

    /**
     * Load the plugin's language file so the backend list can render the messages.
     *
     * @var boolean
     *
     * @since 4.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return array<string, string>
     *
     * @since 4.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return ['onJedAdministrativeDecision' => 'onJedAdministrativeDecision'];
    }

    /**
     * Turn one raised decision into one log row.
     *
     * @param EventInterface $event Carrying `action`, `context`, `itemId` and `data`.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function onJedAdministrativeDecision(EventInterface $event): void
    {
        $action  = (string) $event->getArgument('action', '');
        $context = (string) $event->getArgument('context', '');
        $itemId  = (int) $event->getArgument('itemId', 0);

        if (!isset(self::MESSAGES[$action]) || $context === '') {
            return;
        }

        // Only scalars survive: the message is stored as JSON and rendered with
        // htmlspecialchars(), so an array or an object would come out as "Array" at best.
        $message = ['id' => $itemId];

        foreach ((array) $event->getArgument('data', []) as $key => $value) {
            if ($value === null || \is_scalar($value)) {
                $message[(string) $key] = $value === null ? '' : (string) $value;
            }
        }

        if (!isset($message['itemlink']) && isset(self::ITEM_LINKS[$context]) && $itemId > 0) {
            $message['itemlink'] = \sprintf(self::ITEM_LINKS[$context], $itemId);
        }

        $this->addLog([$message], self::MESSAGES[$action], $context);
    }
}
