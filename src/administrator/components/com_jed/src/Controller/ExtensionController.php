<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Model\ExtensionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Extension controller class.
 *
 * @since 4.0.0
 */
class ExtensionController extends FormController
{
    protected $view_list = 'extensions';

    /**
     * Method to check out an item for editing, mirroring the site side's
     * ExtensionformController::edit(). ExtensionModel::save() needs the true #__jed_extensions id
     * to attach the new history entry to, but ExtensionModel::getTable() intentionally returns
     * ExtensionHistoryTable (not ExtensionTable), so the framework's generic
     * AdminModel::populateState()/getState() bookkeeping isn't a reliable source for it. Stash it
     * explicitly in the session instead, the same way the site form already does.
     *
     * @param string|null $key    The primary key of the item
     * @param string|null $urlVar The name of the "id" URL variable
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function edit($key = null, $urlVar = null)
    {
        $result = parent::edit($key, $urlVar);

        $editId = $this->input->getInt('id', 0);
        Factory::getApplication()->setUserState('com_jed.edit.extension.id', $editId);

        return $result;
    }

    /**
     * Method to add a new record, resetting the tracked edit id so a stale value from a previous
     * edit isn't mistaken for the extension being created (see edit() above).
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function add()
    {
        Factory::getApplication()->setUserState('com_jed.edit.extension.id', 0);

        return parent::add();
    }

    /**
     * Activate a specific history version for an extension.
     *
     * Sets the given history entry to active = 1 and all others for the same
     * extension to active = 0.  Called via AJAX from the history modal.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function activateVersion(): bool
    {
        $this->checkToken();

        $extensionId = (int) $this->input->getInt('extension_id');
        $historyId   = (int) $this->input->getInt('id');

        /** @var ExtensionModel $model */
        $model = $this->getModel();
        $model->activateVersion($extensionId, $historyId);

        // The modal quick-view flow (tmpl/extensions/default.php) fetches this via
        // AJAX and ignores the redirect target; the full-page history view (with
        // its own toolbar) benefits from landing back on itself.
        $this->setRedirect(Route::_('index.php?option=com_jed&view=extension&layout=historylist&id=' . $extensionId, false));

        return true;
    }

    /**
     * Redirect to the "compare" layout for one or two selected history entries.
     * Called from the history page's toolbar Compare button.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function compareHistory(): bool
    {
        $this->checkToken();

        $extensionId = $this->input->getInt('extension_id');
        $historyIds  = $this->input->post->get('history', [], 'array');
        $historyIds  = array_values(array_unique(array_filter(array_map('intval', $historyIds))));

        if (empty($historyIds)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JED_EXTENSION_COMPARE_SELECT_AT_LEAST_ONE'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extension&layout=historylist&id=' . $extensionId, false));

            return false;
        }

        $url = 'index.php?option=com_jed&view=extension&layout=compare&id=' . $extensionId;
        $url .= count($historyIds) === 1
            ? '&right=' . $historyIds[0]
            : '&left=' . $historyIds[0] . '&right=' . $historyIds[1];

        $this->setRedirect(Route::_($url, false));

        return true;
    }

    /**
     * Approve a pending history entry: overwrites the live #__jed_extensions row
     * with that entry's content. Called from the "compare" layout's toolbar.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function approve(): bool
    {
        $this->checkToken();

        $extensionId = $this->input->getInt('extension_id');
        $historyId   = $this->input->getInt('history_id');

        /** @var ExtensionModel $model */
        $model = $this->getModel();

        try {
            $model->approve($extensionId, $historyId);
            Factory::getApplication()->enqueueMessage(Text::_('COM_JED_EXTENSION_APPROVED_MESSAGE'));
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

        return true;
    }
}
