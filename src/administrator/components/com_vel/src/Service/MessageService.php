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

use Jed\Component\Vel\Administrator\Enum\MessageVisibility;
use Jed\Component\Vel\Administrator\Enum\ParticipantRole;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * The case conversation (VEL2-PRD §6.1.8, §6.4.4): participants, their tokens, the messages
 * between them, and the mail that carries a message out.
 *
 * A case is either a report (before it is attached to anything) or an advisory. Messages and
 * participants carry both keys; when a report is attached to an advisory its thread moves with
 * it ({@see self::adopt()}), so the advisory's thread is the whole history.
 *
 * Tokens: 32 random bytes, hex, shown once in a mail and stored only as a SHA-256 hash. A token
 * identifies a participant in one case and nothing else.
 *
 * @since 4.0.0
 */
class MessageService
{
    /**
     * How long a participant link stays valid.
     *
     * @since 4.0.0
     */
    public const TOKEN_DAYS = 180;

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    // ---------------------------------------------------------------------------------------
    // Participants
    // ---------------------------------------------------------------------------------------

    /**
     * Find or create a participant in a case by e-mail address.
     *
     * @param int|null        $advisoryId The advisory, or null.
     * @param int|null        $reportId   The report, or null.
     * @param ParticipantRole $role       Their role.
     * @param string          $name       Their name.
     * @param string          $email      Their address; the identity key within the case.
     * @param int|null        $userId     Their account, if any.
     *
     * @return array{participant: object, token: ?string}  The row and, when newly created or
     *                                                       re-keyed, the plain token to mail.
     *
     * @since 4.0.0
     */
    public function ensureParticipant(?int $advisoryId, ?int $reportId, ParticipantRole $role, string $name, string $email, ?int $userId = null): array
    {
        $email = mb_strtolower(trim($email));
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__vel_participants'))
            ->where($this->db->quoteName('email') . ' = :email')
            ->bind(':email', $email);

        if ($advisoryId !== null && $advisoryId > 0) {
            $query->where($this->db->quoteName('advisory_id') . ' = :aid')->bind(':aid', $advisoryId, ParameterType::INTEGER);
        } elseif ($reportId !== null && $reportId > 0) {
            $query->where($this->db->quoteName('report_id') . ' = :rid')->bind(':rid', $reportId, ParameterType::INTEGER);
        }

        $existing = $this->db->setQuery($query, 0, 1)->loadObject();

        if ($existing !== null) {
            $token = null;

            // A token that has run out gets renewed so the next mail carries a working link.
            if (!empty($existing->token_expires) && strtotime((string) $existing->token_expires) < time()) {
                $token = $this->rekey((int) $existing->id);
            }

            return ['participant' => $this->db->setQuery(
                $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__vel_participants'))
                    ->where($this->db->quoteName('id') . ' = :id')->bind(':id', $existing->id, ParameterType::INTEGER)
            )->loadObject(), 'token' => $token];
        }

        $token = bin2hex(random_bytes(32));

        $row = (object) [
            'advisory_id'   => $advisoryId > 0 ? $advisoryId : null,
            'report_id'     => $reportId > 0 ? $reportId : null,
            'role'          => $role->value,
            'name'          => mb_substr(trim($name), 0, 255),
            'email'         => mb_substr($email, 0, 255),
            'user_id'       => $userId > 0 ? $userId : null,
            'access_token'  => hash('sha256', $token),
            'token_expires' => Factory::getDate('now +' . self::TOKEN_DAYS . ' days')->toSql(),
            'notify'        => 1,
            'created'       => Factory::getDate()->toSql(),
        ];

        $this->db->insertObject('#__vel_participants', $row, 'id');

        return ['participant' => $row, 'token' => $token];
    }

    /**
     * Issue a fresh token for a participant.
     *
     * @param int $participantId The participant.
     *
     * @return string  The plain token.
     *
     * @since 4.0.0
     */
    public function rekey(int $participantId): string
    {
        $token   = bin2hex(random_bytes(32));
        $hash    = hash('sha256', $token);
        $expires = Factory::getDate('now +' . self::TOKEN_DAYS . ' days')->toSql();

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_participants'))
                ->set($this->db->quoteName('access_token') . ' = :hash')
                ->set($this->db->quoteName('token_expires') . ' = :expires')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':hash', $hash)
                ->bind(':expires', $expires)
                ->bind(':id', $participantId, ParameterType::INTEGER)
        )->execute();

        return $token;
    }

    /**
     * @param string $token A plain token from a URL.
     *
     * @return object|null  The participant, if the token is valid and unexpired.
     *
     * @since 4.0.0
     */
    public function participantByToken(string $token): ?object
    {
        $token = strtolower(trim($token));

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $hash = hash('sha256', $token);
        $now  = Factory::getDate()->toSql();

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_participants'))
                ->where($this->db->quoteName('access_token') . ' = :hash')
                ->where('(' . $this->db->quoteName('token_expires') . ' IS NULL OR ' . $this->db->quoteName('token_expires') . ' >= :now)')
                ->bind(':hash', $hash)
                ->bind(':now', $now)
        )->loadObject() ?: null;
    }

    /**
     * @param int|null $advisoryId The advisory.
     * @param int|null $reportId   The report.
     *
     * @return object[]
     *
     * @since 4.0.0
     */
    public function participants(?int $advisoryId, ?int $reportId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__vel_participants'))
            ->order($this->db->quoteName('created') . ' ASC');

        $this->scope($query, $advisoryId, $reportId);

        return $this->db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Move a report's participants and messages onto the advisory it has been attached to.
     *
     * @param int $reportId   The report.
     * @param int $advisoryId The advisory.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function adopt(int $reportId, int $advisoryId): void
    {
        foreach (['#__vel_participants', '#__vel_messages'] as $table) {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName($table))
                    ->set($this->db->quoteName('advisory_id') . ' = :aid')
                    ->where($this->db->quoteName('report_id') . ' = :rid')
                    ->where('(' . $this->db->quoteName('advisory_id') . ' IS NULL OR ' . $this->db->quoteName('advisory_id') . ' = 0)')
                    ->bind(':aid', $advisoryId, ParameterType::INTEGER)
                    ->bind(':rid', $reportId, ParameterType::INTEGER)
            )->execute();
        }
    }

    // ---------------------------------------------------------------------------------------
    // Messages
    // ---------------------------------------------------------------------------------------

    /**
     * Record a message. Does not send anything; see {@see self::deliver()}.
     *
     * @param int|null          $advisoryId    The advisory.
     * @param int|null          $reportId      The report.
     * @param int|null          $participantId The author (null = the team).
     * @param string            $direction     in | out | internal.
     * @param string            $channel       web | email | system.
     * @param MessageVisibility $visibility    Who may read it.
     * @param string            $subject       Subject.
     * @param string            $body          Plain text.
     * @param int               $actorId       The account that wrote it (0 for a guest).
     * @param string|null       $messageId     RFC 5322 Message-ID, if it came by or goes by mail.
     * @param string|null       $inReplyTo     The id it answers.
     *
     * @return int  The message id.
     *
     * @since 4.0.0
     */
    public function record(?int $advisoryId, ?int $reportId, ?int $participantId, string $direction, string $channel, MessageVisibility $visibility, string $subject, string $body, int $actorId, ?string $messageId = null, ?string $inReplyTo = null): int
    {
        $row = (object) [
            'advisory_id'    => $advisoryId > 0 ? $advisoryId : null,
            'report_id'      => $reportId > 0 ? $reportId : null,
            'participant_id' => $participantId > 0 ? $participantId : null,
            'direction'      => \in_array($direction, ['in', 'out', 'internal'], true) ? $direction : 'out',
            'channel'        => \in_array($channel, ['web', 'email', 'system'], true) ? $channel : 'web',
            'subject'        => mb_substr(trim($subject), 0, 255),
            'body'           => $body,
            'message_id'     => $messageId !== null ? mb_substr($messageId, 0, 255) : null,
            'in_reply_to'    => $inReplyTo !== null ? mb_substr($inReplyTo, 0, 255) : null,
            'visibility'     => $visibility->value,
            'created'        => Factory::getDate()->toSql(),
            'created_by'     => $actorId,
        ];

        $this->db->insertObject('#__vel_messages', $row, 'id');

        return (int) $row->id;
    }

    /**
     * The thread, filtered by what the reader may see.
     *
     * @param int|null        $advisoryId The advisory.
     * @param int|null        $reportId   The report.
     * @param ParticipantRole $reader     Who is reading (TEAM sees everything).
     *
     * @return object[]  Messages with `participant` (object|null) and `attachments` (array).
     *
     * @since 4.0.0
     */
    public function thread(?int $advisoryId, ?int $reportId, ParticipantRole $reader): array
    {
        $query = $this->db->getQuery(true)
            ->select('m.*')
            ->from($this->db->quoteName('#__vel_messages', 'm'))
            ->order($this->db->quoteName('m.created') . ' ASC');

        $this->scope($query, $advisoryId, $reportId, 'm');

        $messages     = $this->db->setQuery($query)->loadObjectList() ?: [];
        $participants = [];

        foreach ($this->participants($advisoryId, $reportId) as $participant) {
            $participants[(int) $participant->id] = $participant;
        }

        $visible = [];

        foreach ($messages as $message) {
            $visibility = MessageVisibility::tryFrom((string) $message->visibility) ?? MessageVisibility::TEAM;

            if (!$visibility->readableBy($reader)) {
                continue;
            }

            $message->participant = $participants[(int) ($message->participant_id ?? 0)] ?? null;
            $message->attachments = [];
            $visible[]            = $message;
        }

        if ($visible !== []) {
            $attachments = (new AttachmentService($this->db))->forThread(
                array_map(static fn (object $m): int => (int) $m->id, $visible),
                $reader === ParticipantRole::TEAM || $reader === ParticipantRole::REPORTER ? [(int) $reportId] : []
            );

            foreach ($attachments as $attachment) {
                foreach ($visible as $message) {
                    if ((int) $message->id === (int) ($attachment->message_id ?? 0)) {
                        $message->attachments[] = $attachment;
                    }
                }
            }
        }

        return $visible;
    }

    /**
     * A message id of our own, so replies thread back to the case.
     *
     * @param string $reference VEL-0042 or VELR-00123.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function newMessageId(string $reference): string
    {
        $host = (string) parse_url(Uri::root(), PHP_URL_HOST) ?: 'vel.joomla.org';

        return '<' . strtolower($reference) . '.' . bin2hex(random_bytes(8)) . '@' . $host . '>';
    }

    /**
     * The public reference of a case.
     *
     * @param int|null $advisoryId The advisory.
     * @param int|null $reportId   The report.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function reference(?int $advisoryId, ?int $reportId): string
    {
        return $advisoryId > 0 ? \sprintf('VEL-%04d', $advisoryId) : \sprintf('VELR-%05d', (int) $reportId);
    }

    /**
     * The case page URL for a participant.
     *
     * @param string $token The plain token.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function caseLink(string $token): string
    {
        $path = rtrim((string) ComponentHelper::getParams('com_vel')->get('public_list_path', '/vulnerable-extensions'), '/');

        return rtrim(Uri::root(), '/') . $path . '/case/' . $token;
    }

    /**
     * Send a recorded message to a participant by mail, with the reference in the subject and
     * a Message-ID recorded for threading.
     *
     * @param int         $messageId   The message.
     * @param object      $participant The recipient.
     * @param string|null $token       Their plain token, if a fresh one is to be included; null
     *                                 keeps the link out of the mail (the case link was sent before).
     *
     * @return bool  Whether the mail went out.
     *
     * @since 4.0.0
     */
    public function deliver(int $messageId, object $participant, ?string $token): bool
    {
        $message = $this->db->setQuery(
            $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__vel_messages'))
                ->where($this->db->quoteName('id') . ' = :id')->bind(':id', $messageId, ParameterType::INTEGER)
        )->loadObject();

        if ($message === null || (int) ($participant->notify ?? 1) !== 1 || (string) $participant->email === '') {
            return false;
        }

        $reference = self::reference($participant->advisory_id !== null ? (int) $participant->advisory_id : null, $participant->report_id !== null ? (int) $participant->report_id : null);
        $rfcId     = (string) ($message->message_id ?: $this->newMessageId($reference));

        if (empty($message->message_id)) {
            $this->db->setQuery(
                $this->db->getQuery(true)->update($this->db->quoteName('#__vel_messages'))
                    ->set($this->db->quoteName('message_id') . ' = :mid')
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':mid', $rfcId)->bind(':id', $messageId, ParameterType::INTEGER)
            )->execute();
        }

        try {
            $this->loadLanguage();

            $mail   = $this->newMailer();
            $mailer = new MailTemplate('com_vel.message', Factory::getApplication()->getLanguage()->getTag(), $mail);
            $mailer->addTemplateData([
                'SITENAME'  => (string) Factory::getApplication()->get('sitename'),
                'REFERENCE' => $reference,
                'SUBJECT'   => (string) $message->subject,
                'MESSAGE'   => (string) $message->body,
                'CASELINK'  => $token !== null ? self::caseLink($token) : Text::_('COM_VEL_MAIL_CASELINK_SENT_BEFORE'),
            ]);
            $mailer->addRecipient((string) $participant->email, (string) $participant->name);

            $this->applySender($mailer, $mail);

            $mail->MessageID = $rfcId;

            if (!empty($message->in_reply_to)) {
                $mail->addCustomHeader('In-Reply-To', (string) $message->in_reply_to);
                $mail->addCustomHeader('References', (string) $message->in_reply_to);
            }

            $mail->addCustomHeader('Auto-Submitted', 'auto-generated');

            $mailer->send();

            return true;
        } catch (Throwable $e) {
            Log::add(\sprintf('VEL message %d: mail to participant %d failed: %s', $messageId, (int) $participant->id, $e->getMessage()), Log::WARNING, 'com_vel');

            return false;
        }
    }

    /**
     * Send a templated mail (report received, verify, vendor notification, published...).
     *
     * @param string $templateId The template.
     * @param string $email      Recipient.
     * @param string $name       Recipient name.
     * @param array  $data       Template tags.
     * @param string $reference  The case reference, for the Message-ID.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function sendTemplate(string $templateId, string $email, string $name, array $data, string $reference = ''): bool
    {
        if (trim($email) === '') {
            return false;
        }

        try {
            $this->loadLanguage();

            $mail   = $this->newMailer();
            $mailer = new MailTemplate($templateId, Factory::getApplication()->getLanguage()->getTag(), $mail);
            $mailer->addTemplateData(array_merge(['SITENAME' => (string) Factory::getApplication()->get('sitename')], $data));
            $mailer->addRecipient($email, $name);

            $this->applySender($mailer, $mail);

            if ($reference !== '') {
                $mail->MessageID = $this->newMessageId($reference);
            }

            $mail->addCustomHeader('Auto-Submitted', 'auto-generated');

            $mailer->send();

            return true;
        } catch (Throwable $e) {
            Log::add(\sprintf('VEL mail %s to %s failed: %s', $templateId, $email, $e->getMessage()), Log::WARNING, 'com_vel');

            return false;
        }
    }

    /**
     * A fresh Mail instance, handed to MailTemplate so the headers that make threading work
     * (Message-ID, In-Reply-To) can be set on it - MailTemplate exposes no accessor for its own.
     *
     * @return Mail
     *
     * @since 4.0.0
     */
    private function newMailer(): Mail
    {
        return Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
    }

    /**
     * The configured From address, so replies land in the polled mailbox.
     *
     * @param MailTemplate $mailer The template mailer.
     * @param Mail         $mail   The underlying Mail.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function applySender(MailTemplate $mailer, Mail $mail): void
    {
        $params = ComponentHelper::getParams('com_vel');
        $from   = trim((string) $params->get('from_address', ''));
        $name   = trim((string) $params->get('from_name', Text::_('COM_VEL_DEFAULT_FROM_NAME')));

        if ($from === '') {
            return;
        }

        try {
            $mail->setSender([$from, $name !== '' ? $name : null]);
            $mailer->setReplyTo($from, $name);
        } catch (Throwable $e) {
            // The site default sender then.
        }
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
     * Constrain a query to a case.
     *
     * @param \Joomla\Database\QueryInterface $query      The query.
     * @param int|null                        $advisoryId The advisory.
     * @param int|null                        $reportId   The report.
     * @param string                          $alias      Table alias, or '' for none.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function scope(\Joomla\Database\QueryInterface $query, ?int $advisoryId, ?int $reportId, string $alias = ''): void
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        if ($advisoryId !== null && $advisoryId > 0) {
            $query->where($this->db->quoteName($prefix . 'advisory_id') . ' = :scope_aid')->bind(':scope_aid', $advisoryId, ParameterType::INTEGER);
        } elseif ($reportId !== null && $reportId > 0) {
            $query->where($this->db->quoteName($prefix . 'report_id') . ' = :scope_rid')->bind(':scope_rid', $reportId, ParameterType::INTEGER);
        } else {
            $query->where('1 = 0');
        }
    }
}
