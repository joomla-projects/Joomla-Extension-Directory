<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Model\NewextensionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Controller for the "create a new extension" wizard.
 *
 * Layout "default" lets the user pick a source (upload/git/manual); the two AJAX tasks below
 * (uploadFile/readGit) detect the extension's data from that source and stash it in the session.
 * Layout "form" then renders forms/extensionform.xml pre-filled from that session data, and
 * save() creates the new #__jed_extensions (+ history) rows via NewextensionModel::save().
 *
 * @since 1.0.0
 */
class NewextensionController extends FormController
{
    /**
     * Method to abort current operation and clear any detected manifest data.
     *
     * @param null $key
     *
     * @return void
     *
     * @since 1.0.0
     * @throws Exception
     */
    public function cancel($key = null): void
    {
        Factory::getApplication()->setUserState(NewextensionModel::SESSION_KEY, null);

        $this->setRedirect(Route::_('index.php?option=com_jed&view=newextension', false));
    }

    /**
     * Clears any previously detected manifest data and sends the user straight to the blank form
     * (the "manuell" choice on the default layout).
     *
     * @return void
     *
     * @since 1.0.0
     * @throws Exception
     */
    public function manual(): void
    {
        Factory::getApplication()->setUserState(NewextensionModel::SESSION_KEY, null);

        $this->setRedirect(Route::_('index.php?option=com_jed&view=newextension&layout=form', false));
    }

    /**
     * Method to save the form data.
     *
     * Redirects to the (edit-only) extensionform view for the newly created extension so the
     * user can immediately continue editing it (upload images, add maintainers, ...).
     *
     * @param null $key
     * @param null $urlVar
     *
     * @return void
     *
     * @since  1.0.0
     * @throws Exception
     */
    public function save($key = null, $urlVar = null): void
    {
        $this->checkToken();

        /** @var NewextensionModel $model */
        $model = $this->getModel('Newextension');
        $data  = $this->input->post->get('jform', [], 'array');

        $form = $model->getForm($data, false);

        if (!$form) {
            throw new Exception(Text::_('JERROR_LOADFILE_FAILED'), 500);
        }

        $validatedData = $model->validate($form, $data);

        if ($validatedData === false) {
            $this->setMessage(Text::_('JGLOBAL_ERROR_SAVE_FAILED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=newextension&layout=form', false));

            return;
        }

        try {
            $extensionId = $model->save($validatedData);
        } catch (Exception $e) {
            $this->setMessage($e->getMessage() ?: Text::_('JGLOBAL_ERROR_SAVE_FAILED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=newextension&layout=form', false));

            return;
        }

        Factory::getApplication()->setUserState(NewextensionModel::SESSION_KEY, null);

        $this->setMessage(Text::_('COM_JED_ITEM_SAVED_SUCCESSFULLY'));
        $this->setRedirect(
            Route::_('index.php?option=com_jed&view=extensionform&layout=edit&id=' . (int) $extensionId, false)
        );
    }

    /**
     * AJAX task: receives an uploaded extension zip, extracts it, reads its manifest, stores the
     * detected data in the session and returns it as JSON.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function uploadFile(): void
    {
        $this->checkToken();

        if (!JedHelper::isLoggedIn()) {
            $this->sendJson(['success' => false, 'message' => Text::_('COM_JED_EXTENSION_NO_ACCESS_LABEL')]);

            return;
        }

        /** @var NewextensionModel $model */
        $model = $this->getModel('Newextension');

        $this->sendJson($model->parseUploadedFile($_FILES['extensionfile'] ?? []));
    }

    /**
     * AJAX task: reads the latest GitHub release of the given repository URL, reads its manifest,
     * stores the detected data in the session and returns it as JSON.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function readGit(): void
    {
        $this->checkToken();

        if (!JedHelper::isLoggedIn()) {
            $this->sendJson(['success' => false, 'message' => Text::_('COM_JED_EXTENSION_NO_ACCESS_LABEL')]);

            return;
        }

        $url = (string) Factory::getApplication()->getInput()->getString('git_url', '');

        /** @var NewextensionModel $model */
        $model = $this->getModel('Newextension');

        $this->sendJson($model->parseGithubUrl($url));
    }

    /**
     * Sends a result array as a JSON response and terminates the request, matching the shape the
     * front-end JS (media/com_jed/js/newextension.js) expects:
     * {"success": true, "data": {...}} or {"success": false, "message": "..."}.
     *
     * @param array $result
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function sendJson(array $result): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->sendHeaders();

        echo json_encode($result);

        $app->close();
    }
}
