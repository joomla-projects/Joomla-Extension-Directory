<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Privacy.vel
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Privacy\Vel\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin;
use Joomla\Component\Privacy\Administrator\Event\CollectCapabilitiesEvent;
use Joomla\Component\Privacy\Administrator\Event\ExportRequestEvent;
use Joomla\Component\Privacy\Administrator\Event\RemoveDataEvent;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * Privacy for the VEL (VEL2-PRD §6.9.5, §7): what it holds about a person, handed over and
 * taken away.
 *
 * Personal data lives in exactly three tables - reports, participants, messages - plus the
 * attachments they carry. Everything else in com_vel describes software, not people, and the
 * credits that do name a person name them because they asked to be named (`is_public`).
 *
 * Both export and erasure work by **account and by address**: a guest who reported without an
 * account is still a person with rights, and the request com_privacy raises carries the address.
 *
 * Erasure anonymises rather than deletes the report and the messages: the report is the
 * disclosure record that an advisory rests on, and the team has to be able to show what was
 * reported and when. What goes is everything that identifies the person - name, address,
 * organisation, attachments, the non-public credit - and the token that let them in.
 *
 * @since 4.0.0
 */
final class Vel extends PrivacyPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * @var boolean
     *
     * @since 4.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return array<string, string>
     *
     * @since 4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPrivacyCollectAdminCapabilities' => 'onPrivacyCollectAdminCapabilities',
            'onPrivacyExportRequest'            => 'onPrivacyExportRequest',
            'onPrivacyRemoveData'               => 'onPrivacyRemoveData',
        ];
    }

    /**
     * @param CollectCapabilitiesEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onPrivacyCollectAdminCapabilities(CollectCapabilitiesEvent $event): void
    {
        $this->loadLanguage();

        $event->addResult([
            Text::_('PLG_PRIVACY_VEL') => [
                Text::_('PLG_PRIVACY_VEL_CAPABILITY_REPORTS'),
                Text::_('PLG_PRIVACY_VEL_CAPABILITY_MESSAGES'),
                Text::_('PLG_PRIVACY_VEL_CAPABILITY_ATTACHMENTS'),
                Text::_('PLG_PRIVACY_VEL_CAPABILITY_CREDITS'),
                Text::_('PLG_PRIVACY_VEL_CAPABILITY_IP'),
                Text::_('PLG_PRIVACY_VEL_CAPABILITY_RETENTION'),
            ],
        ]);
    }

    /**
     * @param ExportRequestEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onPrivacyExportRequest(ExportRequestEvent $event): void
    {
        [$userId, $email] = $this->subject($event);

        if ($userId <= 0 && $email === '') {
            return;
        }

        $db      = $this->getDatabase();
        $domains = [];

        $domains[] = $this->domain('vel_reports', $this->rows($db, '#__vel_reports', 'reporter_user_id', 'reporter_email', $userId, $email, [
            'id', 'kind', 'state', 'reporter_name', 'reporter_email', 'reporter_organisation', 'reporter_role', 'reporter_wants_credit',
            'reporter_credit_name', 'share_with_developer', 'extension_name', 'extension_version', 'extension_url', 'developer_name',
            'vulnerability_class', 'description', 'steps_to_reproduce', 'impact', 'actively_exploited', 'publicly_disclosed',
            'disclosure_url', 'developer_contact_status', 'patch_url', 'fixed_version', 'tracking_reference', 'additional_info',
            'consent_to_process', 'consent_time', 'channel', 'created',
        ]));

        $participants = $this->rows($db, '#__vel_participants', 'user_id', 'email', $userId, $email, ['id', 'advisory_id', 'report_id', 'role', 'name', 'email', 'notify', 'created']);
        $domains[]    = $this->domain('vel_participants', $participants);

        $participantIds = array_map(static fn (array $p): int => (int) $p['id'], $participants);

        if ($participantIds !== []) {
            $messages = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'advisory_id', 'report_id', 'direction', 'channel', 'subject', 'body', 'created']))
                    ->from($db->quoteName('#__vel_messages'))
                    ->whereIn($db->quoteName('participant_id'), $participantIds)
            )->loadAssocList() ?: [];

            $domains[] = $this->domain('vel_messages', $messages);
        }

        $credits = $userId > 0 ? $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['id', 'advisory_id', 'name', 'role', 'is_public']))
                ->from($db->quoteName('#__vel_advisory_credits'))
                ->where($db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadAssocList() ?: [] : [];

        if ($credits !== []) {
            $domains[] = $this->domain('vel_credits', $credits);
        }

        $event->addResult($domains);
    }

    /**
     * @param RemoveDataEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onPrivacyRemoveData(RemoveDataEvent $event): void
    {
        [$userId, $email] = $this->subject($event);

        if ($userId <= 0 && $email === '') {
            return;
        }

        $db     = $this->getDatabase();
        $report = ['reports' => 0, 'participants' => 0, 'attachments' => 0, 'credits' => 0];

        try {
            // Reports: anonymise the person, keep the disclosure.
            $reportIds = array_map('intval', array_column($this->rows($db, '#__vel_reports', 'reporter_user_id', 'reporter_email', $userId, $email, ['id']), 'id'));

            foreach ($reportIds as $reportId) {
                $anon = 'Reporter #' . $reportId;
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__vel_reports'))
                        ->set($db->quoteName('reporter_name') . ' = :anon')
                        ->set($db->quoteName('reporter_email') . ' = ' . $db->quote(''))
                        ->set($db->quoteName('reporter_organisation') . ' = ' . $db->quote(''))
                        ->set($db->quoteName('reporter_credit_name') . ' = ' . $db->quote(''))
                        ->set($db->quoteName('reporter_wants_credit') . ' = 0')
                        ->set($db->quoteName('reporter_user_id') . ' = 0')
                        ->set($db->quoteName('reporter_ip_hash') . ' = NULL')
                        ->set($db->quoteName('verify_token') . ' = NULL')
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':anon', $anon)
                        ->bind(':id', $reportId, ParameterType::INTEGER)
                )->execute();

                $report['reports']++;
                $report['attachments'] += $this->deleteAttachments($db, null, $reportId);
            }

            // Participants: anonymise, revoke the token, stop the mail.
            $participantIds = array_map('intval', array_column($this->rows($db, '#__vel_participants', 'user_id', 'email', $userId, $email, ['id']), 'id'));

            foreach ($participantIds as $participantId) {
                $anon = 'Participant #' . $participantId;
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__vel_participants'))
                        ->set($db->quoteName('name') . ' = :anon')
                        ->set($db->quoteName('email') . ' = ' . $db->quote(''))
                        ->set($db->quoteName('user_id') . ' = NULL')
                        ->set($db->quoteName('access_token') . ' = NULL')
                        ->set($db->quoteName('token_expires') . ' = NULL')
                        ->set($db->quoteName('notify') . ' = 0')
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':anon', $anon)
                        ->bind(':id', $participantId, ParameterType::INTEGER)
                )->execute();

                $report['participants']++;

                $messageIds = $db->setQuery(
                    $db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName('#__vel_messages'))
                        ->where($db->quoteName('participant_id') . ' = :pid')->bind(':pid', $participantId, ParameterType::INTEGER)
                )->loadColumn() ?: [];

                foreach ($messageIds as $messageId) {
                    $report['attachments'] += $this->deleteAttachments($db, (int) $messageId, null);
                }
            }

            // Non-public credits go; public ones were asked for and stay.
            if ($userId > 0) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__vel_advisory_credits'))
                        ->where($db->quoteName('user_id') . ' = :uid')
                        ->where($db->quoteName('is_public') . ' = 0')
                        ->bind(':uid', $userId, ParameterType::INTEGER)
                )->execute();
                $report['credits'] = (int) $db->getAffectedRows();

                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__vel_advisory_credits'))
                        ->set($db->quoteName('user_id') . ' = NULL')
                        ->where($db->quoteName('user_id') . ' = :uid')
                        ->bind(':uid', $userId, ParameterType::INTEGER)
                )->execute();
            }
        } catch (Throwable $e) {
            Log::add('plg_privacy_vel: removal failed: ' . $e->getMessage(), Log::ERROR, 'com_vel');

            return;
        }

        try {
            Log::add(\sprintf('plg_privacy_vel: removal request %d completed: %s', (int) $event->getRequest()->id, json_encode($report)), Log::INFO, 'com_vel');
        } catch (Throwable $e) {
            // A logger that is not configured must not turn a completed erasure into a failed one.
        }
    }

    /**
     * @param \Joomla\Event\EventInterface $event The event.
     *
     * @return array{0:int,1:string}  [user id, e-mail].
     *
     * @since 4.0.0
     */
    private function subject(\Joomla\Event\EventInterface $event): array
    {
        $user    = method_exists($event, 'getUser') ? $event->getUser() : $event->getArgument('user');
        $request = method_exists($event, 'getRequest') ? $event->getRequest() : $event->getArgument('request');

        return [(int) ($user->id ?? 0), mb_strtolower(trim((string) ($request->email ?? $user->email ?? '')))];
    }

    /**
     * @param \Joomla\Database\DatabaseInterface $db          The database driver.
     * @param string                             $table       Table.
     * @param string                             $userColumn  User id column.
     * @param string                             $emailColumn E-mail column.
     * @param int                                $userId      User id.
     * @param string                             $email       E-mail.
     * @param string[]                           $columns     Columns to select.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function rows(\Joomla\Database\DatabaseInterface $db, string $table, string $userColumn, string $emailColumn, int $userId, string $email, array $columns): array
    {
        $query = $db->getQuery(true)->select($db->quoteName($columns))->from($db->quoteName($table));
        $or    = [];

        if ($userId > 0) {
            $or[] = $db->quoteName($userColumn) . ' = :uid';
            $query->bind(':uid', $userId, ParameterType::INTEGER);
        }

        if ($email !== '') {
            $or[] = 'LOWER(' . $db->quoteName($emailColumn) . ') = :email';
            $query->bind(':email', $email);
        }

        $query->where('(' . implode(' OR ', $or) . ')');

        return $db->setQuery($query)->loadAssocList() ?: [];
    }

    /**
     * @param \Joomla\Database\DatabaseInterface $db        The database driver.
     * @param int|null                           $messageId The message.
     * @param int|null                           $reportId  The report.
     *
     * @return int  Files deleted.
     *
     * @since 4.0.0
     */
    private function deleteAttachments(\Joomla\Database\DatabaseInterface $db, ?int $messageId, ?int $reportId): int
    {
        if (!class_exists('\\Jed\\Component\\Vel\\Administrator\\Service\\AttachmentService')) {
            return 0;
        }

        $service     = new \Jed\Component\Vel\Administrator\Service\AttachmentService($db);
        $attachments = $service->forThread($messageId !== null ? [$messageId] : [], $reportId !== null ? [$reportId] : []);

        foreach ($attachments as $attachment) {
            $service->delete((int) $attachment->id);
        }

        return \count($attachments);
    }

    /**
     * @param string $name The domain name.
     * @param array  $rows The rows.
     *
     * @return \Joomla\Component\Privacy\Administrator\Export\Domain
     *
     * @since 4.0.0
     */
    private function domain(string $name, array $rows): \Joomla\Component\Privacy\Administrator\Export\Domain
    {
        $domain = $this->createDomain($name, $name . '_data');

        foreach ($rows as $row) {
            $domain->addItem($this->createItemFromArray($row, isset($row['id']) ? (int) $row['id'] : null));
        }

        return $domain;
    }
}
