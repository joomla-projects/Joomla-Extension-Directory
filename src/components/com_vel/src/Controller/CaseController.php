<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Service\AttachmentService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * The participant's actions on the case page.
 *
 * @since 4.0.0
 */
class CaseController extends BaseController
{
    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function reply(): void
    {
        $this->checkToken();

        /** @var \Jed\Component\Vel\Site\Model\CaseModel $model */
        $model  = $this->getModel('Case');
        $token  = $model->getToken();
        $return = Route::_('index.php?option=com_vel&view=case&token=' . $token, false);

        if ($model->getParticipant() === null) {
            $this->setRedirect(Route::_('index.php?option=com_vel&view=advisories', false), Text::_('COM_VEL_ERROR_CASE_TOKEN'), 'error');

            return;
        }

        $data    = (array) $this->input->post->get('jform', [], 'array');
        $files   = (array) $this->input->files->get('jform', [], 'array');
        $uploads = [];

        foreach ((array) ($files['attachments'] ?? []) as $upload) {
            if (\is_array($upload) && !empty($upload['name'])) {
                $uploads[] = $upload;
            }
        }

        try {
            $model->reply((string) ($data['body'] ?? ''), $uploads);
        } catch (Throwable $e) {
            $this->app->setUserState('com_vel.case.data', $data);
            $this->setRedirect($return, $e->getMessage(), 'error');

            return;
        }

        $this->app->setUserState('com_vel.case.data', null);
        $this->setRedirect($return, Text::_('COM_VEL_MSG_CASE_REPLY_SENT'));
    }

    /**
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function attachment(): void
    {
        /** @var \Jed\Component\Vel\Site\Model\CaseModel $model */
        $model      = $this->getModel('Case');
        $attachment = $model->getAttachment((int) $this->input->getInt('id', 0));

        if ($attachment === null) {
            throw new Exception(Text::_('COM_VEL_ERROR_ATTACHMENT_MISSING'), 404);
        }

        (new AttachmentService(Factory::getContainer()->get(DatabaseInterface::class)))->send($attachment);
    }
}
