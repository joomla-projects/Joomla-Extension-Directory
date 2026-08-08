<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Url;

use Jed\Component\Jed\Administrator\Url\UrlFormat;
use PHPUnit\Framework\TestCase;

/**
 * The format rules — the ones that actually block a save.
 *
 * These run in two places: in the browser while the developer types, and on the server when the
 * form is submitted. `P1-08` makes not letting the two drift apart a requirement, and this test
 * is what holds the shared definition still.
 *
 * @since 4.0.0
 */
final class UrlFormatTest extends TestCase
{
    /**
     * URLs the JED accepts.
     *
     * @return array<string, array{0: string}>
     *
     * @since 4.0.0
     */
    public static function acceptable(): array
    {
        return [
            'https'                    => ['https://example.com'],
            'http'                     => ['http://example.com'],
            'with a path'              => ['https://example.com/downloads/extension.zip'],
            'with a query'             => ['https://example.com/update.xml?ext=42&v=5'],
            'with a fragment'          => ['https://example.com/docs#install'],
            'with a port'              => ['https://example.com:8443/update.xml'],
            'a subdomain'              => ['https://updates.example.co.uk/list.xml'],
            'a hyphenated label'       => ['https://my-extension.example.com/'],
            'uppercase scheme'         => ['HTTPS://example.com/'],
            'a long TLD'               => ['https://example.software/'],
            'digits in a label'        => ['https://cdn2.example.com/'],
        ];
    }

    /**
     * @dataProvider acceptable
     *
     * @param string $url The URL.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testAcceptsUsableUrls(string $url): void
    {
        $this->assertSame([], UrlFormat::check($url), $url . ' should be accepted');
    }

    /**
     * URLs the JED refuses, with the rule each one breaks.
     *
     * @return array<string, array{0: string|null, 1: string}>
     *
     * @since 4.0.0
     */
    public static function rejected(): array
    {
        return [
            // Schemes. Everything here is either unfetchable or an SSRF primitive.
            'file'                  => ['file:///etc/passwd', UrlFormat::ERROR_SCHEME],
            'gopher'                => ['gopher://example.com/', UrlFormat::ERROR_SCHEME],
            'ftp'                   => ['ftp://example.com/x.zip', UrlFormat::ERROR_SCHEME],
            'dict'                  => ['dict://example.com:11211/', UrlFormat::ERROR_SCHEME],
            'javascript'            => ['javascript:alert(1)', UrlFormat::ERROR_SCHEME],
            'data'                  => ['data:text/html,<x>', UrlFormat::ERROR_SCHEME],
            'mailto'                => ['mailto:dev@example.com', UrlFormat::ERROR_SCHEME],
            'protocol-relative'     => ['//example.com/x', UrlFormat::ERROR_NO_SCHEME],
            'no scheme at all'      => ['example.com/downloads', UrlFormat::ERROR_NO_SCHEME],

            // Credentials. "https://example.com@127.0.0.1/" points at 127.0.0.1, and a reader
            // skimming the listing sees example.com.
            'credentials'           => ['https://user:pass@example.com/', UrlFormat::ERROR_CREDENTIALS],
            'a username only'       => ['https://example.com@evil.example.org/', UrlFormat::ERROR_CREDENTIALS],

            // Hosts that are not names.
            'an IPv4 literal'       => ['https://127.0.0.1/', UrlFormat::ERROR_HOST],
            'a public IPv4 literal' => ['https://93.184.216.34/', UrlFormat::ERROR_HOST],
            'an IPv6 literal'       => ['https://[::1]/', UrlFormat::ERROR_HOST],
            'decimal notation'      => ['https://2130706433/', UrlFormat::ERROR_HOST],
            'octal notation'        => ['https://0177.0.0.1/', UrlFormat::ERROR_HOST],
            'hex notation'          => ['https://0x7f000001/', UrlFormat::ERROR_HOST],
            'a single label'        => ['https://localhost/', UrlFormat::ERROR_HOST],
            'a numeric TLD'         => ['https://example.123/', UrlFormat::ERROR_HOST],
            'an empty label'        => ['https://example..com/', UrlFormat::ERROR_HOST],
            'a label starting with a hyphen' => ['https://-example.com/', UrlFormat::ERROR_HOST],
            'an underscore'         => ['https://my_host.example.com/', UrlFormat::ERROR_HOST],

            // Whitespace and control characters - both are how a second URL, or a header, gets
            // smuggled into a single field.
            'an inner space'        => ['https://example.com/my file.zip', UrlFormat::ERROR_WHITESPACE],
            'a tab'                 => ["https://example.com/a\tb", UrlFormat::ERROR_WHITESPACE],
            'a newline'             => ["https://example.com/a\nb", UrlFormat::ERROR_CONTROL],
            'a NUL byte'            => ["https://example.com/a\0b", UrlFormat::ERROR_CONTROL],
            'a carriage return'     => ["https://example.com/\r\nHost: x", UrlFormat::ERROR_CONTROL],
        ];
    }

    /**
     * @dataProvider rejected
     *
     * @param string $url      The URL.
     * @param string $expected The rule it must break.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testRejectsWithTheRightReason(string $url, string $expected): void
    {
        $errors = UrlFormat::check($url);

        $this->assertNotEmpty($errors, var_export($url, true) . ' should be rejected');
        $this->assertContains(
            $expected,
            $errors,
            var_export($url, true) . ' should be rejected as "' . $expected . '", got: ' . implode(', ', $errors)
        );
    }

    /**
     * An empty value is only an error where the field is required.
     *
     * Seven of the eight URL columns are optional, and a format rule that refuses "nothing"
     * would make them all mandatory by accident.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testEmptyIsOnlyAnErrorWhenRequired(): void
    {
        foreach ([null, '', '   '] as $value) {
            $this->assertSame([], UrlFormat::check($value), 'optional field');
            $this->assertSame([UrlFormat::ERROR_EMPTY], UrlFormat::check($value, true), 'required field');
        }
    }

    /**
     * Length is bounded by the column, not by taste.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testRefusesWhatTheColumnCannotHold(): void
    {
        $fits = 'https://example.com/' . str_repeat('a', UrlFormat::MAX_LENGTH - 20);
        $over = $fits . 'a';

        $this->assertSame(UrlFormat::MAX_LENGTH, \strlen($fits));
        $this->assertSame([], UrlFormat::check($fits));
        $this->assertContains(UrlFormat::ERROR_LENGTH, UrlFormat::check($over));
    }

    /**
     * Surrounding whitespace is not an error — it is what pasting does.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testToleratesSurroundingWhitespace(): void
    {
        $this->assertSame([], UrlFormat::check("  https://example.com/  "));
    }

    /**
     * The suggestions the UI offers, and the ones it must not.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testSuggestsRepairsWithoutApplyingThem(): void
    {
        // The common shapes: a missing scheme, and a URL pasted out of Markdown or a mail client.
        $this->assertSame('https://example.com/docs', UrlFormat::suggest('example.com/docs'));
        $this->assertSame('https://example.com/docs', UrlFormat::suggest('<https://example.com/docs>'));
        $this->assertSame('https://example.com/docs', UrlFormat::suggest('[Docs](https://example.com/docs)'));
        $this->assertSame('https://example.com/docs', UrlFormat::suggest('https://example.com/ docs'));

        // Nothing to suggest for something already valid.
        $this->assertNull(UrlFormat::suggest('https://example.com/docs'));

        // http is left alone: plenty of developer sites still answer only on http, and a
        // suggestion that breaks the link is worse than no suggestion.
        $this->assertNull(UrlFormat::suggest('http://example.com/docs'));

        // No suggestion can rescue these, and offering a plausible-looking one would be worse
        // than saying nothing.
        $this->assertNull(UrlFormat::suggest('file:///etc/passwd'));
        $this->assertNull(UrlFormat::suggest('https://127.0.0.1/'));
        $this->assertNull(UrlFormat::suggest('not a url at all'));
        $this->assertNull(UrlFormat::suggest(''));
        $this->assertNull(UrlFormat::suggest(null));
    }

    /**
     * What the browser is handed is what the server enforces.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testPublishesItsRulesToTheClient(): void
    {
        $data = UrlFormat::toDataAttributes();

        $this->assertSame(implode(',', UrlFormat::SCHEMES), $data['data-jedurl-schemes']);
        $this->assertSame((string) UrlFormat::MAX_LENGTH, $data['data-jedurl-maxlength']);
    }
}
