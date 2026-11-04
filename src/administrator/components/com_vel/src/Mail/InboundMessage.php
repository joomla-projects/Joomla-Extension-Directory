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

/**
 * One inbound e-mail, normalised, whichever way it arrived (IMAP, webhook) - VEL2-PRD §8.7.
 *
 * @since 4.0.0
 */
final class InboundMessage
{
    /**
     * @param string                                      $fromEmail   Sender address.
     * @param string                                      $fromName    Sender name.
     * @param string                                      $subject     Subject.
     * @param string                                      $text        Plain-text body (HTML converted if there was only HTML).
     * @param string|null                                 $messageId   RFC 5322 Message-ID, with brackets.
     * @param string|null                                 $inReplyTo   In-Reply-To, with brackets.
     * @param string[]                                    $references  References, each with brackets.
     * @param array<int, array{name:string, content:string, mime:string}> $attachments Attachments.
     * @param bool                                        $autoSubmitted Auto-Submitted / Precedence: bulk / list headers present.
     * @param string                                      $externalId  The source's own id (IMAP UID, provider id) for idempotency.
     *
     * @since 4.0.0
     */
    public function __construct(
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly string $subject,
        public readonly string $text,
        public readonly ?string $messageId,
        public readonly ?string $inReplyTo,
        public readonly array $references,
        public readonly array $attachments,
        public readonly bool $autoSubmitted,
        public readonly string $externalId
    ) {
    }

    /**
     * The case reference in the subject, if any: [VEL-0042] or [VELR-00123].
     *
     * @return array{0:?int,1:?int}  [advisoryId, reportId].
     *
     * @since 4.0.0
     */
    public function subjectReference(): array
    {
        if (preg_match('/\bVELR-?0*(\d+)\b/i', $this->subject, $m)) {
            return [null, (int) $m[1]];
        }

        if (preg_match('/\bVEL-?0*(\d+)\b/i', $this->subject, $m)) {
            return [(int) $m[1], null];
        }

        return [null, null];
    }

    /**
     * @param string $html HTML.
     *
     * @return string  Readable text.
     *
     * @since 4.0.0
     */
    public static function htmlToText(string $html): string
    {
        $text = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $text = preg_replace('/<(br|\/p|\/div|\/li|\/tr|\/h\d)[^>]*>/i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\n{3,}/', "\n\n", preg_replace('/[ \t]+/', ' ', $text) ?? $text) ?? $text);
    }

    /**
     * Strip the quoted previous mail from a reply, the way a helpdesk does, so the thread does
     * not repeat itself.
     *
     * @param string $text The body.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function stripQuoted(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $kept  = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // The markers mail clients put above a quote.
            if (preg_match('/^(On .+ wrote:|Am .+ schrieb .*:|Le .+ a écrit ?:|-----Original Message-----|________________________________|From: .+)$/iu', $trimmed)) {
                break;
            }

            if (str_starts_with($trimmed, '>')) {
                continue;
            }

            $kept[] = $line;
        }

        $result = trim(implode("\n", $kept));

        return $result !== '' ? $result : trim($text);
    }
}
