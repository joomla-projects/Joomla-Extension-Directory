<?php

/**
 * @package JED
 *
 * @subpackage Apps
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Apps\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The HTTP side of a response: what a CDN and a browser may keep, and for how long.
 *
 * @since 4.0.0
 */
final class AppsHeaders
{
    /** How long a CDN may serve a stale copy while it fetches a fresh one, or if the origin is down. */
    public const STALE_SECONDS = 86400;

    /**
     * @param AppsResult $result         The answer.
     * @param int        $edgeTtl        s-maxage for browsing, seconds.
     * @param int        $edgeTtlSearch  s-maxage for search, seconds.
     * @param string     $ifNoneMatch    The request's If-None-Match header.
     *
     * @return array{status:int, headers:array<string,string>, body:string}
     *
     * @since 4.0.0
     */
    public static function build(AppsResult $result, int $edgeTtl, int $edgeTtlSearch, string $ifNoneMatch = ''): array
    {
        $headers = [
            'Content-Type'                => 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Vary'                        => 'Accept-Encoding',
            'X-Content-Type-Options'      => 'nosniff',
        ];

        if ($result->kind === AppsResult::KIND_ERROR) {
            $headers['Cache-Control'] = 'public, max-age=60, s-maxage=60';

            return ['status' => $result->status, 'headers' => $headers, 'body' => $result->body];
        }

        $edge = $result->kind === AppsResult::KIND_SEARCH ? $edgeTtlSearch : $edgeTtl;
        $etag = '"' . md5($result->body) . '"';

        $headers['Cache-Control'] = sprintf(
            'public, max-age=60, s-maxage=%d, stale-while-revalidate=%d, stale-if-error=%d',
            $edge,
            self::STALE_SECONDS,
            self::STALE_SECONDS
        );
        $headers['ETag'] = $etag;

        if ($ifNoneMatch !== '' && in_array($etag, array_map('trim', explode(',', $ifNoneMatch)), true)) {
            return ['status' => 304, 'headers' => $headers, 'body' => ''];
        }

        return ['status' => $result->status, 'headers' => $headers, 'body' => $result->body];
    }
}
