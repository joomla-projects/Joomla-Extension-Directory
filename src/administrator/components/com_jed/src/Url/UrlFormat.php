<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Url;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The format rules for every URL the JED stores — defined once, here.
 *
 * `P1-08` makes this a requirement rather than a preference: layer 1 runs in the browser and
 * layer 3 runs on save, and if the two ever disagree the developer is told their URL is wrong by
 * a server that accepted it a moment earlier, with no way to find out which rule they broke.
 * So the rules live in one class, the field publishes them to the browser as `data-` attributes
 * (see {@see self::toDataAttributes()}), and the JavaScript reads them from there instead of
 * restating them.
 *
 * Everything in here is pure: strings in, error keys out, no network and no application. That is
 * what lets the hostile-input table in the unit tests be the real check rather than a smoke test.
 *
 * Format is the only thing that blocks a save. Whether the URL *answers* is a different question,
 * decided by a validator and reported as a notice - see {@see UrlValidatorInterface}.
 *
 * @since 4.1.0
 */
final class UrlFormat
{
    /**
     * The only schemes the JED stores.
     *
     * Not a preference: every other scheme either cannot be fetched (`mailto:`, `tel:`) or is an
     * SSRF primitive (`file:`, `gopher:`, `dict:`, `ftp:`). The validators fetch what is stored,
     * so the storage rule and the fetch rule have to be the same one.
     *
     * @since 4.1.0
     */
    public const SCHEMES = ['http', 'https'];

    /**
     * `varchar(255)` is what every URL column in the schema is.
     *
     * @since 4.1.0
     */
    public const MAX_LENGTH = 255;

    /**
     * Error keys, in the order they are reported. One rule, one key, one language string.
     *
     * @since 4.1.0
     */
    public const ERROR_EMPTY       = 'empty';
    public const ERROR_LENGTH      = 'length';
    public const ERROR_WHITESPACE  = 'whitespace';
    public const ERROR_CONTROL     = 'control';
    public const ERROR_SCHEME      = 'scheme';
    public const ERROR_NO_SCHEME   = 'noscheme';
    public const ERROR_CREDENTIALS = 'credentials';
    public const ERROR_HOST        = 'host';
    public const ERROR_MALFORMED   = 'malformed';

    /**
     * Check a URL against the format rules.
     *
     * @param string|null $url      The value as the developer typed it.
     * @param bool        $required Whether an empty value is itself an error.
     *
     * @return string[]  The error keys, empty when the value is acceptable.
     *
     * @since 4.1.0
     */
    public static function check(?string $url, bool $required = false): array
    {
        $raw = (string) $url;

        if (trim($raw) === '') {
            return $required ? [self::ERROR_EMPTY] : [];
        }

        $errors = [];

        // Checked on the raw value, before trimming: a URL that only works once something has
        // been silently removed from it is a URL the developer should be shown, not one the JED
        // should quietly repair (see the note on normalisation below).
        if (preg_match('/[\x00-\x1F\x7F]/', $raw)) {
            $errors[] = self::ERROR_CONTROL;
        }

        if (preg_match('/\s/u', trim($raw))) {
            $errors[] = self::ERROR_WHITESPACE;
        }

        $value = trim($raw);

        if (mb_strlen($value) > self::MAX_LENGTH) {
            $errors[] = self::ERROR_LENGTH;
        }

        if (!preg_match('#^([a-z][a-z0-9+.\-]*):#i', $value, $schemeMatch)) {
            // No scheme at all. Separated from "wrong scheme" because the two need different
            // advice: this one has a one-click fix and the UI offers it as a suggestion.
            $errors[] = self::ERROR_NO_SCHEME;

            return array_values(array_unique($errors));
        }

        if (!\in_array(strtolower($schemeMatch[1]), self::SCHEMES, true)) {
            $errors[] = self::ERROR_SCHEME;

            return array_values(array_unique($errors));
        }

        $parts = parse_url($value);

        if ($parts === false) {
            $errors[] = self::ERROR_MALFORMED;

            return array_values(array_unique($errors));
        }

        // A URL carrying credentials is either a mistake or an attempt to smuggle a different
        // host past a reader - "https://developer.example@127.0.0.1/" points at 127.0.0.1.
        if (isset($parts['user']) || isset($parts['pass'])) {
            $errors[] = self::ERROR_CREDENTIALS;
        }

        if (!self::isPlausibleHost((string) ($parts['host'] ?? ''))) {
            $errors[] = self::ERROR_HOST;
        }

        return array_values(array_unique($errors));
    }

    /**
     * Whether a host is one the JED is willing to store.
     *
     * A bare IP literal is refused here even though the IP guard would refuse it again later.
     * A public listing links to a developer's site by name; an address that resolves through no
     * DNS record is a support URL nobody can maintain, and refusing it at the format layer also
     * removes the whole decimal/octal/IPv6-literal notation family from what the fetcher ever
     * has to reason about.
     *
     * @param string $host The host component, already extracted.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public static function isPlausibleHost(string $host): bool
    {
        $host = trim($host);

        if ($host === '' || mb_strlen($host) > 253) {
            return false;
        }

        // [::1] and friends - parse_url keeps the brackets.
        if (str_starts_with($host, '[')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        // "0x7f.1", "2130706433", "017700000001" - IPv4 in disguise. Anything whose labels are
        // all numeric, or that is a single label, is not a domain name.
        $labels = explode('.', $host);

        if (\count($labels) < 2) {
            return false;
        }

        foreach ($labels as $label) {
            if ($label === '' || mb_strlen($label) > 63) {
                return false;
            }

            if (!preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/i', $label)) {
                return false;
            }
        }

        $tld = end($labels);

        // A TLD is letters. This is what rejects "192.168.1.1" written as a host as well as
        // "example.123".
        return (bool) preg_match('/^[a-z]{2,63}$/i', $tld);
    }

    /**
     * The gentle repairs the UI *suggests* — it never applies them by itself.
     *
     * `P1-08`: "Suggest a missing scheme - do not add it silently." A URL the developer did not
     * type is a URL nobody checked, and a listing whose support link the JED invented is worse
     * than one with an obvious mistake in it.
     *
     * @param string|null $url The value as typed.
     *
     * @return string|null  A corrected value to offer, or null when there is nothing to suggest.
     *
     * @since 4.1.0
     */
    public static function suggest(?string $url): ?string
    {
        $value = trim((string) $url);

        if ($value === '') {
            return null;
        }

        // Pasted out of Markdown, a mail client or a wiki: <https://x>, [text](https://x), "…".
        $value = preg_replace('/^[<\["\'(]+|[>\]"\')]+$/u', '', $value) ?? $value;
        $value = preg_replace('/^.*\]\(/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        if ($value === '') {
            return null;
        }

        // A missing scheme is the single most common shape, and https is the only sane guess.
        if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value) && self::isPlausibleHost(explode('/', $value)[0])) {
            $value = 'https://' . $value;
        }

        // http:// -> https:// is deliberately NOT suggested. Plenty of developer sites still
        // answer only on http, and a suggestion that breaks the link is worse than none.
        if ($value === trim((string) $url) || self::check($value) !== []) {
            return null;
        }

        return $value;
    }

    /**
     * The rules, in the shape the field hands to the browser.
     *
     * The JavaScript reads these instead of carrying its own copy, which is the whole point:
     * change a rule here and layer 1 changes with it.
     *
     * @return array<string, string>
     *
     * @since 4.1.0
     */
    public static function toDataAttributes(): array
    {
        return [
            'data-jedurl-schemes'   => implode(',', self::SCHEMES),
            'data-jedurl-maxlength' => (string) self::MAX_LENGTH,
        ];
    }
}
