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
 * What came back from a guarded fetch.
 *
 * `refused` is deliberately separate from `status === 0`. A request the guard declined to make
 * and a request that was made and failed are different facts: the first is about the URL the
 * developer typed, the second about somebody else's server having a bad day, and the UI has to
 * say different things about them.
 *
 * @since 4.1.0
 */
final class FetchResult
{
    /**
     * @param bool        $refused     True when the guard declined to make the request at all.
     * @param string      $reason      A language-key suffix explaining a refusal or a failure.
     * @param int         $status      The final HTTP status, 0 when there was never a response.
     * @param string      $body        The response body, truncated at the size cap.
     * @param string      $contentType The Content-Type header, lowercased, without parameters.
     * @param int         $size        Bytes received.
     * @param string[]    $redirects   Every URL in the chain after the first.
     * @param string|null $finalUrl    Where the chain ended.
     *
     * @since 4.1.0
     */
    public function __construct(
        public readonly bool $refused,
        public readonly string $reason = '',
        public readonly int $status = 0,
        public readonly string $body = '',
        public readonly string $contentType = '',
        public readonly int $size = 0,
        public readonly array $redirects = [],
        public readonly ?string $finalUrl = null
    ) {
    }

    /**
     * Whether the server answered with a success status.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function isOk(): bool
    {
        return !$this->refused && $this->status >= 200 && $this->status < 300;
    }
}
