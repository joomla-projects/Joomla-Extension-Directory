<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Mail;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

/**
 * Polls the team mailbox over IMAP (VEL2-PRD D-4, §8.7) and hands each message to
 * {@see ThreadResolver}.
 *
 * Built on `webklex/php-imap`, a pure-PHP client - `ext-imap` left PHP core in 8.4 and is not
 * something to depend on. The library is loaded through the component's vendor autoloader when
 * present; without it this class reports that clearly rather than failing obscurely.
 *
 * Processed messages are moved to the configured folder so a re-run never reads them twice;
 * Message-IDs are checked as well in case a move fails.
 *
 * @since 4.0.0
 */
class ImapFetcher
{
    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @return bool  Whether the IMAP library is available.
     *
     * @since 4.0.0
     */
    public static function available(): bool
    {
        $autoload = JPATH_ADMINISTRATOR . '/components/com_vel/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }

        return class_exists('\\Webklex\\PHPIMAP\\ClientManager');
    }

    /**
     * Fetch and process unseen mail.
     *
     * @param int $limit The most messages to handle in one run.
     *
     * @return array{fetched:int, replies:int, reports:int, skipped:int, duplicates:int}
     *
     * @throws RuntimeException  Not configured, library missing, or the connection failed.
     *
     * @since 4.0.0
     */
    public function run(int $limit = 50): array
    {
        if (!self::available()) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_IMAP_LIBRARY_MISSING'));
        }

        $params = ComponentHelper::getParams('com_vel');
        $host   = trim((string) $params->get('imap_host', ''));

        if ($host === '' || trim((string) $params->get('imap_username', '')) === '') {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_IMAP_NOT_CONFIGURED'));
        }

        $manager = new \Webklex\PHPIMAP\ClientManager([]);
        $client  = $manager->make([
            'host'          => $host,
            'port'          => (int) $params->get('imap_port', 993),
            'encryption'    => (string) $params->get('imap_encryption', 'ssl') === 'none' ? false : (string) $params->get('imap_encryption', 'ssl'),
            'validate_cert' => true,
            'username'      => (string) $params->get('imap_username', ''),
            'password'      => (string) $params->get('imap_password', ''),
            'protocol'      => 'imap',
        ]);

        $client->connect();

        $folderName = (string) ($params->get('imap_folder', 'INBOX') ?: 'INBOX');
        $processed  = (string) ($params->get('imap_processed_folder', 'Processed') ?: 'Processed');
        $folder     = $client->getFolderByPath($folderName);

        if ($folder === null) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_IMAP_FOLDER_NOT_FOUND', $folderName));
        }

        try {
            if ($client->getFolderByPath($processed) === null) {
                $client->createFolder($processed);
            }
        } catch (\Throwable $e) {
            // Cannot create; messages stay where they are and the Message-ID check deduplicates.
        }

        $messages = $folder->messages()->unseen()->limit($limit)->get();
        $resolver = new ThreadResolver($this->db);
        $stats    = ['fetched' => 0, 'replies' => 0, 'reports' => 0, 'skipped' => 0, 'duplicates' => 0];

        foreach ($messages as $message) {
            $stats['fetched']++;

            try {
                $inbound = $this->normalise($message);
                $result  = $resolver->handle($inbound);

                match ($result['result']) {
                    ThreadResolver::RESULT_REPLY     => $stats['replies']++,
                    ThreadResolver::RESULT_NEW       => $stats['reports']++,
                    ThreadResolver::RESULT_DUPLICATE => $stats['duplicates']++,
                    default                          => $stats['skipped']++,
                };

                $message->setFlag('Seen');

                try {
                    $message->move($processed);
                } catch (\Throwable $e) {
                    // Left in place, flagged seen.
                }
            } catch (\Throwable $e) {
                Log::add('VEL IMAP: message could not be processed: ' . $e->getMessage(), Log::WARNING, 'com_vel');
                $stats['skipped']++;
            }
        }

        $client->disconnect();

        return $stats;
    }

    /**
     * @param object $message A webklex Message.
     *
     * @return InboundMessage
     *
     * @since 4.0.0
     */
    private function normalise(object $message): InboundMessage
    {
        $from      = $message->getFrom()->first();
        $fromEmail = (string) ($from->mail ?? '');
        $fromName  = (string) ($from->personal ?? '');
        $subject   = (string) $message->getSubject();
        $text      = (string) ($message->getTextBody() ?? '');

        if (trim($text) === '') {
            $text = InboundMessage::htmlToText((string) ($message->getHTMLBody() ?? ''));
        }

        $header     = $message->getHeader();
        $messageId  = self::bracket((string) ($header->get('message_id') ?? $header->get('message-id') ?? ''));
        $inReplyTo  = self::bracket((string) ($header->get('in_reply_to') ?? $header->get('in-reply-to') ?? ''));
        $references = [];

        foreach (preg_split('/\s+/', (string) ($header->get('references') ?? '')) ?: [] as $reference) {
            $bracketed = self::bracket($reference);

            if ($bracketed !== null) {
                $references[] = $bracketed;
            }
        }

        $auto = false;

        foreach (['auto_submitted', 'auto-submitted', 'precedence', 'x_auto_response_suppress', 'list_id', 'list-id'] as $key) {
            $value = strtolower((string) ($header->get($key) ?? ''));

            if ($value !== '' && $value !== 'no' && ($key !== 'precedence' || \in_array($value, ['bulk', 'junk', 'list', 'auto_reply'], true))) {
                $auto = true;
            }
        }

        $attachments = [];

        foreach ($message->getAttachments() as $attachment) {
            $attachments[] = [
                'name'    => (string) $attachment->getName(),
                'content' => (string) $attachment->getContent(),
                'mime'    => (string) ($attachment->getMimeType() ?? 'application/octet-stream'),
            ];
        }

        return new InboundMessage($fromEmail, $fromName, $subject, $text, $messageId, $inReplyTo, $references, $attachments, $auto, 'imap:' . (string) $message->getUid());
    }

    /**
     * @param string $id A message id with or without angle brackets.
     *
     * @return string|null  With brackets, or null for empty.
     *
     * @since 4.0.0
     */
    public static function bracket(string $id): ?string
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        return '<' . trim($id, '<>') . '>';
    }
}
