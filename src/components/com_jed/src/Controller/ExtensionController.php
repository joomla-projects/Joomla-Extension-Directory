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
use Jed\Component\Jed\Site\Model\ExtensionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Controller for single-extension AJAX actions.
 *
 * @since 4.1.0
 */
class ExtensionController extends BaseController
{
    /**
     * AJAX task: toggles the current user's bookmark on an extension (adds it if not already
     * bookmarked, removes it otherwise) and returns the new state as JSON.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function addFavorite(): void
    {
        $this->checkToken();

        if (!JedHelper::isLoggedIn()) {
            $this->sendJson(['success' => false, 'message' => Text::_('COM_JED_EXTENSION_NO_ACCESS_LABEL')]);

            return;
        }

        $app         = Factory::getApplication();
        $extensionId = $app->getInput()->getInt('extension_id', 0);
        $userId      = (int) $app->getIdentity()->id;

        if (!$extensionId) {
            $this->sendJson(['success' => false, 'message' => Text::_('JGLOBAL_ERROR_SAVE_FAILED')]);

            return;
        }

        try {
            /** @var ExtensionModel $model */
            $model     = $this->getModel('Extension', 'Site');
            $favorited = $model->toggleFavorite($extensionId, $userId);

            $this->sendJson(['success' => true, 'data' => ['favorited' => $favorited]]);
        } catch (Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Sends a result array as a JSON response and terminates the request - same shape/idiom as
     * NewextensionController::sendJson().
     *
     * @param array $result
     *
     * @return void
     *
     * @since 4.1.0
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
