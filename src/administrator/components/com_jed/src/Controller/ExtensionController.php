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
use Jed\Component\Jed\Administrator\Queue\QueueService;
use Jed\Component\Jed\Administrator\Transfer\TransferService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

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

        $app         = Factory::getApplication();
        $extensionId = $this->input->getInt('extension_id');
        $historyId   = $this->input->getInt('history_id');

        // Moderation is its own right (P1-03), not a by-product of core.edit - and this had no
        // permission check at all, so any authenticated backend user could promote a revision.
        if (!$app->getIdentity()->authorise('jed.approve', 'com_jed')) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

            return false;
        }

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

    /**
     * Reject a pending revision, with a reason from the shared vocabulary.
     *
     * The counterpart to approve(), offered from the same places. The reason is mandatory: a
     * rejection the developer cannot act on is not a moderation decision, it is a dead end.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function reject(): bool
    {
        $this->checkToken();

        $app         = Factory::getApplication();
        $extensionId = $this->input->getInt('extension_id');
        $historyId   = $this->input->getInt('history_id');
        $form        = (array) $this->input->post->get('jform', [], 'array');

        $reasonCode = (string) ($this->input->getCmd('reject_reason_code', '') ?: ($form['approved_reason'] ?? ''));
        $notes      = (string) ($this->input->getString('reject_reason_notes', '') ?: ($form['approved_notes'] ?? ''));

        if (!$app->getIdentity()->authorise('jed.approve', 'com_jed')) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

            return false;
        }

        /** @var ExtensionModel $model */
        $model = $this->getModel();

        try {
            $model->reject($extensionId, $historyId, $reasonCode, $notes);
            $app->enqueueMessage(Text::_('COM_JED_EXTENSION_REJECTED_MESSAGE'));
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

        return true;
    }

    /**
     * Reassign a listing to another owner without the current owner's confirmation.
     *
     * The abandonware escape hatch (8.8.1). Guarded by its own permission - forcing a handover
     * is not something anyone who may edit a listing should be able to do - and the reason is
     * mandatory, because "the team moved it" without a why is not an answer a developer can
     * argue with later.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function forceTransfer(): bool
    {
        $this->checkToken();

        $app         = Factory::getApplication();
        $extensionId = $this->input->getInt('id') ?: (int) $app->getUserState('com_jed.edit.extension.id', 0);
        $email       = (string) $this->input->getString('transfer_email', '');
        $reason      = (string) $this->input->getString('transfer_reason', '');

        if (!$app->getIdentity()->authorise('jed.transfer.force', 'com_jed')) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

            return false;
        }

        try {
            $db      = Factory::getContainer()->get(DatabaseInterface::class);
            $service = new TransferService($db);
            $userId  = (int) $app->getIdentity()->id;

            // The same bounded lookup the developer side uses, so a team member probing
            // addresses is logged and rate-limited exactly as a developer would be.
            $recipient = $service->findRecipient($email, $userId, $extensionId);

            $service->force($extensionId, $recipient, $userId, $reason);
            $app->enqueueMessage(Text::_('COM_JED_TRANSFER_FORCED_MESSAGE'));
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

        return true;
    }

    /**
     * Block the listing currently open in the edit form, with a stated reason.
     *
     * The reason code arrives from the block modal and is mandatory - the model rejects an
     * empty or unknown one, so a block can never end up without wording for the public notice.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function block(): bool
    {
        $this->checkToken();

        // The reason fields live in the edit form's "blocking" fieldset, so they arrive inside
        // jform - the toolbar button submits the whole form rather than a separate dialog.
        $form = (array) $this->input->post->get('jform', [], 'array');

        return $this->runListingStateChange(
            'jed.block',
            static fn (ExtensionModel $model, int $extensionId) => $model->block(
                $extensionId,
                (string) ($form['block_reason_code'] ?? ''),
                (string) ($form['block_reason_text'] ?? '')
            ),
            'COM_JED_EXTENSION_BLOCKED_MESSAGE'
        );
    }

    /**
     * Lift the block on the listing currently open in the edit form.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function unblock(): bool
    {
        $this->checkToken();

        return $this->runListingStateChange(
            'jed.block',
            static fn (ExtensionModel $model, int $extensionId) => $model->unblock($extensionId),
            'COM_JED_EXTENSION_UNBLOCKED_MESSAGE'
        );
    }

    /**
     * Soft-delete the listing currently open in the edit form.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function softDelete(): bool
    {
        $this->checkToken();

        return $this->runListingStateChange(
            'core.delete',
            static fn (ExtensionModel $model, int $extensionId) => $model->softDelete($extensionId),
            'COM_JED_EXTENSION_SOFT_DELETED_MESSAGE'
        );
    }

    /**
     * Undo a soft delete.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function restore(): bool
    {
        $this->checkToken();

        return $this->runListingStateChange(
            'core.delete',
            static fn (ExtensionModel $model, int $extensionId) => $model->restore($extensionId),
            'COM_JED_EXTENSION_RESTORED_MESSAGE'
        );
    }

    /**
     * Shared plumbing for the four state transitions: permission, id, call, message, redirect.
     *
     * @param string   $action     The com_jed permission the transition needs.
     * @param callable $transition fn(ExtensionModel, int $extensionId): void
     * @param string   $successKey Language key for the success message.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    private function runListingStateChange(string $action, callable $transition, string $successKey): bool
    {
        $app         = Factory::getApplication();
        $extensionId = $this->input->getInt('id') ?: (int) $app->getUserState('com_jed.edit.extension.id', 0);

        if (!$app->getIdentity()->authorise($action, 'com_jed')) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

            return false;
        }

        if ($extensionId <= 0) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

            return false;
        }

        /** @var ExtensionModel $model */
        $model = $this->getModel();

        try {
            $transition($model, $extensionId);
            $app->enqueueMessage(Text::_($successKey));
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

        return true;
    }

    /**
     * Enqueue a manual, one-off `extension.score_recalc` job for the extension
     * currently open in the edit form. Recalculation is always triggered this way,
     * per extension - never as a scheduled scan of the whole dataset.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function recalculateScore(): bool
    {
        $this->checkToken();

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.edit', 'com_jed')) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

            return false;
        }

        $extensionId = (int) $app->getUserState('com_jed.edit.extension.id', 0);

        if ($extensionId > 0) {
            $queueService = new QueueService(Factory::getContainer()->get(DatabaseInterface::class));
            $queueService->enqueue('extension.score_recalc', $extensionId, null, [], (int) $app->getIdentity()->id);

            $app->enqueueMessage(Text::_('COM_JED_EXTENSION_SCORE_RECALC_QUEUED'), 'message');
        }

        $this->setRedirect(Route::_('index.php?option=com_jed&view=extension&layout=edit&id=' . $extensionId, false));

        return true;
    }
}
