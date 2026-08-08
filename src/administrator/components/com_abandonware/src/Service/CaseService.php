<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Service;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Abandonware\Administrator\Enum\CaseSource;
use Jed\Component\Abandonware\Administrator\Enum\CaseStatus;
use Jed\Component\Abandonware\Administrator\Enum\Resolution;
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

/**
 * The case object 4.10 asks for, and the only place a case changes hands.
 *
 * Everything that can move a case lives here - the public form, the three automated signals, the
 * backend buttons and the scheduled pass all call into this class rather than writing the table.
 * That is not tidiness for its own sake: two of this plan's acceptance criteria are invariants
 * ("cannot be marked abandoned without a recorded contact attempt", "at most one open ticket per
 * extension") and an invariant enforced in four places is enforced in three.
 *
 * @since 4.1.0
 */
class CaseService
{
    /**
     * Fallback grace period, in days, if the component has no configuration yet.
     *
     * The real value is `grace_days` in the component options, because it is a policy number
     * (4.10): how long a developer gets to answer before a public marker goes up is a decision
     * about people, not about software.
     *
     * @since 4.1.0
     */
    public const DEFAULT_GRACE_DAYS = 30;

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Read a policy number out of the component configuration.
     *
     * @param string $key      The option name.
     * @param int    $fallback What to use when the component has no options saved yet.
     *
     * @return int
     *
     * @since 4.1.0
     */
    public function option(string $key, int $fallback): int
    {
        $value = ComponentHelper::getParams('com_abandonware')->get($key, null);

        return $value === null || $value === '' ? $fallback : (int) $value;
    }

    /**
     * Make com_abandonware's strings available.
     *
     * The scheduled pass runs with only the task plugin's own language file loaded and no
     * component booted, so without this the developer's contact mail arrives titled
     * `COM_ABANDONWARE_OWNER_CONTACT_EMAIL_SUBJECT`. `P1-09` hit exactly this and the fix is the
     * same: load from the component's own folder, because that is where the strings are - the
     * default base path is `administrator/language` and finds nothing, silently.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function loadLanguage(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        Factory::getApplication()->getLanguage()
            ->load('com_abandonware', JPATH_ADMINISTRATOR . '/components/com_abandonware');
    }

    /**
     * The open case for a listing, if there is one.
     *
     * @param int $extensionId The listing.
     *
     * @return object|null
     *
     * @since 4.1.0
     */
    public function findOpenCase(int $extensionId): ?object
    {
        if ($extensionId <= 0) {
            return null;
        }

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jed_abandonware_cases'))
                ->where($this->db->quoteName('open_extension_id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * @param int $caseId The case.
     *
     * @return object|null
     *
     * @since 4.1.0
     */
    public function load(int $caseId): ?object
    {
        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jed_abandonware_cases'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $caseId, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * Open a case for a listing, or add the signal to the one already open.
     *
     * This method **is** "one case, not three tickets" (4.9, 12.3). A dead download link, an
     * update server that stopped answering and four years of silence are three symptoms of one
     * thing, and each of them calls this. The first one opens a case and a ticket; the second and
     * third find that case and append themselves to its signal list, changing nothing else.
     *
     * @param int        $extensionId The listing.
     * @param CaseSource $source      What raised it.
     * @param string     $detail      A sentence describing this particular signal.
     * @param int        $actorId     Who acted, or 0 for automation.
     *
     * @return object  The open case, new or existing.
     *
     * @since 4.1.0
     */
    public function raise(int $extensionId, CaseSource $source, string $detail, int $actorId = 0): object
    {
        if ($extensionId <= 0) {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_NO_EXTENSION'));
        }

        $existing = $this->findOpenCase($extensionId);

        if ($existing !== null) {
            $this->addSignal((int) $existing->id, $source, $detail);

            return $this->load((int) $existing->id) ?? $existing;
        }

        $listing = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['name', 'extension_version', 'developer_url', 'developer_email']))
                ->from($this->db->quoteName('#__jed_extensions'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->loadAssoc();

        if ($listing === null) {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_NO_EXTENSION'));
        }

        $now = Factory::getDate()->toSql();

        $case = (object) [
            'extension_id'      => $extensionId,
            'extension_name'    => (string) $listing['name'],
            'extension_version' => (string) $listing['extension_version'],
            'extension_url'     => (string) $listing['developer_url'],
            'developer_name'    => '',
            'status'            => CaseStatus::RECEIVED->value,
            'source'            => $source->value,
            'published'         => 0,
            'signals'           => json_encode([$this->signalEntry($source, $detail)]),
            'created'           => $now,
            'created_by'        => $actorId,
        ];

        $this->db->insertObject('#__jed_abandonware_cases', $case, 'id');

        $this->ensureTicket((int) $case->id);

        return $this->load((int) $case->id) ?? $case;
    }

    /**
     * Open a case for something the JED does not list.
     *
     * The free-text half of 4.10's first substantive change. A report can concern an extension the
     * JED never carried, and dropping those on the floor would lose exactly the reports that no
     * other system is going to catch.
     *
     * @param array<string, string> $tuple   name, version, url, developer_name.
     * @param CaseSource            $source  What raised it.
     * @param string                $detail  A sentence describing the signal.
     * @param int                   $actorId Who acted, or 0.
     *
     * @return object  The new case.
     *
     * @since 4.1.0
     */
    public function raiseUnlisted(array $tuple, CaseSource $source, string $detail, int $actorId = 0): object
    {
        $now = Factory::getDate()->toSql();

        $case = (object) [
            'extension_id'      => null,
            'extension_name'    => mb_substr(trim($tuple['name'] ?? ''), 0, 255),
            'extension_version' => mb_substr(trim($tuple['version'] ?? ''), 0, 100),
            'extension_url'     => mb_substr(trim($tuple['url'] ?? ''), 0, 255),
            'developer_name'    => mb_substr(trim($tuple['developer_name'] ?? ''), 0, 255),
            'status'            => CaseStatus::RECEIVED->value,
            'source'            => $source->value,
            'published'         => 0,
            'signals'           => json_encode([$this->signalEntry($source, $detail)]),
            'created'           => $now,
            'created_by'        => $actorId,
        ];

        $this->db->insertObject('#__jed_abandonware_cases', $case, 'id');

        $this->ensureTicket((int) $case->id);

        return $this->load((int) $case->id) ?? $case;
    }

    /**
     * Append a signal to a case without changing its status.
     *
     * @param int        $caseId The case.
     * @param CaseSource $source What fired.
     * @param string     $detail What it said.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function addSignal(int $caseId, CaseSource $source, string $detail): void
    {
        $case = $this->load($caseId);

        if ($case === null) {
            return;
        }

        $signals = $this->signalsOf($case);
        $entry   = $this->signalEntry($source, $detail);

        // Same signal, same sentence, already recorded: the scheduled pass runs every few hours
        // and would otherwise grow the list without adding information.
        foreach ($signals as $known) {
            if (($known['source'] ?? '') === $entry['source'] && ($known['detail'] ?? '') === $entry['detail']) {
                return;
            }
        }

        $signals[] = $entry;
        $encoded   = json_encode(array_slice($signals, -20));
        $now       = Factory::getDate()->toSql();

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_abandonware_cases'))
                ->set($this->db->quoteName('signals') . ' = :signals')
                ->set($this->db->quoteName('modified') . ' = :now')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':signals', $encoded)
                ->bind(':now', $now)
                ->bind(':id', $caseId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * The signals recorded on a case, decoded.
     *
     * @param object $case The case row.
     *
     * @return array<int, array<string, string>>
     *
     * @since 4.1.0
     */
    public function signalsOf(object $case): array
    {
        $decoded = json_decode((string) ($case->signals ?? ''), true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param CaseSource $source What fired.
     * @param string     $detail What it said.
     *
     * @return array<string, string>
     *
     * @since 4.1.0
     */
    private function signalEntry(CaseSource $source, string $detail): array
    {
        return [
            'source' => $source->value,
            'detail' => mb_substr($detail, 0, 500),
            'time'   => Factory::getDate()->toSql(),
        ];
    }

    /**
     * Move a case to a new status, refusing moves the process does not allow.
     *
     * @param int        $caseId  The case.
     * @param CaseStatus $next    Where it is going.
     * @param int        $actorId Who is moving it.
     *
     * @return void
     *
     * @throws RuntimeException  The move is not legal from the case's current status.
     *
     * @since 4.1.0
     */
    public function transition(int $caseId, CaseStatus $next, int $actorId): void
    {
        $case = $this->load($caseId);

        if ($case === null) {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_CASE_NOT_FOUND'));
        }

        $current = CaseStatus::tryFrom((string) $case->status) ?? CaseStatus::RECEIVED;

        if ($current === $next) {
            return;
        }

        if (!$current->canMoveTo($next)) {
            throw new RuntimeException(Text::sprintf(
                'COM_ABANDONWARE_ERROR_ILLEGAL_TRANSITION',
                Text::_($current->label()),
                Text::_($next->label())
            ));
        }

        $this->writeStatus($caseId, $next, $actorId);
    }

    /**
     * Write a status with no further checking. Private on purpose - everything public that changes
     * a status goes through {@see transition()} or one of the workflow methods below, both of
     * which have already established that the move is allowed.
     *
     * @param int        $caseId  The case.
     * @param CaseStatus $status  The new status.
     * @param int        $actorId Who did it.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function writeStatus(int $caseId, CaseStatus $status, int $actorId): void
    {
        $now   = Factory::getDate()->toSql();
        $value = $status->value;

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jed_abandonware_cases'))
            ->set($this->db->quoteName('status') . ' = :status')
            ->set($this->db->quoteName('modified') . ' = :now')
            ->set($this->db->quoteName('modified_by') . ' = :actor')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':status', $value)
            ->bind(':now', $now)
            ->bind(':actor', $actorId, ParameterType::INTEGER)
            ->bind(':id', $caseId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }

    /**
     * Step 3: write to the owner and start the grace period.
     *
     * 4.10 calls this the most important step and the one most likely to be skipped, and the
     * reason is worth restating where the code is: an extension with no release for three years is
     * not necessarily abandoned - it may simply be finished. Skipping the contact attempt produces
     * the misjudgements 13.4.5 describes for the recency factor, with more at stake, because what
     * is being decided is whether to say something in public about somebody's product.
     *
     * The mail is best-effort; the record is not. If the mail server is down the contact attempt
     * still happened as far as this table is concerned - but the method says so in its return
     * value, so a caller can tell the team the developer may not have heard.
     *
     * @param int    $caseId    The case.
     * @param int    $actorId   Who is making the attempt.
     * @param string $note      What was said, or how contact was made outside the system.
     * @param int    $graceDays Override for the configured grace period.
     *
     * @return bool  Whether a mail actually went out.
     *
     * @throws RuntimeException  The case is not in a status from which the owner can be contacted.
     *
     * @since 4.1.0
     */
    public function recordContact(int $caseId, int $actorId, string $note = '', int $graceDays = 0): bool
    {
        $case = $this->load($caseId);

        if ($case === null) {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_CASE_NOT_FOUND'));
        }

        $current = CaseStatus::tryFrom((string) $case->status) ?? CaseStatus::RECEIVED;

        if (!$current->canMoveTo(CaseStatus::OWNER_CONTACTED) && $current !== CaseStatus::OWNER_CONTACTED) {
            throw new RuntimeException(Text::sprintf(
                'COM_ABANDONWARE_ERROR_ILLEGAL_TRANSITION',
                Text::_($current->label()),
                Text::_(CaseStatus::OWNER_CONTACTED->label())
            ));
        }

        $graceDays = $graceDays > 0 ? $graceDays : $this->option('grace_days', self::DEFAULT_GRACE_DAYS);
        $now       = Factory::getDate();
        $until     = Factory::getDate('now +' . $graceDays . ' days')->toSql();
        $nowSql    = $now->toSql();
        $status    = CaseStatus::OWNER_CONTACTED->value;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_abandonware_cases'))
                ->set($this->db->quoteName('status') . ' = :status')
                ->set($this->db->quoteName('contact_time') . ' = :now')
                ->set($this->db->quoteName('contact_by') . ' = :actor')
                ->set($this->db->quoteName('contact_note') . ' = :note')
                ->set($this->db->quoteName('grace_until') . ' = :until')
                ->set($this->db->quoteName('modified') . ' = :now2')
                ->set($this->db->quoteName('modified_by') . ' = :actor2')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':status', $status)
                ->bind(':now', $nowSql)
                ->bind(':now2', $nowSql)
                ->bind(':actor', $actorId, ParameterType::INTEGER)
                ->bind(':actor2', $actorId, ParameterType::INTEGER)
                ->bind(':note', $note)
                ->bind(':until', $until)
                ->bind(':id', $caseId, ParameterType::INTEGER)
        )->execute();

        return $this->mailOwner($case, $until);
    }

    /**
     * Send the owner-contact template to the owner and every accepted maintainer.
     *
     * Owner **and** maintainers, because the question a case asks is whether anybody is still
     * reachable, not whether one named account is. Read from `owner` and an accepted maintainer
     * row, never `created_by` - authorship does not follow a transfer, so a mail keyed on it would
     * go to somebody who handed the listing on years ago.
     *
     * @param object $case  The case row.
     * @param string $until When the grace period ends.
     *
     * @return bool  Whether at least one recipient was written to.
     *
     * @since 4.1.0
     */
    private function mailOwner(object $case, string $until): bool
    {
        $extensionId = (int) ($case->extension_id ?? 0);

        if ($extensionId <= 0) {
            // Nothing listed, nobody to write to. Not a failure - the team contacts the developer
            // out of band and records it in the note.
            return false;
        }

        $this->loadLanguage();

        $accepted   = 1;
        $recipients = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('DISTINCT ' . $this->db->quoteName('u.id'))
                ->from($this->db->quoteName('#__users', 'u'))
                ->join(
                    'INNER',
                    $this->db->quoteName('#__jed_extensions', 'e'),
                    $this->db->quoteName('e.owner') . ' = ' . $this->db->quoteName('u.id')
                )
                ->where($this->db->quoteName('e.id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->loadColumn();

        $maintainers = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('user_id'))
                ->from($this->db->quoteName('#__jed_extensions_maintainers'))
                ->where($this->db->quoteName('extension_id') . ' = :eid')
                ->where($this->db->quoteName('state') . ' = :accepted')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
                ->bind(':accepted', $accepted, ParameterType::INTEGER)
        )->loadColumn();

        $userIds = array_unique(array_map('intval', array_merge($recipients ?: [], $maintainers ?: [])));
        $userIds = array_filter($userIds, static fn (int $id): bool => $id > 0);

        if ($userIds === []) {
            return false;
        }

        $reason = $this->signalSummary($case);
        $sent   = false;

        foreach ($userIds as $userId) {
            try {
                $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

                if (empty($user->email)) {
                    continue;
                }

                $mailer = new MailTemplate('com_abandonware.owner_contact', Factory::getApplication()->getLanguage()->getTag());
                $mailer->addTemplateData([
                    'SITENAME'      => (string) Factory::getApplication()->get('sitename'),
                    'EXTENSIONNAME' => (string) $case->extension_name,
                    'REASON'        => $reason,
                    'GRACEUNTIL'    => $until,
                    'LISTINGLINK'   => 'index.php?option=com_jed&view=extension&id=' . $extensionId,
                    'TICKETLINK'    => (int) ($case->ticket_id ?? 0) > 0
                        ? 'index.php?option=com_tickets&view=ticket&id=' . (int) $case->ticket_id
                        : '',
                ]);
                $mailer->addRecipient($user->email, $user->name);
                $mailer->send();

                $sent = true;
            } catch (Throwable $e) {
                // The record of the attempt is the table row, which is already written. A mail
                // server having a bad day must not roll back a step of the process - but the
                // caller is told nothing went out, which is the part the team needs to know.
                Log::add(
                    \sprintf('Abandonware case %d: owner mail to user %d failed: %s', (int) $case->id, $userId, $e->getMessage()),
                    Log::WARNING,
                    'com_abandonware'
                );
            }
        }

        return $sent;
    }

    /**
     * A one-line summary of why the case exists, for the developer's mail.
     *
     * @param object $case The case row.
     *
     * @return string
     *
     * @since 4.1.0
     */
    public function signalSummary(object $case): string
    {
        $signals = $this->signalsOf($case);

        if ($signals === []) {
            return Text::_((CaseSource::tryFrom((string) $case->source) ?? CaseSource::MANUAL)->label());
        }

        $lines = [];

        foreach ($signals as $signal) {
            $source  = CaseSource::tryFrom((string) ($signal['source'] ?? '')) ?? CaseSource::MANUAL;
            $lines[] = Text::_($source->label()) . ': ' . (string) ($signal['detail'] ?? '');
        }

        return implode("\n", $lines);
    }

    /**
     * Move every case whose grace period has run out.
     *
     * @return int  How many moved.
     *
     * @since 4.1.0
     */
    public function expireGracePeriods(): int
    {
        $now       = Factory::getDate()->toSql();
        $contacted = CaseStatus::OWNER_CONTACTED->value;

        $ids = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__jed_abandonware_cases'))
                ->where($this->db->quoteName('status') . ' = :status')
                ->where($this->db->quoteName('grace_until') . ' IS NOT NULL')
                ->where($this->db->quoteName('grace_until') . ' < :now')
                ->bind(':status', $contacted)
                ->bind(':now', $now)
        )->loadColumn();

        foreach ($ids ?: [] as $id) {
            $this->writeStatus((int) $id, CaseStatus::GRACE_EXPIRED, 0);
        }

        return \count($ids ?: []);
    }

    /**
     * Conclude that an extension is abandoned.
     *
     * The gate is `contact_time`, not the status. A status is a column somebody can edit; the
     * timestamp is the record that the attempt was actually made, and this plan's acceptance
     * criteria name it: *a case cannot be marked abandoned without a recorded contact attempt*.
     * {@see CaseStatus::allowedNext()} already prevents the transition from `received` or
     * `reviewing`, and this second check is what makes the guarantee hold even if a row is edited
     * into `owner_contacted` directly.
     *
     * @param int  $caseId  The case.
     * @param int  $actorId Who concluded it.
     * @param bool $publish Whether it goes into the public list.
     *
     * @return void
     *
     * @throws RuntimeException  No contact attempt is recorded, or the move is not legal.
     *
     * @since 4.1.0
     */
    public function markAbandoned(int $caseId, int $actorId, ?bool $publish = null): void
    {
        $case = $this->load($caseId);

        if ($case === null) {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_CASE_NOT_FOUND'));
        }

        if (empty($case->contact_time)) {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_NO_CONTACT_ATTEMPT'));
        }

        $current = CaseStatus::tryFrom((string) $case->status) ?? CaseStatus::RECEIVED;

        if (!$current->canMoveTo(CaseStatus::ABANDONED)) {
            throw new RuntimeException(Text::sprintf(
                'COM_ABANDONWARE_ERROR_ILLEGAL_TRANSITION',
                Text::_($current->label()),
                Text::_(CaseStatus::ABANDONED->label())
            ));
        }

        $publish ??= (bool) $this->option('auto_publish', 0);

        $now       = Factory::getDate()->toSql();
        $status    = CaseStatus::ABANDONED->value;
        $published = $publish ? 1 : 0;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_abandonware_cases'))
                ->set($this->db->quoteName('status') . ' = :status')
                ->set($this->db->quoteName('abandoned_time') . ' = :now')
                ->set($this->db->quoteName('abandoned_by') . ' = :actor')
                ->set($this->db->quoteName('published') . ' = :published')
                ->set($this->db->quoteName('modified') . ' = :now2')
                ->set($this->db->quoteName('modified_by') . ' = :actor2')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':status', $status)
                ->bind(':now', $now)
                ->bind(':now2', $now)
                ->bind(':actor', $actorId, ParameterType::INTEGER)
                ->bind(':actor2', $actorId, ParameterType::INTEGER)
                ->bind(':published', $published, ParameterType::INTEGER)
                ->bind(':id', $caseId, ParameterType::INTEGER)
        )->execute();

        Log::add(
            \sprintf('Abandonware case %d marked abandoned by user %d', $caseId, $actorId),
            Log::WARNING,
            'com_abandonware'
        );
    }

    /**
     * Close a case with an outcome.
     *
     * Closing is what releases the extension from the unique key, so a later signal about the same
     * listing opens a fresh case rather than reviving a concluded one. That is deliberate: an
     * extension adopted in 2026 and abandoned again in 2029 is two cases, and reading them as one
     * would lose the fact that somebody did take it on.
     *
     * @param int        $caseId     The case.
     * @param Resolution $resolution How it ended.
     * @param int        $actorId    Who closed it.
     * @param string     $note       Appended to the internal notes.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function resolve(int $caseId, Resolution $resolution, int $actorId, string $note = ''): void
    {
        $case = $this->load($caseId);

        if ($case === null) {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_CASE_NOT_FOUND'));
        }

        $current = CaseStatus::tryFrom((string) $case->status) ?? CaseStatus::RECEIVED;
        $closing = $resolution->closingStatus();

        if (!$current->canMoveTo($closing)) {
            throw new RuntimeException(Text::sprintf(
                'COM_ABANDONWARE_ERROR_ILLEGAL_TRANSITION',
                Text::_($current->label()),
                Text::_($closing->label())
            ));
        }

        $now        = Factory::getDate()->toSql();
        $status     = $closing->value;
        $resolvedAs = $resolution->value;

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jed_abandonware_cases'))
            ->set($this->db->quoteName('status') . ' = :status')
            ->set($this->db->quoteName('resolution') . ' = :resolution')
            ->set($this->db->quoteName('resolved_time') . ' = :now')
            ->set($this->db->quoteName('resolved_by') . ' = :actor')
            // A closed case leaves the public list whatever it said before. Someone reading the
            // list is being told "this is not maintained"; once that is no longer the JED's
            // position, leaving it up is the misjudgement 4.10 warns about, just a slower one.
            ->set($this->db->quoteName('published') . ' = 0')
            ->set($this->db->quoteName('modified') . ' = :now2')
            ->set($this->db->quoteName('modified_by') . ' = :actor2')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':status', $status)
            ->bind(':resolution', $resolvedAs)
            ->bind(':now', $now)
            ->bind(':now2', $now)
            ->bind(':actor', $actorId, ParameterType::INTEGER)
            ->bind(':actor2', $actorId, ParameterType::INTEGER)
            ->bind(':id', $caseId, ParameterType::INTEGER);

        if ($note !== '') {
            $stamped = "\n[" . $now . '] ' . $note;
            $query->set($this->db->quoteName('internal_notes') . ' = CONCAT(IFNULL('
                . $this->db->quoteName('internal_notes') . ", ''), :note)")
                ->bind(':note', $stamped);
        }

        $this->db->setQuery($query)->execute();

        $this->closeTicket($case, $resolution);
    }

    /**
     * Give a case its ticket, once.
     *
     * 4.10 step 2 asks for a ticket for the JED team, at most one per extension. The "at most one"
     * half is two rules working together: the unique key allows a listing only one open case, and
     * this method only ever creates a ticket for a case that has none. A signal arriving every few
     * hours therefore produces one ticket, not one per pass - the same rule `P1-09` applies to the
     * developer's link ticket.
     *
     * `linked_item_id` is the **case**, not the extension. A case can exist without a listing, and
     * what the team needs in front of them is the case with all its signals.
     *
     * @param int $caseId The case.
     *
     * @return int  The ticket id, or 0 if none could be created.
     *
     * @since 4.1.0
     */
    public function ensureTicket(int $caseId): int
    {
        $case = $this->load($caseId);

        if ($case === null) {
            return 0;
        }

        if ((int) ($case->ticket_id ?? 0) > 0) {
            return (int) $case->ticket_id;
        }

        $this->loadLanguage();

        $name    = (string) $case->extension_name !== '' ? (string) $case->extension_name : Text::_('COM_ABANDONWARE_UNNAMED_EXTENSION');
        $subject = Text::sprintf('COM_ABANDONWARE_TICKET_SUBJECT', $name);
        $body    = Text::sprintf(
            'COM_ABANDONWARE_TICKET_BODY',
            $name,
            (string) $case->extension_version,
            (string) $case->extension_url,
            $this->signalSummary($case)
        );

        $ticket = (object) [
            'ticket_origin'        => 'abandonware',
            'ticket_category_type' => 0,
            'ticket_subject'       => mb_substr($subject, 0, 255),
            'ticket_text'          => $body,
            'linked_item_type'     => TicketType::Abandonware->value,
            'linked_item_id'       => $caseId,
            'ticket_status'        => '0',
            'allocated_group'      => 0,
            'allocated_to'         => 0,
            'parent_id'            => -1,
            'state'                => 1,
            // Automation opened this, not whichever account happened to run the cron.
            'created_by' => 0,
            'created_on' => Factory::getDate()->toSql(),
        ];

        try {
            $this->db->insertObject('#__jed_tickets', $ticket, 'id');
        } catch (Throwable $e) {
            Log::add(
                \sprintf('Abandonware case %d: ticket could not be opened: %s', $caseId, $e->getMessage()),
                Log::ERROR,
                'com_abandonware'
            );

            return 0;
        }

        $ticketId = (int) $ticket->id;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_abandonware_cases'))
                ->set($this->db->quoteName('ticket_id') . ' = :ticket')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':ticket', $ticketId, ParameterType::INTEGER)
                ->bind(':id', $caseId, ParameterType::INTEGER)
        )->execute();

        return $ticketId;
    }

    /**
     * Close the case's ticket when the case closes.
     *
     * @param object     $case       The case row as it was before closing.
     * @param Resolution $resolution How it ended.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function closeTicket(object $case, Resolution $resolution): void
    {
        $ticketId = (int) ($case->ticket_id ?? 0);

        if ($ticketId <= 0) {
            return;
        }

        $this->loadLanguage();

        $now  = Factory::getDate()->toSql();
        $note = "\n[" . $now . '] ' . Text::sprintf('COM_ABANDONWARE_TICKET_CLOSED', Text::_($resolution->label()));

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
            // The case is the record.
        }
    }
}
