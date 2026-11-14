<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Feed;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The outbound requests of the current feed step, for the Feed Runs page: what was asked of
 * cvelistV5 and the NVD, what came back and how long it took. The clients record; the runner
 * hands the list to the page with each step's report, where the activity log shows it.
 *
 * @since 4.0.0
 */
final class FeedTrace
{
    /**
     * Requests kept per step.
     *
     * @since 4.0.0
     */
    private const MAX = 100;

    /**
     * @var list<array{method: string, url: string, range: string, status: int, bytes: int, ms: int, error: string}>
     *
     * @since 4.0.0
     */
    private static array $requests = [];

    /**
     * @since 4.0.0
     */
    private static int $dropped = 0;

    /**
     * @param string $method HTTP method.
     * @param string $url    The URL (no credentials: API keys travel in headers).
     * @param int    $status HTTP status, 0 when no response came back.
     * @param int    $bytes  Body size.
     * @param float  $start  microtime(true) when the request began.
     * @param string $range  The Range asked for, if any.
     * @param string $error  What went wrong, when no response came back.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public static function record(string $method, string $url, int $status, int $bytes, float $start, string $range = '', string $error = ''): void
    {
        if (\count(self::$requests) >= self::MAX) {
            self::$dropped++;

            return;
        }

        self::$requests[] = [
            'method' => $method,
            'url'    => $url,
            'range'  => $range,
            'status' => $status,
            'bytes'  => $bytes,
            'ms'     => (int) round((microtime(true) - $start) * 1000),
            'error'  => mb_substr($error, 0, 300),
        ];
    }

    /**
     * The requests since the last call, and forget them.
     *
     * @return array{requests: list<array>, dropped: int}
     *
     * @since 4.0.0
     */
    public static function take(): array
    {
        $taken = ['requests' => self::$requests, 'dropped' => self::$dropped];

        self::$requests = [];
        self::$dropped  = 0;

        return $taken;
    }
}
