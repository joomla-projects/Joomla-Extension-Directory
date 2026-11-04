<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Vel;

use Jed\Component\Vel\Administrator\Mail\InboundMessage;
use Jed\Component\Vel\Administrator\Mail\WebhookReceiver;
use PHPUnit\Framework\TestCase;

/**
 * Inbound mail parsing: subject tokens, quoted-reply stripping, the webhook shape and its HMAC.
 */
final class MailTest extends TestCase
{
    public function testSubjectReference(): void
    {
        $mail = new InboundMessage('a@b.c', 'A', 'Re: [VEL-0042] Security report', 'x', null, null, [], [], false, 'id');
        self::assertSame([42, null], $mail->subjectReference());

        $mail = new InboundMessage('a@b.c', 'A', 'Re: [VELR-00123] Your report', 'x', null, null, [], [], false, 'id');
        self::assertSame([null, 123], $mail->subjectReference());

        $mail = new InboundMessage('a@b.c', 'A', 'Hello', 'x', null, null, [], [], false, 'id');
        self::assertSame([null, null], $mail->subjectReference());
    }

    public function testStripQuoted(): void
    {
        $text = "Thanks, fixed in 2.0.1.\n\nOn Mon, 1 Jan 2026, VEL team wrote:\n> Please confirm\n> the version.";
        self::assertSame('Thanks, fixed in 2.0.1.', InboundMessage::stripQuoted($text));

        $text = "> quoted only\n> lines";
        self::assertSame($text, InboundMessage::stripQuoted($text));
    }

    public function testHtmlToText(): void
    {
        self::assertSame("Hello\nWorld & co", InboundMessage::htmlToText('<p>Hello</p><p>World &amp; co</p><script>x()</script>'));
    }

    public function testWebhookSignature(): void
    {
        $body   = '{"a":1}';
        $secret = 's3cret';
        $good   = 'sha256=' . hash_hmac('sha256', $body, $secret);

        self::assertTrue(WebhookReceiver::verify($body, $good, $secret));
        self::assertFalse(WebhookReceiver::verify($body, 'sha256=deadbeef', $secret));
        self::assertFalse(WebhookReceiver::verify($body, $good, ''));
        self::assertFalse(WebhookReceiver::verify($body, '', $secret));
    }

    public function testWebhookParse(): void
    {
        $mail = WebhookReceiver::parse([
            'from'        => 'Jane Doe <jane@example.org>',
            'subject'     => 'Re: [VEL-0007] hello',
            'html'        => '<p>Reply<br>text</p>',
            'headers'     => ['Message-ID' => 'abc@x', 'In-Reply-To' => '<vel-0007.deadbeef@vel.joomla.org>', 'References' => '<one@x> <two@x>', 'Auto-Submitted' => 'no'],
            'attachments' => [['filename' => 'poc.txt', 'content' => base64_encode('payload'), 'type' => 'text/plain'], ['filename' => 'bad', 'content' => '***']],
            'id'          => 'p-1',
        ]);

        self::assertSame('jane@example.org', $mail->fromEmail);
        self::assertSame('Jane Doe', $mail->fromName);
        self::assertSame("Reply\ntext", $mail->text);
        self::assertSame('<abc@x>', $mail->messageId);
        self::assertSame('<vel-0007.deadbeef@vel.joomla.org>', $mail->inReplyTo);
        self::assertSame(['<one@x>', '<two@x>'], $mail->references);
        self::assertFalse($mail->autoSubmitted);
        self::assertCount(1, $mail->attachments);
        self::assertSame('payload', $mail->attachments[0]['content']);
        self::assertSame('webhook:p-1', $mail->externalId);
        self::assertSame([7, null], $mail->subjectReference());
    }

    public function testWebhookAutoSubmittedDetected(): void
    {
        $mail = WebhookReceiver::parse(['from' => ['email' => 'x@y.z'], 'subject' => 's', 'text' => 't', 'headers' => ['precedence' => 'bulk']]);
        self::assertTrue($mail->autoSubmitted);
    }

    public function testWebhookRejectsMissingSender(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookReceiver::parse(['subject' => 's']);
    }
}
