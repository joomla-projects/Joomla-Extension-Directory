<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Transfer;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Log\JedActionLog;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Ownership transfer: request, confirm, cancel, force, expire.
 *
 * The rules this implements come from 8.8.1 and are all consequences of one decision - that a
 * listing changes hands only when both people say so:
 *
 *  - Two tokens, one per party, each single-use and **stored only as a hash**. Reading the
 *    database must not enable a transfer.
 *  - Tokens identify the transfer; they do not authenticate anybody. Confirmation requires being
 *    logged in as the person the transfer records, so a forwarded or intercepted mail is worth
 *    nothing on its own.
 *  - Tokens are bound to the user id, not the address, so changing an address mid-flight does
 *    not strand a transfer.
 *  - One open transfer per extension.
 *
 * @since 4.0.0
 */
final class TransferService
{
    /**
     * How long a request stays open. 8.8.1 asks for 7-14 days; two weeks is the generous end,
     * because the recipient may be a person who checks that address rarely.
     *
     * @since 4.0.0
     */
    public const EXPIRY_DAYS = 14;

    /**
     * Recipient lookups one user may make per window, and the window in hours.
     *
     * The address field answers "does this address have an account". That is a disclosure, and
     * 8.8.1 chose to allow it in a bounded form rather than make the feature unusable. The
     * attacker is always a logged-in extension owner - identifiable and finite - so a modest
     * ceiling plus an attributable log is proportionate. Enumerating a useful number of
     * addresses at this rate is not practical.
     *
     * @since 4.0.0
     */
    public const LOOKUP_LIMIT  = 10;
    public const LOOKUP_WINDOW = 24;

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Find the account behind an email address, subject to the rate limit.
     *
     * The **account** address from `#__users`, never the extension's `developer_email` - the two
     * are routinely different, and confusing them would send a transfer to whoever is listed as
     * the support contact.
     *
     * @param string $email       The address typed by the owner.
     * @param int    $byUserId    Who is asking.
     * @param int    $extensionId The extension the request is about, for the log.
     *
     * @return User  The recipient.
     *
     * @throws RuntimeException  Rate limit reached, or no usable account behind the address.
     *
     * @since 4.0.0
     */
    public function findRecipient(string $email, int $byUserId, int $extensionId): User
    {
        $email = strtolower(trim($email));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_EMAIL_INVALID'));
        }

        $this->assertLookupAllowed($byUserId);

        // Joomla enforces address uniqueness, so a match is unambiguous. Blocked and unactivated
        // accounts are excluded: they cannot confirm, and a transfer addressed to one would sit
        // open until it expired (8.8.1).
        $row = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['id', 'block', 'activation']))
                ->from($this->db->quoteName('#__users'))
                ->where('LOWER(' . $this->db->quoteName('email') . ') = :email')
                ->bind(':email', $email)
        )->loadAssoc();

        $usable = $row !== null
            && (int) $row['block'] === 0
            && ((string) $row['activation'] === '' || (string) $row['activation'] === '0');

        $this->logLookup($byUserId, $email, $usable, $extensionId);

        if (!$usable) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_NO_ACCOUNT'));
        }

        if ((int) $row['id'] === $byUserId) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_SELF'));
        }

        return Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int) $row['id']);
    }

    /**
     * Start a transfer and mail both parties their own link.
     *
     * @param int  $extensionId The extension.
     * @param User $recipient   The new owner, from findRecipient().
     * @param int  $initiatedBy Who started it.
     *
     * @return int  The transfer id.
     *
     * @throws RuntimeException  When the extension cannot be transferred right now.
     *
     * @since 4.0.0
     */
    public function initiate(int $extensionId, User $recipient, int $initiatedBy): int
    {
        $extension = $this->loadExtension($extensionId);

        if ((int) $extension['deleted'] === 1) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_DELETED'));
        }

        // A blocked listing may still be transferred: finding somebody able to fix the cause is
        // a legitimate reason to hand it on, and refusing would make a block a dead end (8.8.1).

        if ($this->getOpenTransfer($extensionId) !== null) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_ALREADY_OPEN'));
        }

        $fromUserId = (int) $extension['owner'];

        if ($fromUserId === (int) $recipient->id) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_SELF'));
        }

        $fromToken = $this->newToken();
        $toToken   = $this->newToken();
        $now       = Factory::getDate();

        $row = (object) [
            'extension_id'    => $extensionId,
            'from_user_id'    => $fromUserId,
            'to_user_id'      => (int) $recipient->id,
            'initiated_by'    => $initiatedBy,
            'initiated_time'  => $now->toSql(),
            'from_token_hash' => $this->hashToken($fromToken),
            'to_token_hash'   => $this->hashToken($toToken),
            'state'           => TransferState::PENDING->value,
            'expires'         => $now->modify('+' . self::EXPIRY_DAYS . ' days')->toSql(),
        ];

        $this->db->insertObject('#__jed_extension_transfers', $row, 'id');
        $transferId = (int) $row->id;

        $owner = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($fromUserId);

        // Each party gets their own token, and each is told the *name* of the other - never the
        // address. A transfer must not disclose an address the other side did not share (8.8.1).
        $this->notify($owner, 'com_jed.transfer_request_owner', [
            'EXTENSIONNAME' => (string) $extension['name'],
            'OTHERPARTY'    => $recipient->name,
            'CONFIRMLINK'   => $this->confirmationLink($transferId, $fromToken),
        ]);

        $this->notify($recipient, 'com_jed.transfer_request_recipient', [
            'EXTENSIONNAME' => (string) $extension['name'],
            'OTHERPARTY'    => $owner->name,
            'CONFIRMLINK'   => $this->confirmationLink($transferId, $toToken),
        ]);

        return $transferId;
    }

    /**
     * Confirm one side of a transfer.
     *
     * The token says *which* transfer and *which side*; the logged-in user says *who*. Both have
     * to agree. That is what makes a forwarded mail useless: whoever opens the link still has to
     * be the person the transfer records.
     *
     * @param int    $transferId The transfer.
     * @param string $token      The token from the link.
     * @param int    $userId     The logged-in user.
     *
     * @return TransferState  The state after this confirmation.
     *
     * @throws RuntimeException  Unknown, expired, already finished, or the wrong person.
     *
     * @since 4.0.0
     */
    public function confirm(int $transferId, string $token, int $userId): TransferState
    {
        $transfer = $this->loadTransfer($transferId);

        if ($transfer === null) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_UNKNOWN'));
        }

        $state = TransferState::from((string) $transfer['state']);

        if (!$state->isOpen()) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_CLOSED'));
        }

        if (strtotime((string) $transfer['expires']) < time()) {
            $this->expire($transferId);

            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_EXPIRED'));
        }

        // Which side does this token open? Compared with hash_equals against the stored hash, so
        // a token that is close but not equal is not distinguishable by timing.
        $hash        = $this->hashToken($token);
        $isRecipient = $transfer['to_token_hash'] !== null && hash_equals((string) $transfer['to_token_hash'], $hash);
        $isOwner     = $transfer['from_token_hash'] !== null && hash_equals((string) $transfer['from_token_hash'], $hash);

        if (!$isRecipient && !$isOwner) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_TOKEN'));
        }

        $expectedUser = $isRecipient ? (int) $transfer['to_user_id'] : (int) $transfer['from_user_id'];

        if ($userId !== $expectedUser) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_WRONG_USER'));
        }

        $newState = $state->afterConfirmationBy($isRecipient);
        $now      = Factory::getDate()->toSql();

        $update = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jed_extension_transfers'))
            ->set($this->db->quoteName('state') . ' = ' . $this->db->quote($newState->value))
            // Single use: the token that was just spent is cleared, so the same link cannot be
            // replayed and cannot be used to work out the other side's token.
            ->set($this->db->quoteName($isRecipient ? 'to_token_hash' : 'from_token_hash') . ' = NULL')
            ->set($this->db->quoteName($isRecipient ? 'to_confirmed_time' : 'from_confirmed_time') . ' = ' . $this->db->quote($now))
            ->where($this->db->quoteName('id') . ' = ' . $transferId);

        $this->db->setQuery($update)->execute();

        if ($newState === TransferState::COMPLETED) {
            $this->complete($transferId);
        }

        return $newState;
    }

    /**
     * Move a listing without the current owner's consent.
     *
     * The abandonware escape hatch. The reason is mandatory and is kept on the same record, both
     * parties are told afterwards - as information, not as consent - and the state says `forced`
     * so that a later reader can tell this apart from an agreed handover.
     *
     * @param int    $extensionId The extension.
     * @param User   $recipient   The new owner.
     * @param int    $byUserId    The team member.
     * @param string $reason      Why.
     *
     * @return int  The transfer id.
     *
     * @throws RuntimeException  When the reason is missing or the extension cannot be moved.
     *
     * @since 4.0.0
     */
    public function force(int $extensionId, User $recipient, int $byUserId, string $reason): int
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_REASON_REQUIRED'));
        }

        $extension = $this->loadExtension($extensionId);

        if ((int) $extension['owner'] === (int) $recipient->id) {
            throw new RuntimeException(Text::_('COM_JED_TRANSFER_ERROR_SELF'));
        }

        // An open request is superseded rather than left dangling.
        $open = $this->getOpenTransfer($extensionId);

        if ($open !== null) {
            $this->cancel((int) $open['id'], $byUserId, Text::_('COM_JED_TRANSFER_SUPERSEDED_BY_FORCE'));
        }

        $now = Factory::getDate()->toSql();
        $row = (object) [
            'extension_id'    => $extensionId,
            'from_user_id'    => (int) $extension['owner'],
            'to_user_id'      => (int) $recipient->id,
            'initiated_by'    => $byUserId,
            'initiated_time'  => $now,
            'from_token_hash' => null,
            'to_token_hash'   => null,
            'state'           => TransferState::FORCED->value,
            'expires'         => $now,
            'completed_time'  => $now,
            'cancel_reason'   => $reason,
        ];

        $this->db->insertObject('#__jed_extension_transfers', $row, 'id');

        $this->applyOwnership($extensionId, (int) $extension['owner'], (int) $recipient->id);

        $users = Factory::getContainer()->get(UserFactoryInterface::class);
        $owner = $users->loadUserById((int) $extension['owner']);

        foreach ([[$owner, $recipient], [$recipient, $owner]] as [$to, $other]) {
            $this->notify($to, 'com_jed.transfer_forced', [
                'EXTENSIONNAME' => (string) $extension['name'],
                'OTHERPARTY'    => $other->name,
                'REASONNOTES'   => $reason,
            ]);
        }

        // Only the forced handover is logged. An agreed one is the two parties' own business and
        // is already answerable from `#__jed_extension_transfers`, which - unlike the log - is
        // kept for good (`8.15` boundary 1).
        JedActionLog::record(JedActionLog::TRANSFER_FORCE, 'com_jed.extension', $extensionId, [
            'title'    => (string) $extension['name'],
            'from'     => $owner->name,
            'fromlink' => 'index.php?option=com_users&task=user.edit&id=' . (int) $owner->id,
            'to'       => $recipient->name,
            'tolink'   => 'index.php?option=com_users&task=user.edit&id=' . (int) $recipient->id,
            'reason'   => $reason,
        ]);

        return (int) $row->id;
    }

    /**
     * Call a transfer off.
     *
     * @param int    $transferId The transfer.
     * @param int    $byUserId   Who cancelled.
     * @param string $reason     Optional note.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function cancel(int $transferId, int $byUserId, string $reason = ''): void
    {
        $transfer = $this->loadTransfer($transferId);

        if ($transfer === null || !TransferState::from((string) $transfer['state'])->isOpen()) {
            return;
        }

        $this->closeAs($transferId, TransferState::CANCELLED, $byUserId, $reason);
        $this->notifyBothParties($transfer, 'com_jed.transfer_cancelled', ['REASONNOTES' => $reason]);
    }

    /**
     * Close a transfer nobody finished in time.
     *
     * @param int $transferId The transfer.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function expire(int $transferId): void
    {
        $transfer = $this->loadTransfer($transferId);

        if ($transfer === null || !TransferState::from((string) $transfer['state'])->isOpen()) {
            return;
        }

        $this->closeAs($transferId, TransferState::EXPIRED, null, '');
        $this->notifyBothParties($transfer, 'com_jed.transfer_expired', []);
    }

    /**
     * Close every open transfer that has run out of time.
     *
     * Called from the queue worker; also called opportunistically when a transfer is looked at,
     * so a stale one cannot be confirmed just because no scheduled task has run.
     *
     * @return int  How many were expired.
     *
     * @since 4.0.0
     */
    public function expireOverdue(): int
    {
        $open = array_map(
            static fn (array $s) => $s['value'],
            [
                ['value' => TransferState::PENDING->value],
                ['value' => TransferState::FROM_CONFIRMED->value],
                ['value' => TransferState::TO_CONFIRMED->value],
            ]
        );

        $ids = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__jed_extension_transfers'))
                ->whereIn($this->db->quoteName('state'), $open, ParameterType::STRING)
                ->where($this->db->quoteName('expires') . ' < ' . $this->db->quote(Factory::getDate()->toSql()))
        )->loadColumn();

        foreach ((array) $ids as $id) {
            $this->expire((int) $id);
        }

        return \count((array) $ids);
    }

    /**
     * The open transfer for an extension, if there is one.
     *
     * @param int $extensionId The extension.
     *
     * @return array|null
     *
     * @since 4.0.0
     */
    public function getOpenTransfer(int $extensionId): ?array
    {
        $this->expireOverdue();

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jed_extension_transfers'))
                ->where($this->db->quoteName('extension_id') . ' = :eid')
                ->whereIn(
                    $this->db->quoteName('state'),
                    [TransferState::PENDING->value, TransferState::FROM_CONFIRMED->value, TransferState::TO_CONFIRMED->value],
                    ParameterType::STRING
                )
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
                ->order($this->db->quoteName('id') . ' DESC'),
            0,
            1
        )->loadAssoc();
    }

    /**
     * Cancel every open transfer of an extension, for the events that invalidate one.
     *
     * Soft deletion and a privacy removal both make a pending handover meaningless; leaving it
     * open would let it complete later against a listing that is gone (8.8.1, `P1-18`).
     *
     * @param int    $extensionId The extension.
     * @param int    $byUserId    Who caused it.
     * @param string $reason      Why.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function cancelOpenFor(int $extensionId, int $byUserId, string $reason): void
    {
        $open = $this->getOpenTransfer($extensionId);

        if ($open !== null) {
            $this->cancel((int) $open['id'], $byUserId, $reason);
        }
    }

    /**
     * Move the owner column and clean up what ownership implies.
     *
     * @param int $extensionId The extension.
     * @param int $fromUserId  The previous owner.
     * @param int $toUserId    The new owner.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function applyOwnership(int $extensionId, int $fromUserId, int $toUserId): void
    {
        // `owner` and nothing else. `created_by` stays where it is - it is the authorship record
        // and does not move (8.8.1). The previous owner loses access purely because this column
        // changed, which is why P1-03 had to remove every created_by permission check first.
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_extensions'))
                ->set($this->db->quoteName('owner') . ' = :to')
                ->where($this->db->quoteName('id') . ' = :eid')
                ->bind(':to', $toUserId, ParameterType::INTEGER)
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->execute();

        // If the new owner was a maintainer, that row goes. The owner is never held in the
        // maintainers table, and leaving it would record the same person twice and break the
        // rule P1-03 enforces on the other write path.
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_extensions_maintainers'))
                ->where($this->db->quoteName('extension_id') . ' = :eid')
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
                ->bind(':uid', $toUserId, ParameterType::INTEGER)
        )->execute();

        // The previous owner has no maintainer row to remove - they were never in that table -
        // and deliberately does not gain one. 8.8.1: the old owner loses all access.

        $this->resetAuditConsent($extensionId);
    }

    /**
     * Withdraw the Claude audit consent, which was given by a person and not by a listing.
     *
     * The consent columns are `P1-27`'s to add. Until they exist this is a no-op, checked rather
     * than assumed - failing here would abort a transfer that has already been agreed.
     *
     * @param int $extensionId The extension.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function resetAuditConsent(int $extensionId): void
    {
        $columns = $this->db->getTableColumns('#__jed_extensions', false);

        if (!isset($columns['claude_audit_consent'])) {
            return;
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_extensions'))
                ->set($this->db->quoteName('claude_audit_consent') . ' = 0')
                ->where($this->db->quoteName('id') . ' = ' . $extensionId)
        )->execute();
    }

    /**
     * Finish an agreed transfer.
     *
     * @param int $transferId The transfer.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function complete(int $transferId): void
    {
        $transfer = $this->loadTransfer($transferId);

        if ($transfer === null) {
            return;
        }

        $now = Factory::getDate()->toSql();

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_extension_transfers'))
                ->set($this->db->quoteName('completed_time') . ' = ' . $this->db->quote($now))
                ->where($this->db->quoteName('id') . ' = ' . $transferId)
        )->execute();

        $this->applyOwnership(
            (int) $transfer['extension_id'],
            (int) $transfer['from_user_id'],
            (int) $transfer['to_user_id']
        );

        $this->notifyBothParties($transfer, 'com_jed.transfer_completed', []);
    }

    /**
     * Write a terminal state onto a transfer.
     *
     * @param int           $transferId The transfer.
     * @param TransferState $state      The state to write.
     * @param int|null      $byUserId   Who caused it, if anyone.
     * @param string        $reason     Optional note.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function closeAs(int $transferId, TransferState $state, ?int $byUserId, string $reason): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jed_extension_transfers'))
            ->set($this->db->quoteName('state') . ' = ' . $this->db->quote($state->value))
            // Both tokens die with the transfer, so a link in an old mail stops working the
            // moment it stops being valid rather than when somebody notices.
            ->set($this->db->quoteName('from_token_hash') . ' = NULL')
            ->set($this->db->quoteName('to_token_hash') . ' = NULL')
            ->where($this->db->quoteName('id') . ' = ' . $transferId);

        if ($byUserId !== null) {
            $query->set($this->db->quoteName('cancelled_by') . ' = ' . $byUserId);
        }

        if ($reason !== '') {
            $query->set($this->db->quoteName('cancel_reason') . ' = ' . $this->db->quote($reason));
        }

        $this->db->setQuery($query)->execute();
    }

    /**
     * Refuse a lookup once a user has made too many in the window.
     *
     * @param int $userId Who is asking.
     *
     * @return void
     *
     * @throws RuntimeException  When the ceiling is reached.
     *
     * @since 4.0.0
     */
    private function assertLookupAllowed(int $userId): void
    {
        $since = Factory::getDate('-' . self::LOOKUP_WINDOW . ' hours')->toSql();

        $used = (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__jed_transfer_lookups'))
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->where($this->db->quoteName('created') . ' >= :since')
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':since', $since, ParameterType::STRING)
        )->loadResult();

        if ($used >= self::LOOKUP_LIMIT) {
            throw new RuntimeException(
                Text::sprintf('COM_JED_TRANSFER_ERROR_RATE_LIMIT', self::LOOKUP_LIMIT, self::LOOKUP_WINDOW)
            );
        }
    }

    /**
     * Record a lookup attempt against the person who made it.
     *
     * @param int    $userId      Who asked.
     * @param string $email       The address asked about.
     * @param bool   $found       Whether it resolved to a usable account.
     * @param int    $extensionId The extension the request was about.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function logLookup(int $userId, string $email, bool $found, int $extensionId): void
    {
        $row = (object) [
            'user_id'      => $userId,
            'email_hash'   => hash('sha256', $email),
            'found'        => $found ? 1 : 0,
            'extension_id' => $extensionId ?: null,
            'created'      => Factory::getDate()->toSql(),
        ];

        $this->db->insertObject('#__jed_transfer_lookups', $row);
    }

    /**
     * A fresh token.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * The stored form of a token.
     *
     * Plain SHA-256 rather than a password hash: the input is 256 bits of randomness this code
     * generated, so there is nothing to brute-force and no salt to add. What matters is that the
     * database holds the hash and never the token.
     *
     * @param string $token The token.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The link that goes in the mail.
     *
     * @param int    $transferId The transfer.
     * @param string $token      The party's token.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function confirmationLink(int $transferId, string $token): string
    {
        // Built without Route::_() on purpose. This URL carries a credential, so it has to come
        // out well formed in every context the service can be called from - including the queue
        // worker and the CLI, where there is no router and Route::_() returns null. A non-SEF
        // link needs no routing to work, and a confirmation link gains nothing from being pretty.
        return rtrim(Uri::root(), '/')
            . '/index.php?option=com_jed&task=transfer.confirm&id=' . $transferId
            . '&token=' . urlencode($token);
    }

    /**
     * @param int $extensionId The extension.
     *
     * @return array
     *
     * @throws RuntimeException  When it does not exist.
     *
     * @since 4.0.0
     */
    private function loadExtension(int $extensionId): array
    {
        $row = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['id', 'name', 'owner', 'deleted', 'blocked']))
                ->from($this->db->quoteName('#__jed_extensions'))
                ->where($this->db->quoteName('id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->loadAssoc();

        if ($row === null) {
            throw new RuntimeException(Text::_('COM_JED_ITEM_DOESNT_EXIST'));
        }

        return $row;
    }

    /**
     * @param int $transferId The transfer.
     *
     * @return array|null
     *
     * @since 4.0.0
     */
    private function loadTransfer(int $transferId): ?array
    {
        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jed_extension_transfers'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $transferId, ParameterType::INTEGER)
        )->loadAssoc();
    }

    /**
     * Mail both sides of a transfer, each naming the other by name.
     *
     * @param array  $transfer The transfer row.
     * @param string $template The mail template id.
     * @param array  $data     Extra template data.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function notifyBothParties(array $transfer, string $template, array $data): void
    {
        $users     = Factory::getContainer()->get(UserFactoryInterface::class);
        $owner     = $users->loadUserById((int) $transfer['from_user_id']);
        $recipient = $users->loadUserById((int) $transfer['to_user_id']);
        $name      = JedHelper::getExtensionTitle((int) $transfer['extension_id']);

        foreach ([[$owner, $recipient], [$recipient, $owner]] as [$to, $other]) {
            $this->notify($to, $template, array_merge($data, [
                'EXTENSIONNAME' => $name,
                'OTHERPARTY'    => $other->name,
            ]));
        }
    }

    /**
     * Send one mail, and never let a mail failure decide the outcome of a transfer.
     *
     * @param User   $recipient The addressee.
     * @param string $template  The mail template id.
     * @param array  $data      Template data.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function notify(User $recipient, string $template, array $data): void
    {
        if (empty($recipient->email)) {
            return;
        }

        try {
            $mailer = new MailTemplate($template, Factory::getApplication()->getLanguage()->getTag());
            $mailer->addTemplateData(array_merge(
                ['SITENAME' => (string) Factory::getApplication()->get('sitename')],
                $data
            ));
            $mailer->addRecipient($recipient->email, $recipient->name);
            $mailer->send();
        } catch (\Throwable $e) {
            Log::add(
                sprintf('com_jed: transfer mail "%s" to user %d failed: %s', $template, (int) $recipient->id, $e->getMessage()),
                Log::WARNING,
                'com_jed'
            );
        }
    }
}
