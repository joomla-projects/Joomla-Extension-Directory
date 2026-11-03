<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Mail;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\MessageVisibility;
use Jed\Component\Vel\Administrator\Enum\ParticipantRole;
use Jed\Component\Vel\Administrator\Enum\ReportKind;
use Jed\Component\Vel\Administrator\Service\AttachmentService;
use Jed\Component\Vel\Administrator\Service\MessageService;
use Jed\Component\Vel\Administrator\Service\ReportService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Where an inbound mail belongs (VEL2-PRD §6.4.3): a reply to a case, found by In-Reply-To or
 * References first and by the [VEL-…]/[VELR-…] subject token second - or a new report.
 *
 * Idempotent on the source's id and on the Message-ID, loop-safe on Auto-Submitted, and
 * capped per sender per day.
 *
 * @since 4.0.0
 */
class ThreadResolver
{
    public const RESULT_REPLY    = 'reply';
    public const RESULT_NEW      = 'new_report';
    public const RESULT_SKIPPED  = 'skipped';
    public const RESULT_DUPLICATE = 'duplicate';

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @param InboundMessage $mail The mail.
     *
     * @return array{result:string, reason:string, advisory_id:?int, report_id:?int}
     *
     * @since 4.0.0
     */
    public function handle(InboundMessage $mail): array
    {
        Factory::getApplication()->getLanguage()->load('com_vel', JPATH_ADMINISTRATOR . '/components/com_vel');

        $email = mb_strtolower(trim($mail->fromEmail));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->skip('no_sender');
        }

        if ($mail->autoSubmitted) {
            return $this->skip('auto_submitted');
        }

        // Our own outbound mail bouncing back, or a provider echo.
        $ours = mb_strtolower(trim((string) ComponentHelper::getParams('com_vel')->get('from_address', '')));

        if ($ours !== '' && $email === $ours) {
            return $this->skip('own_address');
        }

        if ($mail->messageId !== null && $this->seen($mail->messageId)) {
            return ['result' => self::RESULT_DUPLICATE, 'reason' => 'message_id', 'advisory_id' => null, 'report_id' => null];
        }

        if ($this->overDailyCap($email)) {
            return $this->skip('daily_cap');
        }

        [$advisoryId, $reportId] = $this->findCase($mail);

        if ($advisoryId === null && $reportId === null) {
            return $this->newReport($mail);
        }

        return $this->reply($mail, $advisoryId, $reportId);
    }

    /**
     * @param InboundMessage $mail The mail.
     *
     * @return array{0:?int,1:?int}
     *
     * @since 4.0.0
     */
    private function findCase(InboundMessage $mail): array
    {
        $ids = array_values(array_filter(array_merge([$mail->inReplyTo], $mail->references)));

        if ($ids !== []) {
            $row = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName(['advisory_id', 'report_id']))
                    ->from($this->db->quoteName('#__vel_messages'))
                    ->whereIn($this->db->quoteName('message_id'), $ids, ParameterType::STRING)
                    ->order($this->db->quoteName('id') . ' DESC'),
                0,
                1
            )->loadAssoc();

            if ($row) {
                return [$row['advisory_id'] !== null ? (int) $row['advisory_id'] : null, $row['report_id'] !== null ? (int) $row['report_id'] : null];
            }
        }

        [$advisoryId, $reportId] = $mail->subjectReference();

        if ($advisoryId !== null) {
            $exists = (int) $this->db->setQuery(
                $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__vel_advisories'))
                    ->where($this->db->quoteName('id') . ' = :id')->bind(':id', $advisoryId, ParameterType::INTEGER)
            )->loadResult();

            return $exists > 0 ? [$advisoryId, null] : [null, null];
        }

        if ($reportId !== null) {
            $report = $this->db->setQuery(
                $this->db->getQuery(true)->select($this->db->quoteName(['id', 'advisory_id']))->from($this->db->quoteName('#__vel_reports'))
                    ->where($this->db->quoteName('id') . ' = :id')->bind(':id', $reportId, ParameterType::INTEGER)
            )->loadAssoc();

            if ($report) {
                // A report that has since been attached lives on its advisory now.
                return $report['advisory_id'] !== null ? [(int) $report['advisory_id'], null] : [null, (int) $report['id']];
            }
        }

        return [null, null];
    }

    /**
     * @param InboundMessage $mail       The mail.
     * @param int|null       $advisoryId The advisory.
     * @param int|null       $reportId   The report.
     *
     * @return array
     *
     * @since 4.0.0
     */
    private function reply(InboundMessage $mail, ?int $advisoryId, ?int $reportId): array
    {
        $messages = new MessageService($this->db);
        $email    = mb_strtolower(trim($mail->fromEmail));

        // The sender is a participant if they are already in the case; otherwise they join as
        // "other" so the team sees who wrote, and a team member decides what they are.
        $known = null;

        foreach ($messages->participants($advisoryId, $reportId) as $participant) {
            if (mb_strtolower((string) $participant->email) === $email) {
                $known = $participant;

                break;
            }
        }

        if ($known === null) {
            $known = $messages->ensureParticipant($advisoryId, $reportId, ParticipantRole::OTHER, $mail->fromName, $email)['participant'];
        }

        $role       = ParticipantRole::tryFrom((string) $known->role) ?? ParticipantRole::OTHER;
        $visibility = match ($role) {
            ParticipantRole::VENDOR   => MessageVisibility::VENDOR,
            ParticipantRole::REPORTER => MessageVisibility::REPORTER,
            default                   => MessageVisibility::TEAM,
        };

        $body      = InboundMessage::stripQuoted($mail->text);
        $messageId = $messages->record($advisoryId, $reportId, (int) $known->id, 'in', 'email', $visibility, mb_substr($mail->subject, 0, 255), $body, (int) ($known->user_id ?? 0), $mail->messageId, $mail->inReplyTo);

        $this->storeAttachments($mail, $messageId, null);

        Log::add(\sprintf('VEL inbound: reply from %s filed on %s', $email, MessageService::reference($advisoryId, $reportId)), Log::INFO, 'com_vel');

        return ['result' => self::RESULT_REPLY, 'reason' => 'threaded', 'advisory_id' => $advisoryId, 'report_id' => $reportId];
    }

    /**
     * A mail that belongs to no case becomes a report of kind "vulnerability", with the mail as
     * its description. The team triages it like any other.
     *
     * @param InboundMessage $mail The mail.
     *
     * @return array
     *
     * @since 4.0.0
     */
    private function newReport(InboundMessage $mail): array
    {
        $service = new ReportService($this->db, new MessageService($this->db));
        $guest   = new User();

        $data = [
            'reporter_name'        => $mail->fromName !== '' ? $mail->fromName : $mail->fromEmail,
            'reporter_email'       => $mail->fromEmail,
            'reporter_role'        => 'other',
            'extension_name'       => mb_substr(trim($mail->subject) !== '' ? $mail->subject : Text::_('COM_VEL_UNNAMED_EXTENSION'), 0, 255),
            'description'          => $mail->text,
            'consent_to_process'   => 1,
            'share_with_developer' => 1,
        ];

        try {
            $report = $service->submit($data, ReportKind::VULNERABILITY, $guest, null, 'email');
        } catch (\Throwable $e) {
            Log::add('VEL inbound: report from ' . $mail->fromEmail . ' refused: ' . $e->getMessage(), Log::WARNING, 'com_vel');

            return $this->skip('refused: ' . $e->getMessage());
        }

        $reportId = (int) $report->id;

        // Record the mail's Message-ID on the system message so the sender's next mail threads.
        if ($mail->messageId !== null) {
            $this->db->setQuery(
                $this->db->getQuery(true)->update($this->db->quoteName('#__vel_messages'))
                    ->set($this->db->quoteName('message_id') . ' = :mid')
                    ->set($this->db->quoteName('channel') . ' = ' . $this->db->quote('email'))
                    ->where($this->db->quoteName('report_id') . ' = :rid')
                    ->where($this->db->quoteName('message_id') . ' IS NULL')
                    ->bind(':mid', $mail->messageId)
                    ->bind(':rid', $reportId, ParameterType::INTEGER)
            )->execute();
        }

        $this->storeAttachments($mail, null, $reportId);

        Log::add(\sprintf('VEL inbound: new report VELR-%05d from %s', $reportId, $mail->fromEmail), Log::INFO, 'com_vel');

        return ['result' => self::RESULT_NEW, 'reason' => 'no_case', 'advisory_id' => null, 'report_id' => $reportId];
    }

    /**
     * @param InboundMessage $mail      The mail.
     * @param int|null       $messageId The message.
     * @param int|null       $reportId  The report.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function storeAttachments(InboundMessage $mail, ?int $messageId, ?int $reportId): void
    {
        $attachments = new AttachmentService($this->db);
        $allowed     = $attachments->allowedTypes();
        $max         = $attachments->maxBytes();

        foreach ($mail->attachments as $attachment) {
            $name      = (string) ($attachment['name'] ?? 'attachment');
            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            $content   = (string) ($attachment['content'] ?? '');

            if ($content === '' || \strlen($content) > $max || !\in_array($extension, $allowed, true)) {
                Log::add('VEL inbound: attachment refused: ' . $name, Log::INFO, 'com_vel');

                continue;
            }

            try {
                $attachments->storeContents($content, $name, $messageId, $reportId, 0);
            } catch (\Throwable $e) {
                Log::add('VEL inbound: attachment not stored: ' . $e->getMessage(), Log::WARNING, 'com_vel');
            }
        }
    }

    /**
     * @param string $messageId An RFC 5322 id.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private function seen(string $messageId): bool
    {
        return (int) $this->db->setQuery(
            $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__vel_messages'))
                ->where($this->db->quoteName('message_id') . ' = :mid')->bind(':mid', $messageId)
        )->loadResult() > 0;
    }

    /**
     * @param string $email The sender.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private function overDailyCap(string $email): bool
    {
        $cap   = max(1, (int) ComponentHelper::getParams('com_vel')->get('inbound_daily_cap', 20));
        $since = Factory::getDate('now -1 day')->toSql();
        $mail  = 'email';

        $count = (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__vel_messages', 'm'))
                ->innerJoin($this->db->quoteName('#__vel_participants', 'p'), $this->db->quoteName('p.id') . ' = ' . $this->db->quoteName('m.participant_id'))
                ->where($this->db->quoteName('p.email') . ' = :email')
                ->where($this->db->quoteName('m.channel') . ' = :channel')
                ->where($this->db->quoteName('m.created') . ' >= :since')
                ->bind(':email', $email)
                ->bind(':channel', $mail)
                ->bind(':since', $since)
        )->loadResult();

        return $count >= $cap;
    }

    /**
     * @param string $reason Why.
     *
     * @return array
     *
     * @since 4.0.0
     */
    private function skip(string $reason): array
    {
        return ['result' => self::RESULT_SKIPPED, 'reason' => $reason, 'advisory_id' => null, 'report_id' => null];
    }
}
