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

use InvalidArgumentException;

/**
 * Inbound mail posted by a mail provider's "inbound parse" feature (VEL2-PRD D-4 option ii).
 *
 * Accepts one generic JSON shape, which the provider's webhook is mapped onto by a small
 * adapter on their side (or by their template feature):
 *
 *   { "from": {"email": "...", "name": "..."}, "subject": "...", "text": "...", "html": "...",
 *     "headers": {"message-id": "...", "in-reply-to": "...", "references": "...", "auto-submitted": "..."},
 *     "attachments": [ {"filename": "...", "content": "<base64>", "type": "..."} ],
 *     "id": "<provider id>" }
 *
 * Authenticated by an HMAC-SHA256 of the raw body with the shared secret, sent as
 * `X-VEL-Signature: sha256=<hex>`. The controller that receives the post checks the signature
 * through {@see self::verify()} before anything is parsed.
 *
 * @since 4.0.0
 */
final class WebhookReceiver
{
    /**
     * @param string $rawBody   The request body.
     * @param string $signature The X-VEL-Signature header.
     * @param string $secret    The configured secret.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function verify(string $rawBody, string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, trim($signature));
    }

    /**
     * @param array $payload The decoded body.
     *
     * @return InboundMessage
     *
     * @throws InvalidArgumentException  Not the expected shape.
     *
     * @since 4.0.0
     */
    public static function parse(array $payload): InboundMessage
    {
        $from = $payload['from'] ?? null;

        if (\is_string($from)) {
            // "Name <address>" or a bare address.
            if (preg_match('/^(.*)<([^>]+)>\s*$/', $from, $m)) {
                $from = ['name' => trim($m[1], " \"'"), 'email' => trim($m[2])];
            } else {
                $from = ['name' => '', 'email' => trim($from)];
            }
        }

        if (!\is_array($from) || empty($from['email'])) {
            // A language key: this class runs without Joomla (it is unit-tested that way), so the
            // controller translates it.
            throw new InvalidArgumentException('COM_VEL_ERROR_WEBHOOK_NO_SENDER');
        }

        $headers = [];

        foreach ((array) ($payload['headers'] ?? []) as $key => $value) {
            $headers[strtolower(str_replace('_', '-', (string) $key))] = \is_array($value) ? implode(' ', $value) : (string) $value;
        }

        $text = (string) ($payload['text'] ?? '');

        if (trim($text) === '') {
            $text = InboundMessage::htmlToText((string) ($payload['html'] ?? ''));
        }

        $references = [];

        foreach (preg_split('/\s+/', (string) ($headers['references'] ?? '')) ?: [] as $reference) {
            $bracketed = ImapFetcher::bracket($reference);

            if ($bracketed !== null) {
                $references[] = $bracketed;
            }
        }

        $auto       = false;
        $autoHeader = strtolower((string) ($headers['auto-submitted'] ?? ''));
        $precedence = strtolower((string) ($headers['precedence'] ?? ''));

        if (($autoHeader !== '' && $autoHeader !== 'no') || \in_array($precedence, ['bulk', 'junk', 'list', 'auto_reply'], true) || isset($headers['list-id'])) {
            $auto = true;
        }

        $attachments = [];

        foreach ((array) ($payload['attachments'] ?? []) as $attachment) {
            if (!\is_array($attachment) || empty($attachment['filename'])) {
                continue;
            }

            $content = base64_decode((string) ($attachment['content'] ?? ''), true);

            if ($content === false || $content === '') {
                continue;
            }

            $attachments[] = [
                'name'    => (string) $attachment['filename'],
                'content' => $content,
                'mime'    => (string) ($attachment['type'] ?? 'application/octet-stream'),
            ];
        }

        return new InboundMessage(
            (string) $from['email'],
            (string) ($from['name'] ?? ''),
            (string) ($payload['subject'] ?? ''),
            $text,
            ImapFetcher::bracket((string) ($headers['message-id'] ?? '')),
            ImapFetcher::bracket((string) ($headers['in-reply-to'] ?? '')),
            $references,
            $attachments,
            $auto,
            'webhook:' . (string) ($payload['id'] ?? hash('sha256', json_encode($payload)))
        );
    }
}
