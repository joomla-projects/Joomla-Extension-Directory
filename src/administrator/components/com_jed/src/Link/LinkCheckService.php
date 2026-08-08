<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Link;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Url\UrlCheckResult;
use Jed\Component\Jed\Administrator\Url\UrlFormat;
use Jed\Component\Jed\Administrator\Url\UrlValidatorRegistry;
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * The periodic half of link checking.
 *
 * It owns **no validators**. `P1-08` runs them from the form; this runs the same ones on a
 * schedule, through the same registry and the same guarded fetcher. Two implementations of one
 * check would be maintenance debt that shows up as the form and the cron disagreeing about a
 * developer's URL (4.9).
 *
 * What this owns is everything around the check:
 *
 *  - **which link is due**, oldest first, so a pass is bounded and the stock rotates evenly;
 *  - **politeness**, so 29,000 checks over ~1,100 hosts do not arrive as a burst at any one of
 *    them - the JED must not become a nuisance to the sites it is checking;
 *  - **the counter**, counting *consecutive* failures and resetting on any success;
 *  - **escalation**, developer first and the team only afterwards;
 *  - **transition logging**, which is the only thing that reaches the action log.
 *
 * @since 4.1.0
 */
class LinkCheckService
{
    /**
     * How long a result stands before the link is checked again, in hours.
     *
     * 72 hours over ~29,000 live URLs is roughly 10,000 checks a day - about seven a minute,
     * spread over more than a thousand hosts. The thresholds below are counts of *runs*, so this
     * interval is also what turns them into durations: three failures is nine days.
     *
     * @since 4.1.0
     */
    public const INTERVAL_HOURS = 72;

    /**
     * Consecutive weighted failures before the developer is told, and before the JED team's
     * abandonware signal is raised.
     *
     * Developer first, deliberately. Told at once, the team works cases the developer would have
     * fixed in ten minutes - the same reasoning as the trusted status in `P1-05`.
     *
     * @since 4.1.0
     */
    public const THRESHOLD_DEVELOPER = 3;
    public const THRESHOLD_TEAM      = 6;

    /**
     * Seconds to leave between two requests to the same host inside one run.
     *
     * @since 4.1.0
     */
    public const HOST_DELAY = 2;

    /**
     * @param DatabaseInterface    $db       The database.
     * @param UrlValidatorRegistry $registry The `P1-08` validators.
     *
     * @since 4.1.0
     */
    public function __construct(
        protected readonly DatabaseInterface $db,
        protected readonly UrlValidatorRegistry $registry
    ) {
    }

    /**
     * Make sure com_jed's strings are available.
     *
     * This runs from the scheduler, where nothing has loaded them: the task plugin autoloads its
     * *own* language file and no component has been booted. Without this the developer's ticket
     * arrives with `COM_JED_LINKCHECK_TICKET_SUBJECT` as its subject line - which is exactly what
     * the first run of the escalation test produced.
     *
     * @return void
     *
     * @since 4.1.0
     */
    protected function loadLanguage(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        // The component keeps its strings in its own folder rather than in
        // administrator/language, so that is the base path to load from - the default one finds
        // nothing and fails silently, which is how the first attempt at this still produced a
        // ticket titled COM_JED_LINKCHECK_TICKET_SUBJECT.
        Factory::getApplication()->getLanguage()
            ->load('com_jed', JPATH_ADMINISTRATOR . '/components/com_jed');
    }

    /**
     * Check a batch of the links that are due.
     *
     * @param int  $batchSize How many links to check in this run.
     * @param bool $force     Ignore the interval and take the oldest regardless.
     *
     * @return array{checked: int, ok: int, hard: int, soft: int, semantic: int, notified: int, escalated: int, recovered: int}
     *
     * @since 4.1.0
     */
    public function run(int $batchSize = 200, bool $force = false): array
    {
        $this->loadLanguage();

        $tally = ['checked'  => 0, 'ok' => 0, 'hard' => 0, 'soft' => 0, 'semantic' => 0,
                  'notified' => 0, 'escalated' => 0, 'recovered' => 0];

        $lastSeenHost = [];

        foreach ($this->due($batchSize, $force) as $link) {
            $host = strtolower((string) parse_url($link->url, PHP_URL_HOST));

            // Politeness. The batch is ordered by staleness, not by host, so consecutive rows
            // rarely share one - this only bites where a single site hosts many listings, which
            // is exactly the case it exists for.
            if (isset($lastSeenHost[$host])) {
                $wait = self::HOST_DELAY - (time() - $lastSeenHost[$host]);

                if ($wait > 0) {
                    sleep($wait);
                }
            }

            $lastSeenHost[$host] = time();

            $outcome = $this->checkOne($link);
            $tally['checked']++;
            $tally[$outcome['status']->value]++;

            foreach (['notified', 'escalated', 'recovered'] as $key) {
                $tally[$key] += $outcome[$key] ? 1 : 0;
            }
        }

        return $tally;
    }

    /**
     * Check every checked field of one listing, regardless of when it was last looked at.
     *
     * The on-demand entry point - a queue job after a save, or a moderator asking. Shares
     * everything with the periodic pass except the selection.
     *
     * @param int $extensionId The listing.
     *
     * @return array<string, string>  field => resulting status.
     *
     * @since 4.1.0
     */
    public function checkExtension(int $extensionId): array
    {
        $this->loadLanguage();

        $columns = LinkField::all();

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array_merge(['id'], $columns)))
            ->from($this->db->quoteName('#__jed_extensions'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $extensionId, ParameterType::INTEGER);

        $row = $this->db->setQuery($query)->loadAssoc();

        if ($row === null) {
            return [];
        }

        $results = [];

        foreach ($columns as $field) {
            $url = trim((string) ($row[$field] ?? ''));

            if ($url === '') {
                continue;
            }

            // The whole previous state, not just the counter: this path has to arrive at
            // checkOne() carrying the same information the periodic query supplies, or a link
            // checked on demand loses its ticket, its escalation and the date its outage began.
            $previous = $this->currentState($extensionId, $field);

            $outcome = $this->checkOne((object) [
                'check_id'     => $previous['id'] ?? null,
                'extension_id' => $extensionId,
                'link_type'    => $field,
                'url'          => $url,
                'fail_count'   => (int) ($previous['fail_count'] ?? 0),
                'status'       => (string) ($previous['status'] ?? 'ok'),
                'ticket_id'    => $previous['ticket_id'] ?? null,
                'escalated'    => (int) ($previous['escalated'] ?? 0),
                'first_failed' => $previous['first_failed'] ?? null,
            ]);

            $results[$field] = $outcome['status']->value;
        }

        return $results;
    }

    /**
     * The links due for a check, oldest first.
     *
     * Built from the listings rather than from the state table, so a URL a developer has just
     * added is picked up without anything having to seed a row for it. A `LEFT JOIN` supplies the
     * previous state where there is one.
     *
     * Only listings that are actually public are checked (4.8): there is no point telling a
     * developer their demo link is dead on a listing nobody can see, and it would triple the
     * pass.
     *
     * @param int  $batchSize How many rows.
     * @param bool $force     Ignore the interval.
     *
     * @return object[]
     *
     * @since 4.1.0
     */
    protected function due(int $batchSize, bool $force): array
    {
        $cutoff = Factory::getDate('-' . self::INTERVAL_HOURS . ' hours')->toSql();
        $unions = [];

        foreach (LinkField::all() as $field) {
            $unions[] = 'SELECT e.id AS extension_id, ' . $this->db->quote($field) . ' AS link_type, '
                . 'e.' . $this->db->quoteName($field) . ' AS url, '
                . 'c.id AS check_id, c.status, c.fail_count, c.first_failed, c.ticket_id, c.escalated, c.last_checked '
                . 'FROM ' . $this->db->quoteName('#__jed_extensions', 'e') . ' '
                . 'LEFT JOIN ' . $this->db->quoteName('#__jed_extension_linkchecks', 'c')
                . ' ON c.extension_id = e.id AND c.link_type = ' . $this->db->quote($field) . ' '
                . 'WHERE e.approved = 1 AND e.state = 1 AND e.blocked = 0 AND e.deleted = 0 '
                . 'AND TRIM(IFNULL(e.' . $this->db->quoteName($field) . ", '')) <> ''"
                . ($force ? '' : ' AND (c.last_checked IS NULL OR c.last_checked < ' . $this->db->quote($cutoff) . ')');
        }

        $sql = '(' . implode(') UNION ALL (', $unions) . ') ORDER BY last_checked IS NULL DESC, last_checked ASC';

        return $this->db->setQuery($sql, 0, $batchSize)->loadObjectList() ?: [];
    }

    /**
     * Check one link and write the consequences.
     *
     * @param object $link A row from {@see self::due()}.
     *
     * @return array{status: LinkStatus, notified: bool, escalated: bool, recovered: bool}
     *
     * @since 4.1.0
     */
    protected function checkOne(object $link): array
    {
        $field      = (string) $link->link_type;
        $url        = trim((string) $link->url);
        $wasFailing = ((string) ($link->status ?? 'ok')) !== 'ok' && (int) ($link->fail_count ?? 0) > 0;

        // A URL that cannot even be stored is a hard failure without asking anybody's server.
        if (UrlFormat::check($url) !== []) {
            $result = UrlCheckResult::notice('COM_JED_URLCHECK_REFUSED_FORMAT');
        } else {
            try {
                $result = $this->registry->get(LinkField::validator($field))->validate($url, $this->context($link));
            } catch (Throwable $e) {
                // A validator that throws says nothing about the developer's URL. Treat it as a
                // soft failure so it can never escalate on its own.
                $result = UrlCheckResult::notice('COM_JED_URLCHECK_FAILED_FAILED');
            }
        }

        $status    = LinkStatus::fromResult($result);
        $failCount = $status === LinkStatus::OK ? 0 : (int) ($link->fail_count ?? 0) + 1;

        $notified  = false;
        $escalated = false;
        $recovered = false;

        // A soft failure never advances anything on its own - it is recorded so the team can see
        // it, and the counter stands still.
        if (!$status->counts() && $status !== LinkStatus::OK) {
            $failCount = (int) ($link->fail_count ?? 0);
        }

        if ($status === LinkStatus::OK && $wasFailing) {
            $recovered = true;
            $this->logTransition($link, 'recovered', $result);
            $this->annotateTicket($link, false, $result);
        } elseif ($status !== LinkStatus::OK && !$wasFailing && $status->counts()) {
            $this->logTransition($link, 'broken', $result);
        }

        $row = $this->store($link, $url, $status, $result, $failCount);

        if ($status->counts()) {
            if (
                $row->ticket_id === null
                && LinkField::reaches($field, $failCount, self::THRESHOLD_DEVELOPER)
            ) {
                $ticketId = $this->openDeveloperTicket($link, $url, $result);

                if ($ticketId > 0) {
                    $this->setTicket((int) $row->id, $ticketId);
                    $notified = true;
                }
            }

            if (
                (int) $row->escalated === 0
                && LinkField::reaches($field, $failCount, self::THRESHOLD_TEAM)
            ) {
                $this->raiseSignal((int) $row->id, $link, $url, $result);
                $escalated = true;
            }
        }

        return ['status' => $status, 'notified' => $notified, 'escalated' => $escalated, 'recovered' => $recovered];
    }

    /**
     * What the validators want to know about the listing.
     *
     * @param object $link The link row.
     *
     * @return array<string, mixed>
     *
     * @since 4.1.0
     */
    protected function context(object $link): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['extension_version', 'extension_types', 'requires_registration']))
            ->from($this->db->quoteName('#__jed_extensions'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $link->extension_id, ParameterType::INTEGER);

        return $this->db->setQuery($query)->loadAssoc() ?: [];
    }

    /**
     * Write the current state, inserting the row if this link has never been checked.
     *
     * @param object         $link      The link row.
     * @param string         $url       The URL as checked.
     * @param LinkStatus     $status    The class.
     * @param UrlCheckResult $result    The validator's answer.
     * @param int            $failCount The new consecutive count.
     *
     * @return object  The stored row, with its id, ticket_id and escalated flag.
     *
     * @since 4.1.0
     */
    protected function store(object $link, string $url, LinkStatus $status, UrlCheckResult $result, int $failCount): object
    {
        $now = Factory::getDate()->toSql();

        $row = (object) [
            'extension_id' => (int) $link->extension_id,
            'link_type'    => (string) $link->link_type,
            'url'          => mb_substr($url, 0, 255),
            'last_checked' => $now,
            'status'       => $status->value,
            'http_code'    => $result->status ?: null,
            'message'      => mb_substr($result->message, 0, 255),
            'fail_count'   => $failCount,
            // Cleared on recovery, so "how long has this been down" is always about the current
            // outage rather than about one three years ago.
            'first_failed' => $status === LinkStatus::OK
                ? null
                : ($link->first_failed ?? $now),
            'ticket_id'      => $status === LinkStatus::OK ? null : ($link->ticket_id ?? null),
            'escalated'      => $status === LinkStatus::OK ? 0 : (int) ($link->escalated ?? 0),
            'escalated_time' => null,
        ];

        // The id is resolved from the unique key rather than trusted from the caller. There are
        // two entry points - the periodic query, which supplies it, and the on-demand check,
        // which does not - and a missing id here means an INSERT against a row that already
        // exists, which the unique key turns into a fatal mid-pass.
        $existing = (int) ($link->check_id ?? 0) ?: (int) ($this->currentState((int) $link->extension_id, (string) $link->link_type)['id'] ?? 0);

        if ($existing > 0) {
            $row->id = $existing;
            $this->db->updateObject('#__jed_extension_linkchecks', $row, 'id', true);
        } else {
            $this->db->insertObject('#__jed_extension_linkchecks', $row, 'id');
        }

        return $row;
    }

    /**
     * The stored state of one link, or null if it has never been checked.
     *
     * @param int    $extensionId The listing.
     * @param string $field       The column.
     *
     * @return array<string, mixed>|null
     *
     * @since 4.1.0
     */
    protected function currentState(int $extensionId, string $field): ?array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'status', 'fail_count', 'first_failed', 'ticket_id', 'escalated']))
            ->from($this->db->quoteName('#__jed_extension_linkchecks'))
            ->where($this->db->quoteName('extension_id') . ' = :id')
            ->where($this->db->quoteName('link_type') . ' = :field')
            ->bind(':id', $extensionId, ParameterType::INTEGER)
            ->bind(':field', $field);

        return $this->db->setQuery($query)->loadAssoc();
    }

    /**
     * Record the ticket this link's failure opened.
     *
     * @param int $checkId  The linkcheck row.
     * @param int $ticketId The ticket.
     *
     * @return void
     *
     * @since 4.1.0
     */
    protected function setTicket(int $checkId, int $ticketId): void
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_extension_linkchecks'))
                ->set($this->db->quoteName('ticket_id') . ' = :ticket')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':ticket', $ticketId, ParameterType::INTEGER)
                ->bind(':id', $checkId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Raise the abandonware signal for the JED team.
     *
     * Deliberately **not** a second ticket. Persistently dead links, a persistently failing update
     * server and long inactivity are three symptoms of one thing - a listing nobody is looking
     * after - and they have to feed one case (4.9, 12.3). That case is `P1-19`'s; this sets the
     * flag it reads, and makes it filterable in the backend meanwhile.
     *
     * @param int            $checkId The linkcheck row.
     * @param object         $link    The link row.
     * @param string         $url     The URL.
     * @param UrlCheckResult $result  The validator's answer.
     *
     * @return void
     *
     * @since 4.1.0
     */
    protected function raiseSignal(int $checkId, object $link, string $url, UrlCheckResult $result): void
    {
        $now = Factory::getDate()->toSql();

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_extension_linkchecks'))
                ->set($this->db->quoteName('escalated') . ' = 1')
                ->set($this->db->quoteName('escalated_time') . ' = :now')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':now', $now)
                ->bind(':id', $checkId, ParameterType::INTEGER)
        )->execute();

        $this->logTransition($link, 'escalated', $result);
    }

    /**
     * Open a ticket for the developer, once, when their link has been down long enough.
     *
     * A ticket rather than a bare mail: it keeps the exchange in one place, gives the case a
     * status the developer and the team can both see, and means a reply lands where somebody
     * looks. The mail is the notification *about* the ticket.
     *
     * Only ever one per listing and link type - the ticket id is stored on the row, and this is
     * not reached again while it is set. That is the whole duplicate-ticket rule: a permanently
     * dead link opens one ticket, not one per pass.
     *
     * @param object         $link   The link row.
     * @param string         $url    The URL.
     * @param UrlCheckResult $result The validator's answer.
     *
     * @return int  The ticket id, or 0 if it could not be created.
     *
     * @since 4.1.0
     */
    protected function openDeveloperTicket(object $link, string $url, UrlCheckResult $result): int
    {
        $extensionId = (int) $link->extension_id;

        $listing = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['name', 'owner']))
                ->from($this->db->quoteName('#__jed_extensions'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->loadAssoc();

        if ($listing === null || (int) $listing['owner'] <= 0) {
            return 0;
        }

        $fieldLabel = Text::_('COM_JED_LINKCHECK_FIELD_' . strtoupper((string) $link->link_type));
        $subject    = Text::sprintf('COM_JED_LINKCHECK_TICKET_SUBJECT', $listing['name'], $fieldLabel);
        $body       = Text::sprintf(
            'COM_JED_LINKCHECK_TICKET_BODY',
            $fieldLabel,
            $url,
            Text::_($result->message),
            (string) ($link->first_failed ?? Factory::getDate()->toSql())
        );

        $ticket = (object) [
            'ticket_origin'        => 'linkcheck',
            'ticket_category_type' => 0,
            'ticket_subject'       => mb_substr($subject, 0, 255),
            'ticket_text'          => $body,
            'linked_item_type'     => TicketType::LinkCheck->value,
            'linked_item_id'       => $extensionId,
            'ticket_status'        => '0',
            'allocated_group'      => 0,
            'allocated_to'         => 0,
            'parent_id'            => -1,
            'state'                => 1,
            // The system opened this, not a person. Attributing it to whichever account happened
            // to trigger the cron would be a lie in the audit trail.
            'created_by' => 0,
            'created_on' => Factory::getDate()->toSql(),
        ];

        try {
            $this->db->insertObject('#__jed_tickets', $ticket, 'id');
        } catch (Throwable $e) {
            return 0;
        }

        $ticketId = (int) $ticket->id;

        $this->mailDeveloper((int) $listing['owner'], (string) $listing['name'], $link, $url, $result, $ticketId);

        return $ticketId;
    }

    /**
     * Tell the developer their link is down.
     *
     * @param int            $ownerId  The listing's owner.
     * @param string         $name     The listing name.
     * @param object         $link     The link row.
     * @param string         $url      The URL.
     * @param UrlCheckResult $result   The validator's answer.
     * @param int            $ticketId The ticket just opened.
     *
     * @return void
     *
     * @since 4.1.0
     */
    protected function mailDeveloper(int $ownerId, string $name, object $link, string $url, UrlCheckResult $result, int $ticketId): void
    {
        try {
            $owner = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($ownerId);

            if (empty($owner->email)) {
                return;
            }

            $mailer = new MailTemplate('com_jed.link_broken', Factory::getApplication()->getLanguage()->getTag());
            $mailer->addTemplateData([
                'SITENAME'      => (string) Factory::getApplication()->get('sitename'),
                'EXTENSIONNAME' => $name,
                'LINKTYPE'      => Text::_('COM_JED_LINKCHECK_FIELD_' . strtoupper((string) $link->link_type)),
                'URL'           => $url,
                'REASON'        => Text::_($result->message),
                'SINCE'         => (string) ($link->first_failed ?? ''),
                'TICKETLINK'    => 'index.php?option=com_tickets&view=ticket&id=' . $ticketId,
            ]);
            $mailer->addRecipient($owner->email, $owner->name);
            $mailer->send();
        } catch (Throwable $e) {
            // The ticket is the record. A mail server having a bad day must not undo it.
        }
    }

    /**
     * Note the recovery on the open ticket, and close it.
     *
     * @param object         $link   The link row.
     * @param bool           $broken Unused; kept for symmetry with future transitions.
     * @param UrlCheckResult $result The validator's answer.
     *
     * @return void
     *
     * @since 4.1.0
     */
    protected function annotateTicket(object $link, bool $broken, UrlCheckResult $result): void
    {
        $ticketId = (int) ($link->ticket_id ?? 0);

        if ($ticketId <= 0) {
            return;
        }

        $note = Text::sprintf(
            'COM_JED_LINKCHECK_TICKET_RECOVERED',
            Text::_('COM_JED_LINKCHECK_FIELD_' . strtoupper((string) $link->link_type)),
            (string) $link->url
        );

        // Both values are assigned to variables first: DatabaseQuery::bind() takes its value by
        // reference, and handing it an inline assignment binds something PHP is free to discard.
        $now = Factory::getDate()->toSql();

        try {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__jed_tickets'))
                    ->set($this->db->quoteName('ticket_status') . ' = ' . $this->db->quote('4'))
                    ->set($this->db->quoteName('internal_notes') . ' = CONCAT(IFNULL('
                        . $this->db->quoteName('internal_notes') . ", ''), :note)")
                    ->set($this->db->quoteName('modified_on') . ' = :now')
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':note', $note)
                    ->bind(':now', $now)
                    ->bind(':id', $ticketId, ParameterType::INTEGER)
            )->execute();
        } catch (Throwable $e) {
            // Same reasoning as above: the state table is the record.
        }
    }

    /**
     * Log a state transition, and only a transition.
     *
     * A pass is tens of thousands of checks and almost all of them say the same thing they said
     * last time. Logging every one would flood the action log to the point where nobody could
     * find anything in it (8.15 boundary 2). "Was reachable, now is not" and the reverse are rare,
     * meaningful, and exactly what somebody wants to look up months later.
     *
     * @param object         $link   The link row.
     * @param string         $kind   broken | recovered | escalated.
     * @param UrlCheckResult $result The validator's answer.
     *
     * @return void
     *
     * @since 4.1.0
     */
    protected function logTransition(object $link, string $kind, UrlCheckResult $result): void
    {
        Log::add(
            \sprintf(
                'Link %s: extension %d, %s, %s (%s)',
                $kind,
                (int) $link->extension_id,
                (string) $link->link_type,
                (string) $link->url,
                $result->message
            ),
            $kind === 'recovered' ? Log::INFO : Log::WARNING,
            'com_jed.linkcheck'
        );
    }
}
