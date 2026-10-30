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
use Joomla\Application\ApplicationEvents;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Event\User\AfterLoginEvent;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Privacy\Administrator\Table\RequestTable;
use Joomla\Database\DatabaseAwareTrait;
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
     * Only accounts whose username is a GUID are portal accounts; password users in the backend
     * are left alone. Repeating a tie that already exists is harmless.
     *
     * @param AfterLoginEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onUserAfterLogin(AfterLoginEvent $event): void
    {
        $user = $event->getOptions()['user'] ?? null;

        if (!$user instanceof User || !$user->id || !Guid::isValid((string) $user->username)) {
            return;
        }

        if (!(new IdentityLinks($this->getDatabase()))->link((int) $user->id, (string) $user->username, IdentityLinks::SOURCE_LOGIN)) {
            $this->log(sprintf('account %d could not be tied to GUID %s: one of them is already linked', (int) $user->id, $user->username));
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
        $userId = (new IdentityLinks($db))->userIdForGuid($guid) ?? $this->userIdByUsername($guid);

        // Nothing to remove is the answer the portal wants too: the same removal can be sent twice.
        if (!$userId) {
            return $this->reply(200, 'unknown', ['removed' => false]);
        }

        $user    = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
        $app->getLanguage()->load('com_privacy', JPATH_ADMINISTRATOR);
        $factory = $app->bootComponent('com_privacy')->getMVCFactory();

        /** @var RequestTable $table */
        $table = $factory->createTable('Request', 'Administrator');
        $table->bind([
            'email'        => $user->email,
            'requested_at' => gmdate('Y-m-d H:i:s'),
            'status'       => 1,
            'request_type' => 'remove',
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
     * The id of the account whose username is the given value.
     *
     * @param string $username The username.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function userIdByUsername(string $username): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('username') . ' = :username')
            ->bind(':username', $username)
            ->setLimit(1);

        return (int) $db->setQuery($query)->loadResult();
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
