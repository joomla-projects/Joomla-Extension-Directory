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

/**
 * Checks a ping from the Identity Portal before anything acts on it.
 *
 * The portal pings a connected site with a task and a GUID. The JED has not been told how the
 * portal signs its pings yet, so this is the scheme the JED proposes: the body is JSON, and two
 * headers carry a Unix timestamp and `hash_hmac('sha256', timestamp . '.' . body, secret)` in hex.
 * The timestamp has to be recent, which stops a captured ping being played back later. If the
 * portal already signs its pings for the JVP some other way, only this class changes.
 *
 * It decides nothing but whether to believe the request, and has no database or request access,
 * so every refusal can be tested.
 *
 * @since 4.0.0
 */
final class PingVerifier
{
    /**
     * How far the timestamp may be from now, in seconds.
     *
     * @since 4.0.0
     */
    public const TOLERANCE = 300;

    /**
     * The shortest secret accepted, in characters. A shared secret is a key: anything shorter than
     * this can be guessed from a captured ping.
     *
     * @since 4.0.0
     */
    public const MIN_SECRET = 32;

    /**
     * Compute the signature the portal is expected to send.
     *
     * @param string $secret    The shared secret.
     * @param string $timestamp The timestamp header, as sent.
     * @param string $body      The raw request body.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /**
     * Check a request.
     *
     * @param string $secret    The shared secret. Empty means pings are switched off.
     * @param string $timestamp The timestamp header.
     * @param string $signature The signature header.
     * @param string $body      The raw request body.
     * @param int    $now       The current Unix time.
     *
     * @return string|null Null when the request is genuine, otherwise the reason it is not.
     *
     * @since 4.0.0
     */
    public static function check(string $secret, string $timestamp, string $signature, string $body, int $now): ?string
    {
        if ($secret === '') {
            return 'pings are not enabled';
        }

        if (\strlen($secret) < self::MIN_SECRET) {
            return 'the secret is too short';
        }

        if (!ctype_digit($timestamp) || abs($now - (int) $timestamp) > self::TOLERANCE) {
            return 'the timestamp is missing or out of date';
        }

        if (!hash_equals(self::sign($secret, $timestamp, $body), strtolower($signature))) {
            return 'the signature does not match';
        }

        return null;
    }

    /**
     * Read the body of a verified ping.
     *
     * @param string $body The raw request body.
     *
     * @return array{task: string, guid: string}|null Null when the body is not a ping this site understands.
     *
     * @since 4.0.0
     */
    public static function parse(string $body): ?array
    {
        $data = json_decode($body, true);

        if (!\is_array($data) || !\is_string($data['task'] ?? null) || !\is_string($data['guid'] ?? null)) {
            return null;
        }

        $task = strtolower(trim($data['task']));
        $guid = Guid::normalise($data['guid']);

        if ($task === '' || ($guid !== '' && !Guid::isValid($guid))) {
            return null;
        }

        return ['task' => $task, 'guid' => $guid];
    }
}
