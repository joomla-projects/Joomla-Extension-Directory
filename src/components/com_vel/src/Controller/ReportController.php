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
use Jed\Component\Vel\Administrator\Service\ReportService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * The public report form's controller.
 *
 * @since 4.0.0
 */
class ReportController extends FormController
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $view_item = 'report';

    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $view_list = 'advisories';

    /**
     * @var string  The model to use; FixnoticeController overrides it.
     *
     * @since 4.0.0
     */
    protected string $modelName = 'Report';

    /**
     * File a report.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function submit(): void
    {
        $this->checkToken();

        /** @var \Jed\Component\Vel\Site\Model\ReportModel $model */
        $model = $this->getModel($this->modelName);
        $form  = $model->getForm([], false);
        $data  = (array) $this->input->post->get('jform', [], 'array');

        if ($form === false) {
            $this->bounce($data, Text::_('JERROR_AN_ERROR_HAS_OCCURRED'));

            return;
        }

        $filtered = $form->filter($data);

        if ($form->validate($filtered) === false) {
            $messages = [];

            foreach ($form->getErrors() as $error) {
                $messages[] = $error instanceof Exception ? $error->getMessage() : (string) $error;
            }

            $this->bounce($data, implode(' ', $messages) ?: Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'));

            return;
        }

        // Uploads arrive as one array per file under jform[attachments][].
        $files   = (array) $this->input->files->get('jform', [], 'array');
        $uploads = [];

        foreach ((array) ($files['attachments'] ?? []) as $upload) {
            if (\is_array($upload) && !empty($upload['name'])) {
                $uploads[] = $upload;
            }
        }

        try {
            $report = $model->submit((array) $filtered, $uploads);
        } catch (Throwable $e) {
            $this->bounce($data, $e->getMessage());

            return;
        }

        $this->app->setUserState('com_vel.' . strtolower($this->modelName) . '.data', null);

        $message = $report->verification_sent
            ? Text::sprintf('COM_VEL_MSG_REPORT_VERIFY_SENT', $report->reference)
            : Text::sprintf('COM_VEL_MSG_REPORT_RECEIVED', $report->reference);

        $this->setRedirect(Route::_('index.php?option=com_vel&view=advisories', false), $message);
    }

    /**
     * Confirm a guest's address from the link in the mail.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function verify(): void
    {
        $token   = (string) $this->input->getString('token', '');
        $service = new ReportService(Factory::getContainer()->get(DatabaseInterface::class));
        $report  = $service->verify($token);

        if ($report === null) {
            $this->setRedirect(Route::_('index.php?option=com_vel&view=advisories', false), Text::_('COM_VEL_ERROR_VERIFY_TOKEN'), 'warning');

            return;
        }

        $this->setRedirect(
            Route::_('index.php?option=com_vel&view=advisories', false),
            Text::sprintf('COM_VEL_MSG_REPORT_VERIFIED', \sprintf('VELR-%05d', (int) $report->id))
        );
    }

    /**
     * @param array  $data    The submitted data.
     * @param string $message The refusal.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function bounce(array $data, string $message): void
    {
        unset($data['captcha']);

        $this->app->setUserState('com_vel.' . strtolower($this->modelName) . '.data', $data);

        $this->setRedirect(Route::_('index.php?option=com_vel&view=' . strtolower($this->modelName), false), $message, 'error');
    }
}
