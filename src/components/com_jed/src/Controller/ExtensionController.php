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
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Controller for single-extension AJAX actions.
 *
 * @since 4.1.0
 */
class ExtensionController extends BaseController
{
    /**
     * AJAX task: which of the given extensions the current visitor has bookmarked.
     *
     * The counterpart to taking the bookmark flag out of the browse query (`P1-13`). That flag
     * was the one per-visitor thing in an otherwise identical page, and it alone made every
     * browse list uncacheable; asking for it separately afterwards costs signed-in visitors one
     * small request and makes the pages cacheable for everyone else.
     *
     * Answers about the caller's own bookmarks and nobody else's - it takes no user id, and a
     * guest gets an empty list rather than an error, because a page that is not logged in simply
     * has no icons to fill in.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function favoriteState(): void
    {
        if (!JedHelper::isLoggedIn()) {
            $this->sendJson(['success' => true, 'data' => ['favorited' => []]]);

            return;
        }

        $app    = Factory::getApplication();
        $userId = (int) $app->getIdentity()->id;

        // Bounded: the caller names the ids on its page, and a page holds at most 72 cards.
        $ids = array_slice(
            array_filter(array_map('intval', (array) $app->getInput()->get('ids', [], 'array'))),
            0,
            100
        );

        if ($ids === []) {
            $this->sendJson(['success' => true, 'data' => ['favorited' => []]]);

            return;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__jed_favorites'))
            ->where($db->quoteName('user_id') . ' = :user')
            ->whereIn($db->quoteName('extension_id'), $ids)
            ->bind(':user', $userId, ParameterType::INTEGER);

        $this->sendJson([
            'success' => true,
            'data'    => ['favorited' => array_map('intval', $db->setQuery($query)->loadColumn() ?: [])],
        ]);
    }

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
     * The developer's own online/offline switch for one of their listings.
     *
     * Writes `state` and nothing else. That is the invariant this whole plan rests on (4.8,
     * CLAUDE.md): `state` belongs to the developer, `blocked` to the JED team. The switch stays
     * available while a listing is blocked - going back online does not lift the block, and
     * refusing the switch would only mean a developer could not take a blocked listing down.
     *
     * Owner or maintainer may use it; a soft-deleted listing may not be touched at all.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function setOnlineState(): void
    {
        $this->checkToken();

        $app         = Factory::getApplication();
        $extensionId = $app->getInput()->getInt('extension_id', 0);
        $online      = $app->getInput()->getInt('online', 0) === 1 ? 1 : 0;

        if (!JedHelper::isLoggedIn() || !$extensionId || !JedHelper::isOwnerOrMaintainer($extensionId)) {
            $this->sendJson(['success' => false, 'message' => Text::_('COM_JED_EXTENSION_NO_ACCESS_LABEL')]);

            return;
        }

        try {
            /** @var ExtensionModel $model */
            $model = $this->getModel('Extension', 'Site');
            $model->setOnlineState($extensionId, $online);

            $this->sendJson(['success' => true, 'data' => ['online' => $online]]);
        } catch (Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Accept or decline a maintainer invitation.
     *
     * Only the invited person can answer, and only their own invitation - the extension id alone
     * is not an authorisation, so the row is matched on the current user's id as well.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function respondToInvitation(): void
    {
        // 'get': the dashboard offers this as two plain links rather than a form, so the token
        // travels in the query string. checkToken() defaults to POST and would silently reject
        // every answer.
        $this->checkToken('get');

        $app         = Factory::getApplication();
        $extensionId = $app->getInput()->getInt('extension_id', 0);
        $accept      = $app->getInput()->getInt('accept', 0) === 1;

        $dashboard = Route::_('index.php?option=com_jed&view=dashboard', false);

        if (!JedHelper::isLoggedIn() || !$extensionId) {
            $app->enqueueMessage(Text::_('COM_JED_EXTENSION_NO_ACCESS_LABEL'), 'error');
            $this->setRedirect($dashboard);

            return;
        }

        try {
            /** @var ExtensionModel $model */
            $model    = $this->getModel('Extension', 'Site');
            $answered = $model->respondToMaintainerInvitation($extensionId, (int) $app->getIdentity()->id, $accept);

            $app->enqueueMessage(
                $answered
                    ? Text::_($accept ? 'COM_JED_MAINTAINER_INVITE_ACCEPTED' : 'COM_JED_MAINTAINER_INVITE_DECLINED')
                    : Text::_('COM_JED_MAINTAINER_INVITE_NOT_FOUND'),
                $answered ? 'message' : 'warning'
            );
        } catch (Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($dashboard);
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
