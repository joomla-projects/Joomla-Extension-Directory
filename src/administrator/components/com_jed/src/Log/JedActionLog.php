<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Log;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Throwable;

/**
 * The one way a JED administrative decision reaches the core action log (`8.15`, `P1-22`).
 *
 * Call sites say *what was decided*; they do not know that an action log exists. They raise
 * `onJedAdministrativeDecision`, and `plg_actionlog_jed` turns it into a row in `#__action_logs`.
 * Turning that plugin off therefore stops the logging and changes nothing else - which is the
 * property that lets the decision "log this or not" be made per installation rather than in code.
 *
 * ## What may be recorded here, and what may not
 *
 * `8.15` draws three boundaries, and they are boundaries about *call sites*, not about this class:
 *
 * 1. **Nothing whose only copy is the log.** Entries are deleted after the retention period
 *    (`P1-22` §4). A block reason, a completed transfer, an audit consent lives in its own table;
 *    what lands here is a copy for the timeline.
 * 2. **No routine automated runs - transitions yes.** Checking 14,900 links every night is not a
 *    decision. "This link answered yesterday and does not answer today" is. Only
 *    {@see self::LINK_BROKEN} and {@see self::LINK_RECOVERED} are raised from the checker, once
 *    per change of state.
 * 3. **No field-level content edits.** `#__jed_extensions_history` already versions every row.
 *    Logging each edit on top would bury the decisions among them.
 *
 * ## Why a plain event and not a typed one
 *
 * `com_tickets` raises two of these actions and must not have to load a `com_jed` event class to
 * do it. A plain {@see Event} with an argument bag keeps the coupling at the level of a string,
 * which is what an extension point is.
 *
 * @since 4.0.0
 */
abstract class JedActionLog
{
    /**
     * The event both components raise and the plugin listens for.
     *
     * @since 4.0.0
     */
    public const EVENT = 'onJedAdministrativeDecision';

    /**
     * A submission or a pending revision was approved. (`P1-02`)
     *
     * @since 4.0.0
     */
    public const EXTENSION_APPROVE = 'extension.approve';

    /**
     * A submission or a pending revision was turned down, with a reason code. (`P1-02`)
     *
     * @since 4.0.0
     */
    public const EXTENSION_REJECT = 'extension.reject';

    /**
     * A listing was blocked by the JED team, with a reason code. (`P1-01`)
     *
     * @since 4.0.0
     */
    public const EXTENSION_BLOCK = 'extension.block';

    /**
     * A block was lifted. (`P1-01`)
     *
     * @since 4.0.0
     */
    public const EXTENSION_UNBLOCK = 'extension.unblock';

    /**
     * A listing was soft-deleted. (`P1-01`)
     *
     * @since 4.0.0
     */
    public const EXTENSION_DELETE = 'extension.delete';

    /**
     * A soft delete was undone. (`P1-01`)
     *
     * @since 4.0.0
     */
    public const EXTENSION_RESTORE = 'extension.restore';

    /**
     * The JED team moved a listing to another account without the old owner's confirmation.
     * (`P1-04`)
     *
     * @since 4.0.0
     */
    public const TRANSFER_FORCE = 'transfer.force';

    /**
     * Somebody was invited as a maintainer of a listing. (`P1-03`)
     *
     * @since 4.0.0
     */
    public const MAINTAINER_ADD = 'maintainer.add';

    /**
     * A maintainer was taken off a listing. (`P1-03`)
     *
     * @since 4.0.0
     */
    public const MAINTAINER_REMOVE = 'maintainer.remove';

    /**
     * A review was published by a moderator. (`P1-02`, `P1-06`)
     *
     * @since 4.0.0
     */
    public const REVIEW_PUBLISH = 'review.publish';

    /**
     * A review was taken off the site by a moderator. (`P1-02`, `P1-06`)
     *
     * @since 4.0.0
     */
    public const REVIEW_UNPUBLISH = 'review.unpublish';

    /**
     * A developer's response to a review was approved. (`P1-06`)
     *
     * @since 4.0.0
     */
    public const RESPONSE_PUBLISH = 'response.publish';

    /**
     * A developer's response was hidden from the listing. (`P1-06`)
     *
     * @since 4.0.0
     */
    public const RESPONSE_UNPUBLISH = 'response.unpublish';

    /**
     * A ticket was handed to somebody. (`com_tickets`)
     *
     * @since 4.0.0
     */
    public const TICKET_ASSIGN = 'ticket.assign';

    /**
     * A ticket was taken away from whoever had it, without being given to anybody else.
     * (`com_tickets`)
     *
     * The inverse of {@see self::TICKET_ASSIGN}, and logged for the same reason: without it the
     * log would say a ticket is somebody's long after it stopped being theirs.
     *
     * @since 4.0.0
     */
    public const TICKET_UNASSIGN = 'ticket.unassign';

    /**
     * A ticket was closed or resolved. (`com_tickets`)
     *
     * @since 4.0.0
     */
    public const TICKET_CLOSE = 'ticket.close';

    /**
     * A ticket that had been closed was reopened. (`com_tickets`)
     *
     * @since 4.0.0
     */
    public const TICKET_REOPEN = 'ticket.reopen';

    /**
     * Somebody was banned from taking part. (`P1-05`)
     *
     * @since 4.0.0
     */
    public const USER_BAN = 'user.ban';

    /**
     * A ban was lifted. (`P1-05`)
     *
     * @since 4.0.0
     */
    public const USER_UNBAN = 'user.unban';

    /**
     * Trusted status - submissions or reviews going live without moderation - was granted.
     * (`P1-05`)
     *
     * @since 4.0.0
     */
    public const USER_TRUST_GRANT = 'user.trust.grant';

    /**
     * Trusted status was taken away again. (`P1-05`)
     *
     * @since 4.0.0
     */
    public const USER_TRUST_REVOKE = 'user.trust.revoke';

    /**
     * One or more individual privileges were changed on an account. (`P1-05`)
     *
     * @since 4.0.0
     */
    public const USER_PRIVILEGE = 'user.privilege';

    /**
     * A link that had been answering stopped answering. (`P1-09`)
     *
     * @since 4.0.0
     */
    public const LINK_BROKEN = 'link.broken';

    /**
     * A link that had been failing started answering again. (`P1-09`)
     *
     * @since 4.0.0
     */
    public const LINK_RECOVERED = 'link.recovered';

    /**
     * Record one administrative decision.
     *
     * Failure is swallowed on purpose. The decision itself is already committed by the time this
     * is reached, and a log that cannot be written must not roll back a block or leave a
     * moderator staring at an exception for an action that in fact succeeded. `8.15` wants the
     * log as a record, never as part of the transaction.
     *
     * ## The acting identity
     *
     * There is no actor parameter, and that is the point. The actor is always whoever this
     * request belongs to, which the core log fills in by itself. A block made in the backend is
     * attributed to the moderator; a link transition found by the scheduler running under cron
     * has no identity and is attributed to nobody; the same check started by hand from the
     * backend is attributed to the person who started it, which is also correct - they ran it.
     * When `P2-01` arrives, its machine accounts authenticate as real users (`6.6` requires one
     * per consumer), so the calling identity lands here without any extra plumbing.
     *
     * @param string $action  One of the constants above.
     * @param string $context The `com_x.type` the entry belongs to, e.g. `com_jed.extension`.
     * @param int    $itemId  The primary key of the thing decided about, for the item filter.
     * @param array  $data    Placeholders for the message: `title`, `itemlink`, and whatever else
     *                        the wording of this action needs.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public static function record(string $action, string $context, int $itemId, array $data = []): void
    {
        try {
            // The container's dispatcher, not `Application::getDispatcher()`: that one reaches
            // the same object through `EventAwareInterface`, which is the Joomla 3 compatibility
            // layer and is deprecated for removal in 7.0.
            $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);

            // Events reach a plugin only once its group has been imported; nothing else in a
            // request that blocks a listing would have imported this one.
            PluginHelper::importPlugin('actionlog', null, true, $dispatcher);

            $dispatcher->dispatch(
                self::EVENT,
                new Event(self::EVENT, [
                    'action'  => $action,
                    'context' => $context,
                    'itemId'  => $itemId,
                    'data'    => $data,
                ])
            );
        } catch (Throwable) {
            // Deliberately silent - see the note above.
        }
    }

    /**
     * Make com_jed's strings available to whoever is about to build a log message.
     *
     * Call this **before** the `Text::_()` calls that fill `$data`, not after: what goes into the
     * log is the finished sentence, so a key that was not resolved at that moment is stored
     * unresolved for good.
     *
     * The base path is the component folder. com_jed keeps its strings with itself rather than in
     * `administrator/language`, and `load('com_jed', JPATH_ADMINISTRATOR)` finds nothing while
     * reporting success - the same trap {@see \Jed\Component\Jed\Administrator\Link\LinkCheckService}
     * documents, and the one that first put "banned Ole Ottosen COM_JED_USERACCESS_BANNED_UNTIL"
     * into the log during testing.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public static function loadWording(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        Factory::getApplication()->getLanguage()
            ->load('com_jed', JPATH_ADMINISTRATOR . '/components/com_jed');
    }
}
