<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

/**
 * Lets a person who signed in through the portal take over their old JED account.
 *
 * The portal creates a new account at the first login, so the old one cannot be linked beforehand.
 * Instead the new account asks to claim it. The proof of ownership is the mailbox of the OLD
 * account, not the portal's address (which the portal does not verify): a one time link goes to the
 * address stored on the old account, and opening it, signed in as the account that asked, completes
 * the claim.
 *
 * Completing it moves the portal identity onto the old account. The old account keeps its id, so its
 * extensions, reviews, favourites, tickets and transfers stay attached, and takes the GUID as its
 * username. The empty account the portal created is removed in the same transaction.
 *
 * @since 4.0.0
 */
final class ClaimService
{
    /**
     * Hours a link stays valid.
     *
     * @since 4.0.0
     */
    public const HOURS = 24;

    /**
     * Seconds that must pass before the same account can ask again.
     *
     * @since 4.0.0
     */
    public const WAIT = 60;

    public const SENT        = 'sent';
    public const THROTTLED   = 'throttled';
    public const NOT_PORTAL  = 'not_portal_account';
    public const HAS_CONTENT = 'has_content';

    /**
     * Mails to one old account that the requests of different people may cause within a day.
     *
     * @since 4.0.0
     */
    public const MAILS_PER_ACCOUNT = 3;

    /**
     * Where the new account may already have left a trace. Removing it would lose these rows or
     * leave them pointing at nobody, and for bans that would lift the ban, so a claim is refused
     * while any exist. Each entry is [table, column].
     *
     * @var array<int, array{0: string, 1: string}>
     *
     * @since 4.0.0
     */
    private const CONTENT = [
        ['#__jed_extensions', 'owner'],
        ['#__jed_extensions_maintainers', 'user_id'],
        ['#__jed_reviews', 'created_by'],
        ['#__jed_favorites', 'user_id'],
        ['#__jed_user_access', 'user_id'],
        ['#__jed_user_review_bans', 'user_id'],
        ['#__jed_extension_transfers', 'from_user_id'],
        ['#__jed_extension_transfers', 'to_user_id'],
        ['#__jed_tickets', 'created_by'],
    ];

    /**
     * @param DatabaseInterface $db The database.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Start a claim: find the old account and mail it a link.
     *
     * The caller shows the same answer whatever the result, apart from the codes that say something
     * about the caller themselves, so the form cannot be used to find out which names or addresses
     * have an account.
     *
     * @param int    $userId     The account that signed in through the portal.
     * @param string $identifier The username or email address of the old account.
     *
     * @return string One of the SENT, THROTTLED, NOT_PORTAL constants, or a ClaimRules code.
     *
     * @since 4.0.0
     */
    public function request(int $userId, string $identifier): string
    {
        $links = new IdentityLinks($this->db);
        $guid  = $links->guidForUser($userId);

        if ($guid === null) {
            return self::NOT_PORTAL;
        }

        $existing = $this->claimOf($userId);

        if ($existing !== null && strtotime($existing['created'] . ' UTC') > time() - self::WAIT) {
            return self::THROTTLED;
        }

        if ($this->contentOf($userId) > 0) {
            return self::HAS_CONTENT;
        }

        $old        = $this->findOld(trim($identifier));
        $privileged = $old !== null && (new PrivilegedAccounts($this->db))->isPrivileged((int) $old['id']);
        $why        = ClaimRules::oldAccountProblem($old, $userId, $old !== null && $links->guidForUser((int) $old['id']) !== null, $privileged);
        $token      = bin2hex(random_bytes(32));

        // Whatever the outcome, the attempt leaves a row, so the one minute wait applies to a name
        // that matched and to one that did not. A row for no account has old_user_id 0 and can
        // never be confirmed.
        if ($why !== null) {
            $this->record($userId, 0, $token);

            return $why;
        }

        // Nobody can be made to receive more than a few of these a day, whoever asks. The caller
        // is told it was sent either way, as for any other address.
        if ($this->recentClaimsFor((int) $old['id']) >= self::MAILS_PER_ACCOUNT) {
            $this->record($userId, 0, $token);

            return self::SENT;
        }

        $this->record($userId, (int) $old['id'], $token);
        $this->mail($old, $token);

        return self::SENT;
    }

    /**
     * Finish a claim: the link was opened by the account that asked.
     *
     * @param int    $userId The signed-in account.
     * @param string $token  The token from the link.
     *
     * @return int The id of the old account, which now holds the portal identity.
     *
     * @throws RuntimeException With a ClaimRules code as message when the claim cannot be completed.
     *
     * @since 4.0.0
     */
    public function confirm(int $userId, string $token): int
    {
        $claim = $this->claimOf($userId);
        $why   = ClaimRules::tokenProblem($claim, $token, gmdate('Y-m-d H:i:s'));

        if ($why !== null) {
            throw new RuntimeException($why);
        }

        $links = new IdentityLinks($this->db);
        $guid  = $links->guidForUser($userId);
        $oldId = (int) $claim['old_user_id'];
        $old   = $this->findOld((string) $oldId, true);
        $why   = $guid === null
            ? self::NOT_PORTAL
            : ClaimRules::oldAccountProblem(
                $old,
                $userId,
                $links->guidForUser($oldId) !== null,
                (new PrivilegedAccounts($this->db))->isPrivileged($oldId)
            );

        if ($why === null && $this->contentOf($userId) > 0) {
            $why = self::HAS_CONTENT;
        }

        if ($why !== null) {
            throw new RuntimeException($why);
        }

        $this->db->transactionStart();

        try {
            $this->deleteClaim($userId);
            $links->unlink($userId);

            $new = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

            if (!$new instanceof User || !$new->delete()) {
                throw new RuntimeException('the new account could not be removed');
            }

            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__users'))
                    ->set($this->db->quoteName('username') . ' = :guid')
                    ->where($this->db->quoteName('id') . ' = :uid')
                    ->bind(':guid', $guid)
                    ->bind(':uid', $oldId, ParameterType::INTEGER)
            )->execute();

            if (!$links->link($oldId, $guid, IdentityLinks::SOURCE_CLAIM)) {
                throw new RuntimeException('the old account could not be linked');
            }

            // From now on the portal is the way in. The old password is replaced by one nobody
            // knows, and any session already open under the old account ends.
            $unknown = UserHelper::hashPassword(bin2hex(random_bytes(32)));

            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__users'))
                    ->set($this->db->quoteName('password') . ' = :password')
                    ->where($this->db->quoteName('id') . ' = :uid')
                    ->bind(':password', $unknown)
                    ->bind(':uid', $oldId, ParameterType::INTEGER)
            )->execute();

            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->delete($this->db->quoteName('#__session'))
                    ->where($this->db->quoteName('userid') . ' = :uid')
                    ->bind(':uid', $oldId, ParameterType::INTEGER)
            )->execute();

            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();

            Log::add(sprintf('plg_system_jedidentity: claim of %d by %d failed: %s', $oldId, $userId, $e->getMessage()), Log::WARNING, 'com_jed');

            throw new RuntimeException('failed', 0, $e);
        }

        return $oldId;
    }

    /**
     * Find the old account by username or email address.
     *
     * @param string  $identifier The value typed, or an id when $byId is set.
     * @param boolean $byId       Look up by id.
     *
     * @return array{id: int, username: string, email: string, name: string, block: int}|null
     *
     * @since 4.0.0
     */
    private function findOld(string $identifier, bool $byId = false): ?array
    {
        if ($identifier === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'username', 'email', 'name', 'block']))
            ->from($this->db->quoteName('#__users'))
            ->setLimit(2);

        if ($byId) {
            $id = (int) $identifier;
            $query->where($this->db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
        } else {
            $query->where('(' . $this->db->quoteName('username') . ' = :u OR LOWER(' . $this->db->quoteName('email') . ') = LOWER(:e))')
                ->bind(':u', $identifier)
                ->bind(':e', $identifier);
        }

        $rows = $this->db->setQuery($query)->loadAssocList() ?: [];

        // Two accounts answering to one input is not a match anybody can be sure of.
        return \count($rows) === 1 ? $rows[0] : null;
    }

    /**
     * @param int $userId The account that asked.
     *
     * @return array<string, mixed>|null
     *
     * @since 4.0.0
     */
    private function claimOf(int $userId): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jed_identity_claims'))
            ->where($this->db->quoteName('user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER);

        return $this->db->setQuery($query)->loadAssoc() ?: null;
    }

    /**
     * Store the open claim of an account, replacing any earlier one.
     *
     * @param int    $userId The account that asked.
     * @param int    $oldId  The account asked for, or 0 when nothing can be confirmed.
     * @param string $token  The token that goes in the mail.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function record(int $userId, int $oldId, string $token): void
    {
        $this->db->transactionStart();

        try {
            $this->deleteClaim($userId);

            $row = (object) [
                'user_id'     => $userId,
                'old_user_id' => $oldId,
                'token_hash'  => ClaimRules::hashToken($token),
                'created'     => gmdate('Y-m-d H:i:s'),
                'expires'     => gmdate('Y-m-d H:i:s', time() + ($oldId > 0 ? self::HOURS * 3600 : 0)),
            ];

            $this->db->insertObject('#__jed_identity_claims', $row);
            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();

            throw $e;
        }
    }

    /**
     * How many claims on an old account were started in the last day.
     *
     * @param int $oldId The old account.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function recentClaimsFor(int $oldId): int
    {
        $since = gmdate('Y-m-d H:i:s', time() - 86400);
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__jed_identity_claims'))
            ->where($this->db->quoteName('old_user_id') . ' = :old')
            ->where($this->db->quoteName('created') . ' > :since')
            ->bind(':old', $oldId, ParameterType::INTEGER)
            ->bind(':since', $since);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * @param int $userId The account that asked.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function deleteClaim(int $userId): void
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_identity_claims'))
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * How many JED rows an account has made. A fresh portal account has none, and then removing it
     * loses nothing; one that has already been used is not removed behind its owner's back.
     *
     * @param int $userId The account.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function contentOf(int $userId): int
    {
        $count = 0;

        foreach (self::CONTENT as [$table, $column]) {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName($table))
                ->where($this->db->quoteName($column) . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER);

            $count += (int) $this->db->setQuery($query)->loadResult();
        }

        return $count;
    }

    /**
     * Send the link to the address on the old account.
     *
     * @param array{id: int, email: string, name: string} $old   The old account.
     * @param string                                      $token The token.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function mail(array $old, string $token): void
    {
        try {
            $app    = Factory::getApplication();
            $mailer = new MailTemplate('com_jed.identity_claim', $app->getLanguage()->getTag());
            $mailer->addTemplateData([
                'SITENAME' => (string) $app->get('sitename'),
                'NAME'     => (string) $old['name'],
                'LINK'     => rtrim(Uri::root(), '/') . '/index.php?option=com_jed&task=identity.confirm&token=' . $token,
                'HOURS'    => (string) self::HOURS,
            ]);
            $mailer->addRecipient((string) $old['email'], (string) $old['name']);
            $mailer->send();
        } catch (Throwable $e) {
            Log::add(sprintf('com_jed: identity claim mail to user %d failed: %s', (int) $old['id'], $e->getMessage()), Log::WARNING, 'com_jed');
        }
    }
}
