<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.jedidentity
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Jedidentity\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Identity\Guid;
use Jed\Component\Jed\Administrator\Identity\IdentityLinks;
use Jed\Component\Jed\Administrator\Identity\PingVerifier;
use Jed\Component\Jed\Administrator\Identity\PrivilegedAccounts;
use Joomla\Application\ApplicationEvents;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Event\User\AfterDeleteEvent;
use Joomla\CMS\Event\User\AfterLoginEvent;
use Joomla\CMS\Event\User\BeforeSaveEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Privacy\Administrator\Table\RequestTable;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\System\Jedidentity\Console\MigrateCommand;
use Throwable;

/**
 * What the JED needs around the Joomla Identity Portal that the single sign-on extension does not do.
 *
 * Signing in itself is done by RO Single Sign On, which creates the account with the portal GUID as
 * username and logs it in through Joomla's normal login. This plugin adds three things:
 *
 *  - on every login it records the tie between the account and its GUID;
 *  - it answers the portal's ping at `index.php?option=com_ajax&group=system&plugin=jedidentity&format=json`,
 *    which today carries one real task, `remove`, a data removal the person asked for at the portal;
 *  - it adds the `jed:identity:migrate` command that moves old accounts onto GUIDs.
 *
 * The decisions are in `com_jed`'s Identity namespace, where they can be tested; this class connects
 * them to Joomla.
 *
 * @since 4.0.0
 */
final class Jedidentity extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApplicationEvents::BEFORE_EXECUTE => 'registerCommands',
            'onUserAfterLogin'                => 'onUserAfterLogin',
            'onUserAfterDelete'               => 'onUserAfterDelete',
            'onUserBeforeSave'                => 'onUserBeforeSave',
            'onAjaxJedidentity'               => 'onAjaxJedidentity',
        ];
    }

    /**
     * Add the migration command when running from the command line.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function registerCommands(): void
    {
        $app = $this->getApplication();

        if ($app instanceof ConsoleApplication) {
            $app->addCommand(new MigrateCommand($this->getDatabase()));
        }
    }

    /**
     * Record which portal identity the account that just signed in belongs to.
     *
     * Only a login that came through the single sign-on counts. A password login, or a cookie that
     * restores one, proves nothing about a GUID-shaped username, so it never creates a tie.
     * Repeating a tie that already exists is harmless.
     *
     * @param AfterLoginEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onUserAfterLogin(AfterLoginEvent $event): void
    {
        $options = $event->getOptions();
        $user    = $options['user'] ?? null;

        if (!$user instanceof User || !$user->id || !Guid::isValid((string) $user->username)) {
            return;
        }

        if (\in_array(strtolower((string) ($options['responseType'] ?? 'joomla')), ['joomla', 'cookie', ''], true)) {
            return;
        }

        if (!(new IdentityLinks($this->getDatabase()))->link((int) $user->id, (string) $user->username, IdentityLinks::SOURCE_LOGIN)) {
            $this->log(sprintf('account %d could not be tied to its portal identity: one of them is already linked', (int) $user->id));
        }
    }

    /**
     * Keep the ties tidy when an account is deleted by any route.
     *
     * @param AfterDeleteEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onUserAfterDelete(AfterDeleteEvent $event): void
    {
        $userId = (int) ($event->getUser()['id'] ?? 0);

        if ($userId <= 0) {
            return;
        }

        $db = $this->getDatabase();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__jed_identity_links'))
                ->where($db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__jed_identity_claims'))
                ->where('(' . $db->quoteName('user_id') . ' = :uid OR ' . $db->quoteName('old_user_id') . ' = :old)')
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':old', $userId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Refuse a GUID-shaped username chosen on the public registration and profile forms.
     *
     * The portal GUID is the key to an account. If anybody could register with somebody else's GUID
     * as a username, that person's later portal sign-in would land in an account they do not
     * control. Accounts created by the single sign-on, and by an administrator, are not affected.
     *
     * @param BeforeSaveEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onUserBeforeSave(BeforeSaveEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $input = $app->getInput();

        // The dispatcher has already split "registration.register" into controller and task.
        if ($input->getCmd('option') !== 'com_users' || !\in_array(strtolower($input->getCmd('controller')), ['registration', 'profile'], true)) {
            return;
        }

        $username = (string) ($event->getData()['username'] ?? '');

        if (Guid::isValid($username) && strtolower(trim($username)) !== strtolower((string) ($event->getUser()['username'] ?? ''))) {
            $this->loadLanguage();
            $app->enqueueMessage(Text::_('PLG_SYSTEM_JEDIDENTITY_USERNAME_RESERVED'), 'error');
            $event->addResult(false);
        }
    }

    /**
     * Answer a ping from the Identity Portal.
     *
     * @param AjaxEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onAjaxJedidentity(AjaxEvent $event): void
    {
        $app    = $this->getApplication();
        $server = $app->getInput()->server;
        $body   = (string) file_get_contents('php://input');

        if ($server->getString('REQUEST_METHOD', '') !== 'POST') {
            $event->addResult($this->reply(405, 'POST only'));

            return;
        }

        $why = PingVerifier::check(
            trim((string) $this->params->get('ping_secret', '')),
            $server->getString('HTTP_X_JED_TIMESTAMP', ''),
            $server->getString('HTTP_X_JED_SIGNATURE', ''),
            $body,
            time()
        );

        if ($why !== null) {
            $this->log('ping refused: ' . $why);
            $event->addResult($this->reply(403, 'refused'));

            return;
        }

        $ping = PingVerifier::parse($body);

        if ($ping === null) {
            $event->addResult($this->reply(400, 'not a ping this site understands'));

            return;
        }

        switch ($ping['task']) {
            case 'ping':
                $event->addResult($this->reply(200, 'ok'));

                return;

            case 'remove':
                $event->addResult($ping['guid'] === '' ? $this->reply(400, 'a GUID is required') : $this->remove($ping['guid']));

                return;
        }

        $this->log('ping with an unknown task: ' . $ping['task']);
        $event->addResult($this->reply(422, 'unknown task'));
    }

    /**
     * Run a data removal for the person behind a GUID, through Joomla's own privacy machinery.
     *
     * The person already asked at the portal, so there is no second request to approve. A request
     * record is still written and closed, so the privacy screen shows that it happened, and every
     * privacy plugin runs: the JED's own, the tickets one and Joomla's, which anonymises the account.
     *
     * @param string $guid The portal GUID.
     *
     * @return array<string, mixed>
     *
     * @since 4.0.0
     */
    private function remove(string $guid): array
    {
        $app    = $this->getApplication();
        $db     = $this->getDatabase();
        $userId = (new IdentityLinks($db))->userIdForGuid($guid);

        // Nothing to remove is the answer the portal wants too: the same removal can be sent twice.
        if (!$userId) {
            return $this->reply(200, 'unknown', ['removed' => false]);
        }

        // Staff accounts are handled by a person, not by a message.
        if ((new PrivilegedAccounts($db))->isPrivileged($userId)) {
            $this->log(sprintf('removal for account %d refused: staff account', $userId));

            return $this->reply(409, 'the removal needs a person', ['removed' => false]);
        }

        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

        // com_privacy finds the account again by email address. That has to lead to this account and
        // to no other, or the wrong person is erased and this one is reported as done.
        if ((int) $user->id !== $userId || !$this->emailBelongsOnlyTo($userId, (string) $user->email)) {
            $this->log(sprintf('removal for account %d refused: its email address does not identify it', $userId));

            return $this->reply(409, 'the account cannot be identified', ['removed' => false]);
        }

        $app->getLanguage()->load('com_privacy', JPATH_ADMINISTRATOR);
        $factory = $app->bootComponent('com_privacy')->getMVCFactory();

        /** @var RequestTable $table */
        $table = $factory->createTable('Request', 'Administrator');
        $table->bind([
            'email'         => $user->email,
            'requested_at'  => gmdate('Y-m-d H:i:s'),
            'status'        => 1,
            'request_type'  => 'remove',
            'confirm_token' => '',
        ]);

        if (!$table->store()) {
            return $this->reply(500, 'could not record the request');
        }

        $model = $factory->createModel('Remove', 'Administrator', ['ignore_request' => true]);
        $model->setUserFactory(Factory::getContainer()->get(UserFactoryInterface::class));

        if (!$model->removeDataForRequest((int) $table->id)) {
            $table->status = -1;
            $table->store();
            $this->log(sprintf('removal for account %d refused: %s', $userId, implode('; ', $model->getErrors())));

            return $this->reply(409, 'the removal was refused', ['removed' => false]);
        }

        $table->status = 2;
        $table->store();
        $this->log(sprintf('removal for account %d done, request %d', $userId, (int) $table->id));

        return $this->reply(200, 'removed', ['removed' => true]);
    }

    /**
     * Whether exactly one account has this email address, case ignored, and it is the given one.
     *
     * @param int    $userId The account.
     * @param string $email  Its email address.
     *
     * @return boolean
     *
     * @since 4.0.0
     */
    private function emailBelongsOnlyTo(int $userId, string $email): bool
    {
        if (trim($email) === '') {
            return false;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where('LOWER(' . $db->quoteName('email') . ') = LOWER(:email)')
            ->bind(':email', $email)
            ->setLimit(2);

        $ids = array_map('intval', $db->setQuery($query)->loadColumn() ?: []);

        return $ids === [$userId];
    }

    /**
     * Build a reply and set the HTTP status that goes with it.
     *
     * @param int                  $status  The HTTP status.
     * @param string               $message A short word for the portal's log.
     * @param array<string, mixed> $extra   More fields.
     *
     * @return array<string, mixed>
     *
     * @since 4.0.0
     */
    private function reply(int $status, string $message, array $extra = []): array
    {
        $this->getApplication()->setHeader('status', (string) $status, true);

        return ['status' => $status, 'message' => $message] + $extra;
    }

    /**
     * Write to the com_jed log. A logger that is not configured must not break the request.
     *
     * @param string $message The message.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function log(string $message): void
    {
        try {
            Log::add('plg_system_jedidentity: ' . $message, Log::INFO, 'com_jed');
        } catch (Throwable $e) {
            // Nothing to do.
        }
    }
}
