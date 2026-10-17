<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Helper;

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use PHPUnit\Framework\TestCase;

/**
 * Extension descriptions are Markdown written by developers and shown on the public extension
 * page. Whatever version of the Markdown library is installed, the output must stay safe.
 *
 * @since 4.0.0
 */
final class RenderMarkdownTest extends TestCase
{
    public function testOrdinaryFormattingIsRendered(): void
    {
        $html = JedHelper::renderMarkdown("# Title\n\nSome **bold** text and `code`.\n\n- one\n- two\n");

        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<code>code</code>', $html);
        $this->assertStringContainsString('<li>two</li>', $html);
    }

    public function testRawHtmlIsStripped(): void
    {
        $html = JedHelper::renderMarkdown("Hello <script>alert(1)</script> <img src=x onerror=alert(2)> <b onmouseover=alert(3)>x</b>");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<b ', $html);
    }

    public function testUnsafeLinksAreDropped(): void
    {
        $html = JedHelper::renderMarkdown('[a](javascript:alert(1)) [b](data:text/html;base64,PHNjcmlwdD4=) [c](https://example.test)');

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data:text', $html);
        $this->assertStringContainsString('href="https://example.test"', $html);
    }

    public function testEmptyInputGivesNothing(): void
    {
        $this->assertSame('', JedHelper::renderMarkdown(null));
        $this->assertSame('', JedHelper::renderMarkdown("  \n"));
    }
}
