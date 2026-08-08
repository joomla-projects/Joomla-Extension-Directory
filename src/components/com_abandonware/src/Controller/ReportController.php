<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Site\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Throwable;

/**
 * The public report form's controller.
 *
 * @since 4.1.0
 */
class ReportController extends FormController
{
    /**
     * @var string
     *
     * @since 4.1.0
     */
    protected $view_item = 'report';

    /**
     * @var string
     *
     * @since 4.1.0
     */
    protected $view_list = 'abandoned';

    /**
     * File a report.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function submit(): void
    {
        $this->checkToken();

        /** @var \Jed\Component\Abandonware\Site\Model\ReportModel $model */
        $model = $this->getModel('Report');
        $form  = $model->getForm([], false);
        $data  = (array) $this->input->post->get('jform', [], 'array');

        if ($form === false) {
            $this->bounce($data, Text::_('JERROR_AN_ERROR_HAS_OCCURRED'));

            return;
        }

        // Filter and validate on the Form rather than through FormModel::validate(). The two do
        // the same work, but the model reports failures through the deprecated BaseModel::getErrors(),
        // while Form::getErrors() is the supported way to read them.
        $filtered = $form->filter($data);

        if ($form->validate($filtered) === false) {
            $messages = [];

            foreach ($form->getErrors() as $error) {
                $messages[] = $error instanceof Exception ? $error->getMessage() : (string) $error;
            }

            $this->bounce($data, implode(' ', $messages) ?: Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'));

            return;
        }

        try {
            $result = $model->submit((array) $filtered);
        } catch (Throwable $e) {
            // Refusals - not logged in, banned, over the rate limit, already reported, consent
            // withheld - all arrive here with a message that says which. Keeping the submitted
            // data means the reporter does not retype a paragraph because they were one report
            // over the daily limit.
            $this->bounce($data, $e->getMessage());

            return;
        }

        $this->app->setUserState('com_abandonware.report.data', null);

        // The case id is deliberately not shown. Until the JED team concludes something there is
        // nothing public to point at, and a link to a case that 404s reads as a broken site.
        $this->setRedirect(
            Route::_('index.php?option=com_abandonware&view=abandoned', false),
            Text::_('COM_ABANDONWARE_MSG_REPORT_RECEIVED')
        );
    }

    /**
     * Send the reporter back to the form with what they typed and why it was refused.
     *
     * @param array  $data    The submitted data.
     * @param string $message The refusal.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function bounce(array $data, string $message): void
    {
        $this->app->setUserState('com_abandonware.report.data', $data);

        $this->setRedirect(
            Route::_('index.php?option=com_abandonware&view=report', false),
            $message,
            'error'
        );
    }
}
