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
 * The only way this component may fetch a URL a user supplied.
 *
 * Joomla's own HTTP client is not used here, and that is the point of the class. It resolves the
 * hostname itself, inside the transport, at the moment it connects - so a check performed before
 * calling it is a check of a *different* lookup than the one that opens the socket. That gap is
 * DNS rebinding: a record with a one-second TTL answers `93.184.216.34` when the guard asks and
 * `169.254.169.254` a moment later when curl asks.
 *
 * This class closes it by resolving once, vetting every answer through {@see IpGuard}, and then
 * pinning the connection to an address it already approved via `CURLOPT_RESOLVE`. curl then does
 * no lookup of its own, so there is no second answer to differ from the first.
 *
 * The other three guards:
 *
 *  - **Redirects are followed by hand.** `CURLOPT_FOLLOWLOCATION` would resolve each hop inside
 *    curl, unvetted, which makes a public URL that 302s to `127.0.0.1` a complete bypass. Each
 *    hop here goes back through the same checks as the first request.
 *  - **The body is capped while it arrives**, in the write callback, not afterwards. A response
 *    that is a terabyte long is a denial of service against the JED, and `CURLOPT_MAXFILESIZE`
 *    only believes the `Content-Length` header.
 *  - **Timeouts on connect and on the whole exchange**, so a target that accepts the connection
 *    and then says nothing cannot hold a PHP worker open.
 *
 * @since 4.0.0
 */
class SafeHttpFetcher
{
    /**
     * Seconds to wait for the connection, and for the whole exchange.
     *
     * @since 4.0.0
     */
    public const CONNECT_TIMEOUT = 5;
    public const TOTAL_TIMEOUT   = 12;

    /**
     * How much of a response is read before the transfer is aborted.
     *
     * Enough for any update or changelog XML; far short of anything that could exhaust memory.
     *
     * @since 4.0.0
     */
    public const MAX_BYTES = 512000;

    /**
     * How many hops are followed before giving up.
     *
     * @since 4.0.0
     */
    public const MAX_REDIRECTS = 5;

    /**
     * The JED identifies itself. A directory that checks a developer's server anonymously gives
     * them no way to recognise the traffic, allow-list it, or complain about it.
     *
     * @since 4.0.0
     */
    public const USER_AGENT = 'JoomlaExtensionsDirectory/4.1 (+https://extensions.joomla.org/; link checker)';

    /**
     * Fetch a URL, or refuse to.
     *
     * @param string $url    The URL, which has already passed {@see UrlFormat::check()}.
     * @param bool   $bodyToo Whether to download the body; false issues a HEAD request.
     *
     * @return FetchResult
     *
     * @since 4.0.0
     */
    public function fetch(string $url, bool $bodyToo = true): FetchResult
    {
        $seen      = [];
        $redirects = [];
        $current   = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            // Every hop is a new, untrusted URL - including the first. A redirect target gets
            // exactly the same treatment as something typed into the form.
            if (UrlFormat::check($current) !== []) {
                return new FetchResult(true, 'format', 0, '', '', 0, $redirects, $current);
            }

            $key = strtolower($current);

            if (isset($seen[$key])) {
                return new FetchResult(true, 'redirect_loop', 0, '', '', 0, $redirects, $current);
            }

            $seen[$key] = true;

            $host = (string) parse_url($current, PHP_URL_HOST);
            $dns  = IpGuard::resolve($host);

            if ($dns['addresses'] === []) {
                // "Some answers were blocked" and "nothing resolved" are different failures and
                // the developer needs to be told which: one is a mistake in their DNS, the other
                // is a URL pointing into private space.
                return new FetchResult(
                    true,
                    $dns['blocked'] === [] ? 'dns' : 'private_address',
                    0,
                    '',
                    '',
                    0,
                    $redirects,
                    $current
                );
            }

            $response = $this->request($current, $dns['addresses'][0], $bodyToo);

            if ($response['errno'] !== 0) {
                return new FetchResult(false, $this->curlReason($response['errno']), 0, '', '', 0, $redirects, $current);
            }

            $status = (int) $response['status'];

            if ($status < 300 || $status > 399 || $response['location'] === '') {
                return new FetchResult(
                    false,
                    '',
                    $status,
                    $response['body'],
                    $response['contentType'],
                    $response['size'],
                    $redirects,
                    $current
                );
            }

            $current     = $this->absoluteUrl($current, $response['location']);
            $redirects[] = $current;
        }

        return new FetchResult(true, 'too_many_redirects', 0, '', '', 0, $redirects, $current);
    }

    /**
     * One request, to one address, with no redirect following and no DNS of curl's own.
     *
     * @param string $url     The URL to request.
     * @param string $ip      The vetted address to connect to.
     * @param bool   $bodyToo Whether to download the body.
     *
     * @return array{errno: int, status: int, body: string, contentType: string, size: int, location: string}
     *
     * @since 4.0.0
     */
    protected function request(string $url, string $ip, bool $bodyToo): array
    {
        $parts  = parse_url($url);
        $host   = (string) ($parts['host'] ?? '');
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $port   = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        $body     = '';
        $overflow = false;

        $ch = curl_init($url);

        // curl speaks a dozen protocols. Without this, a redirect into one of the others would
        // be honoured - which is how `file://` gets reached from an `https://` starting point.
        // The string form arrived in curl 7.85; the bitmask is the same restriction for older
        // builds, and one of the two is always available.
        if (\defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($ch, \CURLOPT_PROTOCOLS_STR, 'http,https');
        } else {
            curl_setopt($ch, \CURLOPT_PROTOCOLS, \CURLPROTO_HTTP | \CURLPROTO_HTTPS);
        }

        curl_setopt_array($ch, [
            // The pin. curl connects to this address and performs no lookup, so the address the
            // guard approved is the address the socket goes to.
            CURLOPT_RESOLVE         => [$host . ':' . $port . ':' . $ip],
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_RETURNTRANSFER  => false,
            CURLOPT_HEADER          => false,
            CURLOPT_NOBODY          => !$bodyToo,
            CURLOPT_CONNECTTIMEOUT  => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT         => self::TOTAL_TIMEOUT,
            CURLOPT_USERAGENT       => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
            CURLOPT_ACCEPT_ENCODING => '',
            CURLOPT_WRITEFUNCTION   => static function ($handle, string $chunk) use (&$body, &$overflow) {
                $length = \strlen($chunk);

                if (\strlen($body) + $length > self::MAX_BYTES) {
                    $body .= substr($chunk, 0, max(0, self::MAX_BYTES - \strlen($body)));
                    $overflow = true;

                    // Returning less than was handed in aborts the transfer. This is the size cap
                    // that works regardless of what Content-Length claimed.
                    return 0;
                }

                $body .= $chunk;

                return $length;
            },
        ]);

        curl_exec($ch);

        $errno    = curl_errno($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type     = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);

        curl_close($ch);

        // Aborting on the size cap is our own doing, not a failure of the other server.
        if ($overflow && \in_array($errno, [CURLE_WRITE_ERROR, CURLE_ABORTED_BY_CALLBACK], true)) {
            $errno = 0;
        }

        return [
            'errno'       => $errno,
            'status'      => $status,
            'body'        => $body,
            'contentType' => strtolower(trim(explode(';', $type)[0])),
            'size'        => \strlen($body),
            'location'    => $location,
        ];
    }

    /**
     * Resolve a `Location` header against the URL it came from.
     *
     * A relative redirect is normal and has to be followed; it also cannot change the host, so it
     * is the safe half of this. An absolute one goes through the full check again on the next
     * pass of the loop.
     *
     * @param string $base     The URL that produced the redirect.
     * @param string $location The Location header value.
     *
     * @return string
     *
     * @since 4.0.0
     */
    protected function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $location)) {
            return $location;
        }

        $parts  = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = (string) ($parts['path'] ?? '/');
        $path = substr($path, 0, strrpos($path, '/') ?: 0);

        return $origin . $path . '/' . $location;
    }

    /**
     * Turn a curl error number into a language-key suffix.
     *
     * Grouped rather than passed through: the developer needs to know whether their server was
     * unreachable, too slow, or presenting a bad certificate. curl's 90-odd codes do not help
     * them, and its English error strings are not translatable.
     *
     * @param int $errno The curl error number.
     *
     * @return string
     *
     * @since 4.0.0
     */
    protected function curlReason(int $errno): string
    {
        // 60 is CURLE_PEER_FAILED_VERIFICATION - the certificate one, and the one developers hit
        // most. PHP does not expose a constant for it on every build, so it is spelled out.
        return match ($errno) {
            CURLE_OPERATION_TIMEDOUT   => 'timeout',
            CURLE_COULDNT_CONNECT      => 'unreachable',
            CURLE_COULDNT_RESOLVE_HOST => 'dns',
            CURLE_SSL_CONNECT_ERROR,
            CURLE_SSL_CACERT_BADFILE,
            60      => 'tls',
            default => 'failed',
        };
    }
}
