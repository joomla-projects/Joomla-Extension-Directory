<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\MessageVisibility;
use Jed\Component\Vel\Administrator\Enum\ParticipantRole;
use Jed\Component\Vel\Administrator\Service\AttachmentService;
use Jed\Component\Vel\Administrator\Service\MessageService;
use Jed\Component\Vel\Administrator\Service\ReportService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * The team's side of the conversation: reply, notify the vendor, fetch an attachment.
 *
 * @since 4.0.0
 */
class MessageController extends BaseController
{
    /**
     * Post a reply into a case and mail it to the participants it is visible to.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function reply(): void
    {
        $this->checkToken();

        $advisoryId = (int) $this->input->getInt('advisory_id', 0);
        $reportId   = (int) $this->input->getInt('report_id', 0);
        $return     = $this->returnUrl($advisoryId, $reportId);

        if (!$this->app->getIdentity()->authorise('vel.communicate', 'com_vel')) {
            $this->setRedirect($return, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $subject    = trim((string) $this->input->getString('subject', ''));
        $body       = trim((string) $this->input->getString('body', ''));
        $visibility = MessageVisibility::tryFrom((string) $this->input->getCmd('visibility', 'team')) ?? MessageVisibility::TEAM;

        if ($body === '') {
            $this->setRedirect($return, Text::_('COM_VEL_ERROR_MESSAGE_EMPTY'), 'error');

            return;
        }

        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $messages = new MessageService($db);
        $actor    = (int) $this->app->getIdentity()->id;
        $isNote   = $visibility === MessageVisibility::TEAM;

        if ($subject === '') {
            $subject = Text::sprintf('COM_VEL_MSG_DEFAULT_SUBJECT', MessageService::reference($advisoryId ?: null, $reportId ?: null));
        }

        try {
            $messageId = $messages->record($advisoryId ?: null, $reportId ?: null, null, $isNote ? 'internal' : 'out', 'web', $visibility, $subject, $body, $actor);

            $files = (array) $this->input->files->get('attachments', [], 'array');

            foreach ($files as $upload) {
                if (\is_array($upload) && !empty($upload['name'])) {
                    (new AttachmentService($db))->storeUpload($upload, $messageId, null, $actor);
                }
            }

            $sent = 0;

            if (!$isNote) {
                foreach ($messages->participants($advisoryId ?: null, $reportId ?: null) as $participant) {
                    $role = ParticipantRole::tryFrom((string) $participant->role) ?? ParticipantRole::OTHER;

                    if ($role === ParticipantRole::TEAM || !$visibility->readableBy($role)) {
                        continue;
                    }

                    if ($messages->deliver($messageId, $participant, null)) {
                        $sent++;
                    }
                }
            }
        } catch (Throwable $e) {
            $this->setRedirect($return, $e->getMessage(), 'error');

            return;
        }

        $this->setRedirect($return, $isNote ? Text::_('COM_VEL_MSG_NOTE_SAVED') : Text::plural('COM_VEL_MSG_REPLY_SENT', $sent));
    }

    /**
     * Write to the vendor and start the clock (VEL2-PRD §6.4.4).
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function notifyvendor(): void
    {
        $this->checkToken();

        $advisoryId = (int) $this->input->getInt('advisory_id', 0);
        $return     = $this->returnUrl($advisoryId, 0);

        if (!$this->app->getIdentity()->authorise('vel.communicate', 'com_vel')) {
            $this->setRedirect($return, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        try {
            $sent = (new ReportService(Factory::getContainer()->get(DatabaseInterface::class)))->notifyVendor(
                $advisoryId,
                (int) $this->app->getIdentity()->id,
                (string) $this->input->getString('vendor_email', ''),
                (string) $this->input->getString('vendor_name', ''),
                (string) $this->input->getString('vendor_note', '')
            );
        } catch (Throwable $e) {
            $this->setRedirect($return, $e->getMessage(), 'error');

            return;
        }

        $this->setRedirect($return, Text::plural('COM_VEL_MSG_VENDOR_NOTIFIED', $sent), $sent > 0 ? 'message' : 'warning');
    }

    /**
     * Download an attachment.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function attachment(): void
    {
        $this->checkToken('get');

        if (!$this->app->getIdentity()->authorise('vel.communicate', 'com_vel')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $service    = new AttachmentService(Factory::getContainer()->get(DatabaseInterface::class));
        $attachment = $service->find((int) $this->input->getInt('id', 0));

        if ($attachment === null) {
            throw new \Exception(Text::_('COM_VEL_ERROR_ATTACHMENT_MISSING'), 404);
        }

        $service->send($attachment);
    }

    /**
     * @param int $advisoryId The advisory.
     * @param int $reportId   The report.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function returnUrl(int $advisoryId, int $reportId): string
    {
        if ($advisoryId > 0) {
            return Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $advisoryId, false);
        }

        if ($reportId > 0) {
            return Route::_('index.php?option=com_vel&task=report.edit&id=' . $reportId, false);
        }

        return Route::_('index.php?option=com_vel&view=reports', false);
    }
}
