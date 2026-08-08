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

use Jed\Component\Jed\Administrator\Transfer\TransferService;
use Jed\Component\Jed\Administrator\Transfer\TransferState;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * Ownership transfer, from the developer's side.
 *
 * @since 4.0.0
 */
class TransferController extends BaseController
{
    /**
     * Ask for a listing to be handed to somebody else.
     *
     * Owner only. The 8.8 matrix puts transfer next to soft delete in the owner-only column, and
     * a maintainer being able to give away a listing they do not own would make the distinction
     * between the two roles meaningless.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function request(): void
    {
        $this->checkToken();

        $app         = Factory::getApplication();
        $extensionId = $app->getInput()->getInt('extension_id', 0);
        $email       = (string) $app->getInput()->getString('recipient_email', '');
        $userId      = (int) $app->getIdentity()->id;

        if (!JedHelper::isLoggedIn() || !JedHelper::isOwner($extensionId)) {
            $app->enqueueMessage(Text::_('COM_JED_TRANSFER_ERROR_NOT_OWNER'), 'error');
            $this->setRedirect($this->dashboard());

            return;
        }

        try {
            $service   = new TransferService(Factory::getContainer()->get(DatabaseInterface::class));
            $recipient = $service->findRecipient($email, $userId, $extensionId);

            $service->initiate($extensionId, $recipient, $userId);

            // Named, never addressed: telling the owner "sent to Jane Doe" confirms the account
            // they already looked up, while echoing the address back would not (8.8.1).
            $app->enqueueMessage(Text::sprintf('COM_JED_TRANSFER_REQUESTED', $recipient->name));
        } catch (Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($this->dashboard());
    }

    /**
     * Confirm one side of a transfer, from the link in the mail.
     *
     * The token identifies the transfer and the side; being logged in as the right person is
     * what authorises it. A logged-out visitor is sent to log in and comes back here, so the
     * link keeps working - it just never works for the wrong person.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function confirm(): void
    {
        $app        = Factory::getApplication();
        $transferId = $app->getInput()->getInt('id', 0);
        $token      = (string) $app->getInput()->getAlnum('token', '');

        if (!JedHelper::isLoggedIn()) {
            $app->enqueueMessage(Text::_('COM_JED_TRANSFER_LOGIN_REQUIRED'), 'warning');
            $this->setRedirect(JedHelper::getLoginlink());

            return;
        }

        try {
            $service = new TransferService(Factory::getContainer()->get(DatabaseInterface::class));
            $state   = $service->confirm($transferId, $token, (int) $app->getIdentity()->id);

            $app->enqueueMessage(
                Text::_($state === TransferState::COMPLETED
                    ? 'COM_JED_TRANSFER_COMPLETED'
                    : 'COM_JED_TRANSFER_CONFIRMED_WAITING')
            );
        } catch (Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($this->dashboard());
    }

    /**
     * Call off a transfer. Either party may, until it completes.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function cancel(): void
    {
        $this->checkToken('get');

        $app        = Factory::getApplication();
        $transferId = $app->getInput()->getInt('id', 0);
        $userId     = (int) $app->getIdentity()->id;

        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $service  = new TransferService($db);

        $transfer = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['from_user_id', 'to_user_id']))
                ->from($db->quoteName('#__jed_extension_transfers'))
                ->where($db->quoteName('id') . ' = ' . $transferId)
        )->loadAssoc();

        $isParty = $transfer !== null
            && \in_array($userId, [(int) $transfer['from_user_id'], (int) $transfer['to_user_id']], true);

        if (!JedHelper::isLoggedIn() || !$isParty) {
            $app->enqueueMessage(Text::_('COM_JED_TRANSFER_ERROR_NOT_PARTY'), 'error');
            $this->setRedirect($this->dashboard());

            return;
        }

        $service->cancel($transferId, $userId, Text::_('COM_JED_TRANSFER_CANCELLED_BY_PARTY'));
        $app->enqueueMessage(Text::_('COM_JED_TRANSFER_CANCELLED'));
        $this->setRedirect($this->dashboard());
    }

    /**
     * @return string
     *
     * @since 4.0.0
     */
    private function dashboard(): string
    {
        return Route::_('index.php?option=com_jed&view=dashboard', false);
    }
}
