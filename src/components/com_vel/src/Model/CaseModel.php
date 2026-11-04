<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\MessageVisibility;
use Jed\Component\Vel\Administrator\Enum\ParticipantRole;
use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Jed\Component\Vel\Administrator\Service\AttachmentService;
use Jed\Component\Vel\Administrator\Service\MessageService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * The participant's case page (VEL2-PRD §6.4.4): what one reporter or vendor may see and do,
 * identified by their token and nothing else.
 *
 * What it never shows: internal notes, messages not visible to the participant's role, the
 * other party's address, the advisory before it is published (a vendor sees the draft; a
 * reporter sees it only if the team shares it).
 *
 * @since 4.0.0
 */
class CaseModel extends FormModel
{
    /**
     * @var object|null
     *
     * @since 4.0.0
     */
    private ?object $participant = null;

    /**
     * @var string
     *
     * @since 4.0.0
     */
    private string $token = '';

    /**
     * @return object|null  The participant the request's token names.
     *
     * @since 4.0.0
     */
    public function getParticipant(): ?object
    {
        if ($this->participant !== null) {
            return $this->participant;
        }

        $this->token       = (string) Factory::getApplication()->getInput()->getString('token', '');
        $this->participant = (new MessageService($this->getDatabase()))->participantByToken($this->token);

        return $this->participant;
    }

    /**
     * @return string
     *
     * @since 4.0.0
     */
    public function getToken(): string
    {
        $this->getParticipant();

        return $this->token;
    }

    /**
     * @return ParticipantRole
     *
     * @since 4.0.0
     */
    public function getRole(): ParticipantRole
    {
        $participant = $this->getParticipant();

        return $participant ? (ParticipantRole::tryFrom((string) $participant->role) ?? ParticipantRole::OTHER) : ParticipantRole::OTHER;
    }

    /**
     * @return object[]  The messages this participant may read.
     *
     * @since 4.0.0
     */
    public function getThread(): array
    {
        $participant = $this->getParticipant();

        if ($participant === null) {
            return [];
        }

        return (new MessageService($this->getDatabase()))->thread(
            $participant->advisory_id !== null ? (int) $participant->advisory_id : null,
            $participant->report_id !== null ? (int) $participant->report_id : null,
            $this->getRole()
        );
    }

    /**
     * The advisory as this participant may see it: the full record for a vendor (the draft
     * preview the vendor notification promises), the public record for anybody once it is
     * published, nothing otherwise.
     *
     * @return object|null
     *
     * @since 4.0.0
     */
    public function getAdvisory(): ?object
    {
        $participant = $this->getParticipant();

        if ($participant === null || (int) ($participant->advisory_id ?? 0) <= 0) {
            return null;
        }

        $repository = new AdvisoryRepository($this->getDatabase());
        $advisory   = $repository->find((int) $participant->advisory_id);

        if ($advisory === null) {
            return null;
        }

        $state = AdvisoryState::tryFrom((string) $advisory->state) ?? AdvisoryState::DRAFT;

        if ($state->isPublic() || $this->getRole() === ParticipantRole::VENDOR) {
            unset($advisory->internal_notes, $advisory->published_json, $advisory->assigned_to);

            return $advisory;
        }

        return null;
    }

    /**
     * The reference shown on the page and in mails.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function getReference(): string
    {
        $participant = $this->getParticipant();

        if ($participant === null) {
            return '';
        }

        return MessageService::reference(
            $participant->advisory_id !== null ? (int) $participant->advisory_id : null,
            $participant->report_id !== null ? (int) $participant->report_id : null
        );
    }

    /**
     * @param array $data     Data for the form.
     * @param bool  $loadData Whether to load the data.
     *
     * @return Form|false
     *
     * @since 4.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_vel.casereply', 'casereply', ['control' => 'jform', 'load_data' => $loadData]) ?: false;
    }

    /**
     * @return array
     *
     * @since 4.0.0
     */
    protected function loadFormData()
    {
        return (array) Factory::getApplication()->getUserState('com_vel.case.data', []);
    }

    /**
     * A participant's reply: recorded as visible to their role and the team, mailed to the
     * team's mailbox (so the team hears without opening the backend) and to nobody else.
     *
     * @param string $body    The message.
     * @param array  $uploads Attachments.
     *
     * @return int  The message id.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function reply(string $body, array $uploads = []): int
    {
        $participant = $this->getParticipant();

        if ($participant === null) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_CASE_TOKEN'));
        }

        $body = trim($body);

        if ($body === '') {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_MESSAGE_EMPTY'));
        }

        $role       = $this->getRole();
        $visibility = $role === ParticipantRole::VENDOR ? MessageVisibility::VENDOR : MessageVisibility::REPORTER;
        $messages   = new MessageService($this->getDatabase());
        $advisoryId = $participant->advisory_id !== null ? (int) $participant->advisory_id : null;
        $reportId   = $participant->report_id !== null ? (int) $participant->report_id : null;
        $userId     = (int) ($participant->user_id ?? 0);

        $messageId = $messages->record($advisoryId, $reportId, (int) $participant->id, 'in', 'web', $visibility, Text::sprintf('COM_VEL_MSG_REPLY_FROM', (string) ($participant->name ?: $participant->email)), $body, $userId);

        $attachments = new AttachmentService($this->getDatabase());

        foreach ($uploads as $upload) {
            if (\is_array($upload) && !empty($upload['name'])) {
                $attachments->storeUpload($upload, $messageId, null, $userId);
            }
        }

        // Tell the team. The configured from/contact address is the team's mailbox.
        $team = (string) (Factory::getApplication()->getParams('com_vel')->get('contact_email', '') ?: Factory::getApplication()->getParams('com_vel')->get('from_address', ''));

        if ($team !== '') {
            $messages->sendTemplate('com_vel.message', $team, 'VEL team', [
                'REFERENCE' => $this->getReference(),
                'SUBJECT'   => Text::sprintf('COM_VEL_MSG_REPLY_FROM', (string) ($participant->name ?: $participant->email)),
                'MESSAGE'   => $body,
                'CASELINK'  => Text::_('COM_VEL_MAIL_CASELINK_BACKEND'),
            ], $this->getReference());
        }

        return $messageId;
    }

    /**
     * @param int $attachmentId The attachment.
     *
     * @return object|null  The attachment, if it belongs to a message this participant may read.
     *
     * @since 4.0.0
     */
    public function getAttachment(int $attachmentId): ?object
    {
        $participant = $this->getParticipant();

        if ($participant === null) {
            return null;
        }

        $attachment = (new AttachmentService($this->getDatabase()))->find($attachmentId);

        if ($attachment === null) {
            return null;
        }

        // A report's own attachments belong to its reporter.
        if ((int) ($attachment->report_id ?? 0) > 0 && $this->getRole() === ParticipantRole::REPORTER
            && (int) $attachment->report_id === (int) ($participant->report_id ?? 0)) {
            return $attachment;
        }

        foreach ($this->getThread() as $message) {
            if ((int) $message->id === (int) ($attachment->message_id ?? 0)) {
                return $attachment;
            }
        }

        return null;
    }
}
