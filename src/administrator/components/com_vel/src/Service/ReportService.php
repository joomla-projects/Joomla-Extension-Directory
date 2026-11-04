<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Service;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisorySource;
use Jed\Component\Vel\Administrator\Enum\CreditRole;
use Jed\Component\Vel\Administrator\Enum\MessageVisibility;
use Jed\Component\Vel\Administrator\Enum\ParticipantRole;
use Jed\Component\Vel\Administrator\Enum\ReportKind;
use Jed\Component\Vel\Administrator\Enum\ReportState;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

/**
 * Intake (VEL2-PRD §6.4): every rule about who may file, what is kept, and what happens next.
 *
 * The public form, the fix-notice form, the e-mail intake and the backend all call this. The
 * rules - consent, rate limits, the JED report privilege and its bans for accounts, guest
 * verification - live here and nowhere else, for the reason com_abandonware's ReportService
 * gives: a rule enforced in three places is enforced in two.
 *
 * @since 4.0.0
 */
class ReportService
{
    /**
     * @var MessageService
     *
     * @since 4.0.0
     */
    private MessageService $messages;

    /**
     * @param DatabaseInterface   $db       The database driver.
     * @param MessageService|null $messages The conversation service.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db, ?MessageService $messages = null)
    {
        $this->messages = $messages ?? new MessageService($db);
    }

    /**
     * @param string $key      An option.
     * @param mixed  $fallback Default.
     *
     * @return mixed
     *
     * @since 4.0.0
     */
    private function option(string $key, mixed $fallback): mixed
    {
        $value = ComponentHelper::getParams('com_vel')->get($key, null);

        return $value === null || $value === '' ? $fallback : $value;
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    private function loadLanguage(): void
    {
        Factory::getApplication()->getLanguage()->load('com_vel', JPATH_ADMINISTRATOR . '/components/com_vel');
    }

    /**
     * A hash of the visitor's address: deduplicates and rate-limits without storing the address.
     *
     * @param string $ip The address.
     *
     * @return string|null  32 raw bytes, or null for an empty address.
     *
     * @since 4.0.0
     */
    public static function ipHash(string $ip): ?string
    {
        $ip = trim($ip);

        if ($ip === '') {
            return null;
        }

        $secret = (string) Factory::getApplication()->get('secret', '');

        return hash('sha256', $secret . '|' . $ip, true);
    }

    /**
     * Whether this person may file now. Throws with the reason when not.
     *
     * @param User        $user   The current user (a guest has id 0).
     * @param string      $email  The address given on the form.
     * @param string|null $ipHash The visitor's address hash.
     *
     * @return void
     *
     * @throws RuntimeException  Not allowed, with the reason.
     *
     * @since 4.0.0
     */
    public function assertMayReport(User $user, string $email, ?string $ipHash): void
    {
        $this->loadLanguage();

        $userId = (int) $user->id;

        if ($userId <= 0 && (int) $this->option('allow_guest_reports', 1) !== 1) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_LOGIN_REQUIRED'));
        }

        // An account that the JED has barred from reporting is barred here too.
        if ($userId > 0 && class_exists('\\Jed\\Component\\Jed\\Administrator\\Access\\JedAccessHelper')) {
            try {
                \Jed\Component\Jed\Administrator\Access\JedAccessHelper::assertMay($userId, \Jed\Component\Jed\Administrator\Access\Privilege::REPORT);
            } catch (RuntimeException $e) {
                throw $e;
            } catch (Throwable $e) {
                // com_jed not booted in this context; its bans do not apply.
            }
        }

        $limit  = max(1, (int) $this->option('report_rate_limit', 5));
        $window = max(1, (int) $this->option('report_rate_window_hours', 24));
        $since  = Factory::getDate('now -' . $window . ' hours')->toSql();
        $email  = mb_strtolower(trim($email));

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__vel_reports'))
            ->where($this->db->quoteName('created') . ' >= :since')
            ->bind(':since', $since);

        $or = [];

        if ($userId > 0) {
            $or[] = $this->db->quoteName('reporter_user_id') . ' = :uid';
            $query->bind(':uid', $userId, ParameterType::INTEGER);
        }

        if ($email !== '') {
            $or[] = $this->db->quoteName('reporter_email') . ' = :email';
            $query->bind(':email', $email);
        }

        if ($ipHash !== null) {
            $or[] = $this->db->quoteName('reporter_ip_hash') . ' = :ip';
            $query->bind(':ip', $ipHash);
        }

        if ($or === []) {
            return;
        }

        $query->where('(' . implode(' OR ', $or) . ')');

        if ((int) $this->db->setQuery($query)->loadResult() >= $limit) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_RATE_LIMIT', $limit, $window));
        }
    }

    /**
     * File a report.
     *
     * @param array       $data    The validated form data.
     * @param ReportKind  $kind    Vulnerability or fix notice.
     * @param User        $user    The current user.
     * @param string|null $ipHash  The visitor's address hash.
     * @param string      $channel web | email.
     * @param array       $uploads $_FILES-style arrays to attach.
     *
     * @return object  The report row, with `verification_sent` (bool) and `reference`.
     *
     * @throws RuntimeException  Refused.
     *
     * @since 4.0.0
     */
    public function submit(array $data, ReportKind $kind, User $user, ?string $ipHash, string $channel = 'web', array $uploads = []): object
    {
        $this->loadLanguage();

        $userId = (int) $user->id;

        // An account's address is the account's, not the form's: a logged-in reporter cannot
        // file as somebody else by editing a read-only field.
        $email = mb_strtolower(trim((string) ($userId > 0 ? $user->email : ($data['reporter_email'] ?? ''))));
        $name  = trim((string) ($userId > 0 ? ($user->name ?: ($data['reporter_name'] ?? '')) : ($data['reporter_name'] ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_EMAIL_REQUIRED'));
        }

        $this->assertMayReport($user, $email, $ipHash);

        // Consent is the lawful basis for keeping the row at all.
        if ((int) ($data['consent_to_process'] ?? 0) !== 1) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_CONSENT_REQUIRED'));
        }

        $needsVerification = $userId <= 0 && (int) $this->option('guest_verification_required', 1) === 1 && $channel === 'web';
        $now               = Factory::getDate()->toSql();
        $token             = $needsVerification ? bin2hex(random_bytes(32)) : null;

        $advisoryId = null;

        if (!empty($data['advisory_reference'])) {
            $advisoryId = (new AdvisoryRepository($this->db))->resolveReference((string) $data['advisory_reference']) ?: null;
        }

        $row = (object) [
            'kind'                     => $kind->value,
            'state'                    => $needsVerification ? ReportState::VERIFYING->value : ReportState::NEW->value,
            'advisory_id'              => $advisoryId,
            'reporter_user_id'         => max(0, $userId),
            'reporter_name'            => mb_substr($name, 0, 255),
            'reporter_email'           => mb_substr($email, 0, 255),
            'reporter_organisation'    => mb_substr(trim((string) ($data['reporter_organisation'] ?? '')), 0, 255),
            'reporter_role'            => \in_array((string) ($data['reporter_role'] ?? ''), ['researcher', 'developer', 'site_owner', 'other'], true) ? (string) $data['reporter_role'] : 'other',
            'reporter_wants_credit'    => (int) ($data['reporter_wants_credit'] ?? 1) === 1 ? 1 : 0,
            'reporter_credit_name'     => mb_substr(trim((string) ($data['reporter_credit_name'] ?? '')), 0, 255),
            'share_with_developer'     => (int) ($data['share_with_developer'] ?? 1) === 1 ? 1 : 0,
            'extension_id'             => (int) ($data['extension_id'] ?? 0) > 0 ? (int) $data['extension_id'] : null,
            'extension_name'           => mb_substr(trim((string) ($data['extension_name'] ?? '')), 0, 255),
            'extension_version'        => mb_substr(trim((string) ($data['extension_version'] ?? '')), 0, 100),
            'extension_url'            => mb_substr(trim((string) ($data['extension_url'] ?? '')), 0, 255),
            'developer_name'           => mb_substr(trim((string) ($data['developer_name'] ?? '')), 0, 255),
            'developer_email'          => mb_substr(trim((string) ($data['developer_email'] ?? '')), 0, 255),
            'vulnerability_class'      => mb_substr(strtoupper(trim((string) ($data['vulnerability_class'] ?? ''))), 0, 16),
            'description'              => (string) ($data['description'] ?? ''),
            'steps_to_reproduce'       => (string) ($data['steps_to_reproduce'] ?? ''),
            'impact'                   => (string) ($data['impact'] ?? ''),
            'actively_exploited'       => \in_array((string) ($data['actively_exploited'] ?? ''), ['unknown', 'no', 'yes'], true) ? (string) $data['actively_exploited'] : 'unknown',
            'publicly_disclosed'       => (int) ($data['publicly_disclosed'] ?? 0) === 1 ? 1 : 0,
            'disclosure_url'           => mb_substr(trim((string) ($data['disclosure_url'] ?? '')), 0, 255),
            'developer_contact_status' => \in_array((string) ($data['developer_contact_status'] ?? ''), ['not_contacted', 'contacted_no_reply', 'contacted_replied', 'i_am_developer', 'fixed_publicly'], true) ? (string) $data['developer_contact_status'] : 'not_contacted',
            'patch_url'                => mb_substr(trim((string) ($data['patch_url'] ?? '')), 0, 255),
            'fixed_version'            => mb_substr(trim((string) ($data['fixed_version'] ?? '')), 0, 50),
            'tracking_reference'       => mb_substr(trim((string) ($data['tracking_reference'] ?? '')), 0, 255),
            'additional_info'          => (string) ($data['additional_info'] ?? ''),
            'consent_to_process'       => 1,
            'consent_time'             => $now,
            'reporter_ip_hash'         => $ipHash,
            'verify_token'             => $token !== null ? hash('sha256', $token) : null,
            'verified_time'            => $needsVerification ? null : $now,
            'channel'                  => \in_array($channel, ['web', 'email', 'legacy'], true) ? $channel : 'web',
            'created'                  => $now,
            'created_by'               => max(0, $userId),
        ];

        $this->db->insertObject('#__vel_reports', $row, 'id');

        $reportId = (int) $row->id;

        foreach ($uploads as $upload) {
            if (\is_array($upload)) {
                try {
                    (new AttachmentService($this->db))->storeUpload($upload, null, $reportId, $userId);
                } catch (RuntimeException $e) {
                    // The report is in; the file was refused, and the reporter is told.
                    Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
                }
            }
        }

        $reference = \sprintf('VELR-%05d', $reportId);

        $row->reference         = $reference;
        $row->verification_sent = false;

        if ($needsVerification) {
            $row->verification_sent = $this->messages->sendTemplate('com_vel.report_verify', $email, $name, [
                'REFERENCE'     => $reference,
                'EXTENSIONNAME' => (string) $row->extension_name,
                'VERIFYLINK'    => $this->verifyLink($token),
            ], $reference);

            return $row;
        }

        $this->activate($row, $userId);

        return $row;
    }

    /**
     * What happens once a report is real: a participant with a token, a system message, the
     * "received" mail with the case link.
     *
     * @param object $report The report row.
     * @param int    $userId The reporter's account, or 0.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function activate(object $report, int $userId): void
    {
        $reportId  = (int) $report->id;
        $reference = \sprintf('VELR-%05d', $reportId);
        $kind      = ReportKind::tryFrom((string) $report->kind) ?? ReportKind::VULNERABILITY;

        $result      = $this->messages->ensureParticipant(null, $reportId, ParticipantRole::REPORTER, (string) $report->reporter_name, (string) $report->reporter_email, $userId > 0 ? $userId : null);
        $participant = $result['participant'];
        $token       = $result['token'] ?? $this->messages->rekey((int) $participant->id);

        $this->messages->record(null, $reportId, (int) $participant->id, 'in', (string) $report->channel, MessageVisibility::REPORTER, Text::sprintf('COM_VEL_MSG_REPORT_FILED', Text::_($kind->label())), $this->summary($report), $userId);

        if ($kind === ReportKind::FIX_NOTICE) {
            $this->messages->sendTemplate('com_vel.fix_received', (string) $report->reporter_email, (string) $report->reporter_name, [
                'REFERENCE'     => $reference,
                'EXTENSIONNAME' => (string) $report->extension_name,
                'FIXEDVERSION'  => (string) $report->fixed_version,
            ], $reference);

            return;
        }

        $this->messages->sendTemplate('com_vel.report_received', (string) $report->reporter_email, (string) $report->reporter_name, [
            'REFERENCE'     => $reference,
            'EXTENSIONNAME' => (string) $report->extension_name,
            'CASELINK'      => MessageService::caseLink($token),
        ], $reference);
    }

    /**
     * The verification link.
     *
     * @param string $token The plain token.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function verifyLink(string $token): string
    {
        $path = rtrim((string) $this->option('public_list_path', '/vulnerable-extensions'), '/');

        return rtrim(\Joomla\CMS\Uri\Uri::root(), '/') . $path . '/report?task=report.verify&token=' . $token;
    }

    /**
     * Confirm a guest's e-mail address.
     *
     * @param string $token The plain token from the link.
     *
     * @return object|null  The report now active, or null for an unknown or used token.
     *
     * @since 4.0.0
     */
    public function verify(string $token): ?object
    {
        $token = strtolower(trim($token));

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $hash      = hash('sha256', $token);
        $verifying = ReportState::VERIFYING->value;

        $report = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_reports'))
                ->where($this->db->quoteName('verify_token') . ' = :hash')
                ->where($this->db->quoteName('state') . ' = :state')
                ->bind(':hash', $hash)
                ->bind(':state', $verifying)
        )->loadObject();

        if ($report === null) {
            return null;
        }

        $now = Factory::getDate()->toSql();
        $new = ReportState::NEW->value;
        $id  = (int) $report->id;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_reports'))
                ->set($this->db->quoteName('state') . ' = :new')
                ->set($this->db->quoteName('verified_time') . ' = :now')
                ->set($this->db->quoteName('verify_token') . ' = NULL')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':new', $new)
                ->bind(':now', $now)
                ->bind(':id', $id, ParameterType::INTEGER)
        )->execute();

        $report->state         = $new;
        $report->verified_time = $now;

        $this->activate($report, 0);

        return $report;
    }

    /**
     * Delete guest reports that were never confirmed.
     *
     * @return int  How many.
     *
     * @since 4.0.0
     */
    public function purgeUnverified(): int
    {
        $days      = max(1, (int) $this->option('unverified_purge_days', 7));
        $before    = Factory::getDate('now -' . $days . ' days')->toSql();
        $verifying = ReportState::VERIFYING->value;

        $ids = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__vel_reports'))
                ->where($this->db->quoteName('state') . ' = :state')
                ->where($this->db->quoteName('created') . ' < :before')
                ->bind(':state', $verifying)
                ->bind(':before', $before)
        )->loadColumn() ?: [];

        foreach ($ids as $id) {
            $this->delete((int) $id);
        }

        return \count($ids);
    }

    /**
     * Delete a report with everything it carried.
     *
     * @param int $reportId The report.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function delete(int $reportId): void
    {
        $attachments = new AttachmentService($this->db);

        foreach ($attachments->forThread([], [$reportId]) as $attachment) {
            $attachments->delete((int) $attachment->id);
        }

        foreach (['#__vel_messages', '#__vel_participants'] as $table) {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->delete($this->db->quoteName($table))
                    ->where($this->db->quoteName('report_id') . ' = :id')
                    ->where($this->db->quoteName('advisory_id') . ' IS NULL')
                    ->bind(':id', $reportId, ParameterType::INTEGER)
            )->execute();
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__vel_reports'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $reportId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * @param int $reportId The report.
     *
     * @return object|null
     *
     * @since 4.0.0
     */
    public function load(int $reportId): ?object
    {
        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_reports'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $reportId, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * Record the team's decision about a report.
     *
     * @param int         $reportId The report.
     * @param ReportState $state    triaged | duplicate | rejected | spam | accepted.
     * @param int         $actorId  Who.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function setState(int $reportId, ReportState $state, int $actorId): void
    {
        $now   = Factory::getDate()->toSql();
        $value = $state->value;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_reports'))
                ->set($this->db->quoteName('state') . ' = :state')
                ->set($this->db->quoteName('modified') . ' = :now')
                ->set($this->db->quoteName('modified_by') . ' = :actor')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':state', $value)
                ->bind(':now', $now)
                ->bind(':actor', $actorId, ParameterType::INTEGER)
                ->bind(':id', $reportId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Attach a report to an advisory: the report is accepted, its thread and participants move
     * across, a credit is added when the reporter wants one, and a fix notice's data lands in
     * the internal notes for the editor to act on.
     *
     * @param int $reportId   The report.
     * @param int $advisoryId The advisory.
     * @param int $actorId    Who.
     *
     * @return void
     *
     * @throws RuntimeException  Either side missing.
     *
     * @since 4.0.0
     */
    public function attach(int $reportId, int $advisoryId, int $actorId): void
    {
        $this->loadLanguage();

        $report   = $this->load($reportId);
        $service  = new VelService($this->db);
        $advisory = $service->load($advisoryId);

        if ($report === null || $advisory === null) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_ATTACH_MISSING'));
        }

        $now      = Factory::getDate()->toSql();
        $accepted = ReportState::ACCEPTED->value;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_reports'))
                ->set($this->db->quoteName('advisory_id') . ' = :aid')
                ->set($this->db->quoteName('state') . ' = :state')
                ->set($this->db->quoteName('modified') . ' = :now')
                ->set($this->db->quoteName('modified_by') . ' = :actor')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':aid', $advisoryId, ParameterType::INTEGER)
                ->bind(':state', $accepted)
                ->bind(':now', $now)
                ->bind(':actor', $actorId, ParameterType::INTEGER)
                ->bind(':id', $reportId, ParameterType::INTEGER)
        )->execute();

        $this->messages->adopt($reportId, $advisoryId);

        if (empty($advisory->date_reported)) {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__vel_advisories'))
                    ->set($this->db->quoteName('date_reported') . ' = :reported')
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':reported', $report->created)
                    ->bind(':id', $advisoryId, ParameterType::INTEGER)
            )->execute();
        }

        $kind = ReportKind::tryFrom((string) $report->kind) ?? ReportKind::VULNERABILITY;

        if ($kind === ReportKind::VULNERABILITY && (int) $report->reporter_wants_credit === 1) {
            $creditName = trim((string) $report->reporter_credit_name) !== '' ? (string) $report->reporter_credit_name : (string) $report->reporter_name;

            if ($creditName !== '') {
                $exists = (int) $this->db->setQuery(
                    $this->db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($this->db->quoteName('#__vel_advisory_credits'))
                        ->where($this->db->quoteName('advisory_id') . ' = :aid')
                        ->where($this->db->quoteName('report_id') . ' = :rid')
                        ->bind(':aid', $advisoryId, ParameterType::INTEGER)
                        ->bind(':rid', $reportId, ParameterType::INTEGER)
                )->loadResult();

                if ($exists === 0) {
                    $credit = (object) [
                        'advisory_id' => $advisoryId,
                        'name'        => mb_substr($creditName, 0, 255),
                        'role'        => CreditRole::FINDER->value,
                        'user_id'     => (int) $report->reporter_user_id > 0 ? (int) $report->reporter_user_id : null,
                        'report_id'   => $reportId,
                        'is_public'   => 1,
                        'ordering'    => 99,
                    ];

                    $this->db->insertObject('#__vel_advisory_credits', $credit, 'id');
                }
            }
        }

        if ($kind === ReportKind::FIX_NOTICE) {
            $service->appendNote($advisoryId, Text::sprintf(
                'COM_VEL_NOTE_FIX_NOTICE',
                \sprintf('VELR-%05d', $reportId),
                (string) $report->extension_name,
                (string) $report->fixed_version,
                (string) $report->patch_url
            ));
        }

        $this->messages->record($advisoryId, $reportId, null, 'internal', 'system', MessageVisibility::TEAM, Text::_('COM_VEL_MSG_REPORT_ATTACHED'), Text::sprintf('COM_VEL_MSG_REPORT_ATTACHED_BODY', \sprintf('VELR-%05d', $reportId), \sprintf('VEL-%04d', $advisoryId)), $actorId);
    }

    /**
     * Create a draft advisory from a report and attach the report to it.
     *
     * @param int $reportId The report.
     * @param int $actorId  Who.
     *
     * @return int  The new advisory id.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function createAdvisory(int $reportId, int $actorId): int
    {
        $this->loadLanguage();

        $report = $this->load($reportId);

        if ($report === null) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_REPORT_NOT_FOUND'));
        }

        $service   = new VelService($this->db);
        $bridge    = new JedBridge($this->db);
        $extension = trim((string) $report->extension_name) !== '' ? (string) $report->extension_name : Text::_('COM_VEL_UNNAMED_EXTENSION');
        $title     = Text::sprintf('COM_VEL_DRAFT_TITLE_FROM_REPORT', $extension);

        $advisoryId = $service->createDraft([
            'title'          => mb_substr($title, 0, 255),
            'description'    => '<p>' . nl2br(htmlspecialchars((string) $report->description, ENT_QUOTES, 'UTF-8')) . '</p>',
            'date_reported'  => $report->created,
            'exploited'      => (string) $report->actively_exploited,
            'discovery'      => (string) $report->reporter_role === 'developer' ? 'INTERNAL' : ((string) $report->reporter_role === 'researcher' ? 'EXTERNAL' : 'USER'),
            'internal_notes' => Text::sprintf('COM_VEL_NOTE_CREATED_FROM_REPORT', \sprintf('VELR-%05d', $reportId)),
        ], AdvisorySource::REPORT, $actorId);

        // One product from what the reporter said, filled from the JED listing when there is one.
        $defaults = (int) ($report->extension_id ?? 0) > 0 ? ($bridge->productDefaults((int) $report->extension_id) ?? []) : [];
        $host     = (string) parse_url((string) $report->extension_url, PHP_URL_HOST);

        $product = (object) [
            'advisory_id'    => $advisoryId,
            'vendor'         => mb_substr((string) ($defaults['vendor'] ?? ($report->developer_name ?: preg_replace('/^www\./', '', $host))), 0, 255),
            'product'        => mb_substr((string) ($defaults['product'] ?? $extension), 0, 255),
            'extension_id'   => (int) ($report->extension_id ?? 0) > 0 ? (int) $report->extension_id : null,
            'supplier_url'   => mb_substr((string) ($defaults['supplier_url'] ?? $report->extension_url), 0, 255),
            'ext_type'       => $defaults['ext_type'] ?? null,
            'element'        => $defaults['element'] ?? null,
            'folder'         => $defaults['folder'] ?? null,
            'client_id'      => $defaults['client_id'] ?? null,
            'fixed_version'  => mb_substr((string) $report->fixed_version, 0, 50),
            'patch_url'      => mb_substr((string) $report->patch_url, 0, 255),
            'default_status' => 'unaffected',
            'ordering'       => 0,
        ];

        $this->db->insertObject('#__vel_advisory_products', $product, 'id');

        $version = (object) [
            'product_id'   => (int) $product->id,
            'version_from' => '*',
            'version_to'   => trim((string) $report->extension_version) !== '' ? mb_substr((string) $report->extension_version, 0, 50) : '*',
            'version_type' => 'custom',
            'ordering'     => 0,
        ];

        $this->db->insertObject('#__vel_advisory_versions', $version, 'id');

        $cwe = strtoupper(trim((string) $report->vulnerability_class));

        if (preg_match('/^CWE-\d+$/', $cwe)) {
            $name = (string) $this->db->setQuery(
                $this->db->getQuery(true)->select($this->db->quoteName('name'))->from($this->db->quoteName('#__vel_cwe'))
                    ->where($this->db->quoteName('cwe_id') . ' = :cwe')->bind(':cwe', $cwe)
            )->loadResult();

            $this->db->insertObject('#__vel_advisory_cwes', (object) [
                'advisory_id' => $advisoryId,
                'cwe_id'      => $cwe,
                'description' => mb_substr($name !== '' ? $cwe . ' ' . $name : $cwe, 0, 255),
                'ordering'    => 0,
            ], 'id');
        }

        if (trim((string) $report->disclosure_url) !== '' && preg_match('~^https?://~i', (string) $report->disclosure_url)) {
            $this->db->insertObject('#__vel_advisory_references', (object) [
                'advisory_id' => $advisoryId,
                'url'         => mb_substr((string) $report->disclosure_url, 0, 1024),
                'name'        => '',
                'tags'        => json_encode(['third-party-advisory']),
                'ordering'    => 0,
            ], 'id');
        }

        $this->attach($reportId, $advisoryId, $actorId);
        $service->refreshStatus($advisoryId, $actorId);

        return $advisoryId;
    }

    /**
     * The report as a readable block, for the thread's first message and the vendor mail.
     *
     * @param object $report The report.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function summary(object $report): string
    {
        $this->loadLanguage();

        $lines = [];
        $add   = static function (string $label, string $value) use (&$lines): void {
            if (trim($value) !== '') {
                $lines[] = Text::_($label) . ': ' . trim($value);
            }
        };

        $add('COM_VEL_FIELD_EXTENSION_NAME_LABEL', (string) $report->extension_name . ((string) $report->extension_version !== '' ? ' ' . $report->extension_version : ''));
        $add('COM_VEL_FIELD_EXTENSION_URL_LABEL', (string) $report->extension_url);
        $add('COM_VEL_FIELD_DEVELOPER_NAME_LABEL', (string) $report->developer_name);
        $add('COM_VEL_FIELD_VULNERABILITY_CLASS_LABEL', (string) $report->vulnerability_class);
        $add('COM_VEL_FIELD_REPORT_DESCRIPTION_LABEL', "\n" . (string) $report->description);
        $add('COM_VEL_FIELD_STEPS_LABEL', "\n" . (string) $report->steps_to_reproduce);
        $add('COM_VEL_FIELD_IMPACT_LABEL', "\n" . (string) $report->impact);
        $add('COM_VEL_FIELD_ACTIVELY_EXPLOITED_LABEL', (string) $report->actively_exploited);
        $add('COM_VEL_FIELD_DISCLOSURE_URL_LABEL', (string) $report->disclosure_url);
        $add('COM_VEL_FIELD_DEVELOPER_CONTACT_STATUS_LABEL', (string) $report->developer_contact_status);
        $add('COM_VEL_FIELD_FIXED_VERSION_LABEL', (string) $report->fixed_version);
        $add('COM_VEL_FIELD_PATCH_URL_LABEL', (string) $report->patch_url);
        $add('COM_VEL_FIELD_TRACKING_REFERENCE_LABEL', (string) $report->tracking_reference);
        $add('COM_VEL_FIELD_ADDITIONAL_INFO_LABEL', "\n" . (string) $report->additional_info);

        return implode("\n", $lines);
    }

    /**
     * Write to the vendor (VEL2-PRD §6.4.4): participants from the JED listing's owner and
     * maintainers and/or the developer address, one mail each with the report summary the
     * reporter consented to share, the disclosure date, and a case link.
     *
     * @param int    $advisoryId     The advisory.
     * @param int    $actorId        Who.
     * @param string $extraEmail     An address typed by the editor, or ''.
     * @param string $extraName      Its name.
     * @param string $note           Text the editor adds above the summary.
     *
     * @return int  How many mails went out.
     *
     * @throws RuntimeException  Nobody to write to.
     *
     * @since 4.0.0
     */
    public function notifyVendor(int $advisoryId, int $actorId, string $extraEmail = '', string $extraName = '', string $note = ''): int
    {
        $this->loadLanguage();

        $service  = new VelService($this->db);
        $advisory = $service->repository()->find($advisoryId);

        if ($advisory === null) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_ADVISORY_NOT_FOUND'));
        }

        $bridge     = new JedBridge($this->db);
        $recipients = [];

        foreach ((array) $advisory->products as $product) {
            foreach ($bridge->listingContacts((int) ($product->extension_id ?? 0)) as $userId) {
                try {
                    $user = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($userId);

                    if (!empty($user->email)) {
                        $recipients[mb_strtolower($user->email)] = ['name' => (string) $user->name, 'user_id' => $userId];
                    }
                } catch (Throwable $e) {
                    // Unknown account.
                }
            }
        }

        $reports = $this->db->setQuery(
            $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__vel_reports'))
                ->where($this->db->quoteName('advisory_id') . ' = :id')->bind(':id', $advisoryId, ParameterType::INTEGER)
                ->order($this->db->quoteName('created') . ' ASC')
        )->loadObjectList() ?: [];

        foreach ($reports as $report) {
            $email = mb_strtolower(trim((string) $report->developer_email));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[$email] = $recipients[$email] ?? ['name' => (string) $report->developer_name, 'user_id' => null];
            }
        }

        $extraEmail = mb_strtolower(trim($extraEmail));

        if ($extraEmail !== '' && filter_var($extraEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[$extraEmail] = ['name' => trim($extraName), 'user_id' => null];
        }

        if ($recipients === []) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_NO_VENDOR_CONTACT'));
        }

        // What the vendor is shown: the reports whose reporters consented to sharing.
        $summaries = [];

        foreach ($reports as $report) {
            if ((int) $report->share_with_developer === 1 && (string) $report->kind === ReportKind::VULNERABILITY->value) {
                $summaries[] = $this->summary($report);
            }
        }

        $summary = trim($note) !== '' ? trim($note) . "\n\n" : '';
        $summary .= $summaries !== [] ? implode("\n\n---\n\n", $summaries) : Text::_('COM_VEL_VENDOR_SUMMARY_NOT_SHARED') . "\n\n" . (string) $advisory->title;

        $responseDays = max(1, (int) $this->option('vendor_first_response_days', 14));
        $disclosure   = (string) ($advisory->date_public ?: Factory::getDate('now +' . max(1, (int) $this->option('disclosure_days', 90)) . ' days')->toSql());
        $reference    = \sprintf('VEL-%04d', $advisoryId);
        $sent         = 0;

        foreach ($recipients as $email => $info) {
            $result      = $this->messages->ensureParticipant($advisoryId, null, ParticipantRole::VENDOR, (string) $info['name'], (string) $email, $info['user_id']);
            $participant = $result['participant'];
            $token       = $result['token'] ?? $this->messages->rekey((int) $participant->id);

            $ok = $this->messages->sendTemplate('com_vel.vendor_notification', (string) $email, (string) $info['name'], [
                'REFERENCE'      => $reference,
                'EXTENSIONNAME'  => (string) ($advisory->products[0]->product ?? $advisory->title),
                'SUMMARY'        => $summary,
                'DISCLOSUREDATE' => substr($disclosure, 0, 10),
                'RESPONSEDAYS'   => (string) $responseDays,
                'CASELINK'       => MessageService::caseLink($token),
            ], $reference);

            if ($ok) {
                $sent++;
            }

            $this->messages->record($advisoryId, null, (int) $participant->id, 'out', 'email', MessageVisibility::VENDOR, Text::sprintf('COM_VEL_MSG_VENDOR_NOTIFIED_SUBJECT', $reference), $summary, $actorId);
        }

        $now = Factory::getDate()->toSql();

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__vel_advisories'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $advisoryId, ParameterType::INTEGER);

        $set = false;

        if (empty($advisory->date_vendor_notified)) {
            $query->set($this->db->quoteName('date_vendor_notified') . ' = :now')->bind(':now', $now);
            $set = true;
        }

        if (empty($advisory->date_public)) {
            $query->set($this->db->quoteName('date_public') . ' = :public')->bind(':public', $disclosure);
            $set = true;
        }

        if ($set) {
            $this->db->setQuery($query)->execute();
        }

        Log::add(\sprintf('Advisory %d: vendor notified (%d mails) by user %d', $advisoryId, $sent, $actorId), Log::INFO, 'com_vel');

        return $sent;
    }
}
