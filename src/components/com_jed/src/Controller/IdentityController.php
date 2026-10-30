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

use Jed\Component\Jed\Administrator\Identity\ClaimRules;
use Jed\Component\Jed\Administrator\Identity\ClaimService;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

/**
 * Taking over an old JED account after signing in through the Identity Portal.
 *
 * @since 4.0.0
 */
class IdentityController extends BaseController
{
    /**
     * Ask for a link to be mailed to the address on the old account.
     *
     * The answer is the same whether or not an account matched, so the form cannot be used to find
     * out who has one. Only what concerns the person asking is said plainly.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function request(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();

        if (!JedHelper::isLoggedIn()) {
            $app->enqueueMessage(Text::_('COM_JED_IDENTITY_LOGIN_REQUIRED'), 'warning');
            $this->setRedirect(JedHelper::getLoginlink());

            return;
        }

        $result = (new ClaimService(Factory::getContainer()->get(DatabaseInterface::class)))
            ->request((int) $app->getIdentity()->id, (string) $app->getInput()->getString('identifier', ''));

        $specific = [
            ClaimService::THROTTLED   => ['COM_JED_IDENTITY_THROTTLED', 'warning'],
            ClaimService::NOT_PORTAL  => ['COM_JED_IDENTITY_NOT_PORTAL', 'warning'],
            ClaimService::HAS_CONTENT => ['COM_JED_IDENTITY_HAS_CONTENT', 'warning'],
        ];

        [$key, $type] = $specific[$result] ?? ['COM_JED_IDENTITY_REQUESTED', 'message'];

        $app->enqueueMessage(Text::_($key), $type);
        $this->setRedirect(Route::_('index.php?option=com_jed&view=identityclaim', false));
    }

    /**
     * Complete the claim, from the link in the mail.
     *
     * Being signed in as the account that asked is what authorises it; the token alone does nothing
     * for anybody else. A visitor who is signed out is sent to sign in and the link keeps working.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function confirm(): void
    {
        $app   = Factory::getApplication();
        $token = (string) $app->getInput()->getAlnum('token', '');

        if (!JedHelper::isLoggedIn()) {
            $app->enqueueMessage(Text::_('COM_JED_IDENTITY_LOGIN_REQUIRED'), 'warning');
            $this->setRedirect(JedHelper::getLoginlink());

            return;
        }

        $userId = (int) $app->getIdentity()->id;

        try {
            (new ClaimService(Factory::getContainer()->get(DatabaseInterface::class)))->confirm($userId, $token);
        } catch (RuntimeException $e) {
            $messages = [
                ClaimRules::NO_CLAIM      => 'COM_JED_IDENTITY_ERROR_NO_CLAIM',
                ClaimRules::EXPIRED       => 'COM_JED_IDENTITY_ERROR_EXPIRED',
                ClaimRules::WRONG_TOKEN   => 'COM_JED_IDENTITY_ERROR_NO_CLAIM',
                ClaimService::HAS_CONTENT => 'COM_JED_IDENTITY_HAS_CONTENT',
            ];

            $app->enqueueMessage(Text::_($messages[$e->getMessage()] ?? 'COM_JED_IDENTITY_ERROR_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=identityclaim', false));

            return;
        }

        // The account that was signed in no longer exists. Sign out and ask for a fresh sign in,
        // which lands on the old account because it now carries the GUID.
        $app->logout($userId);
        $app->enqueueMessage(Text::_('COM_JED_IDENTITY_CLAIMED'));
        $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
    }
}
