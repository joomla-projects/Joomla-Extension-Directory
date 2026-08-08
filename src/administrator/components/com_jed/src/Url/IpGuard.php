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
 * Decides whether an IP address is one this server is allowed to connect to.
 *
 * The JED fetches URLs that developers type into a form. That is a server-side request forgery
 * primitive by construction: whoever controls the URL controls where the JED's own server sends
 * a request from inside whatever network it is standing in. The interesting targets are not on
 * the public internet - they are `169.254.169.254` for cloud instance credentials, the database
 * on `10.x`, an admin panel on `127.0.0.1` that trusts localhost.
 *
 * **This class only ever answers about a resolved IP address, never about a hostname.** A
 * hostname filter is not a control: `localtest.me` has a public DNS record pointing at
 * `127.0.0.1`, and an attacker who owns a domain can point it wherever they like, change it
 * between two lookups, or return several answers. The fetcher therefore resolves first, checks
 * every answer here, and then connects to the address it checked - see {@see SafeHttpFetcher}.
 *
 * Pure and static, with no network of its own, because this is the class whose failure is
 * silent. `P1-08` requires the unit tests to be a table of hostile inputs - decimal notation,
 * IPv4-mapped IPv6, redirect chains - and that table is only meaningful against a function that
 * can be called directly.
 *
 * @since 4.1.0
 */
final class IpGuard
{
    /**
     * Blocked IPv4 ranges, as CIDR.
     *
     * Wider than "private": every range IANA marks special is here, because "is it routable on
     * the public internet" is the question, not "is it RFC1918". `100.64/10` is carrier-grade
     * NAT, `192.0.0/24` is IETF protocol assignments, `198.18/15` is benchmarking.
     *
     * @since 4.1.0
     */
    private const BLOCKED_V4 = [
        '0.0.0.0/8',          // "this network"
        '10.0.0.0/8',         // private
        '100.64.0.0/10',      // carrier-grade NAT
        '127.0.0.0/8',        // loopback
        '169.254.0.0/16',     // link-local - cloud instance metadata lives at 169.254.169.254
        '172.16.0.0/12',      // private
        '192.0.0.0/24',       // IETF protocol assignments
        '192.0.2.0/24',       // TEST-NET-1
        '192.88.99.0/24',     // 6to4 relay anycast
        '192.168.0.0/16',     // private
        '198.18.0.0/15',      // benchmarking
        '198.51.100.0/24',    // TEST-NET-2
        '203.0.113.0/24',     // TEST-NET-3
        '224.0.0.0/4',        // multicast
        '240.0.0.0/4',        // reserved, includes 255.255.255.255
    ];

    /**
     * Blocked IPv6 ranges, as CIDR.
     *
     * IPv4-mapped (`::ffff:0:0/96`) is in the list, but it is also unwrapped before the check -
     * see {@see self::isBlocked()}. Blocking the whole mapped range would be simpler and wrong:
     * `::ffff:93.184.216.34` is a legitimate way to reach a public address.
     *
     * @since 4.1.0
     */
    private const BLOCKED_V6 = [
        '::/128',             // unspecified
        '::1/128',            // loopback
        '64:ff9b::/96',       // NAT64 - reaches IPv4 space, including private IPv4
        '100::/64',           // discard-only
        '2001:db8::/32',      // documentation
        'fc00::/7',           // unique local
        'fe80::/10',          // link-local
        'ff00::/8',           // multicast
    ];

    /**
     * Whether this address must not be connected to.
     *
     * Anything that is not a valid IP address is blocked. This function decides whether to open a
     * socket, so "I do not understand this input" has exactly one safe answer.
     *
     * @param string $ip The address, as returned by DNS resolution.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public static function isBlocked(string $ip): bool
    {
        $ip = trim($ip);

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        // ::ffff:127.0.0.1 is 127.0.0.1 wearing a hat. Unwrap it and judge the IPv4 address,
        // rather than letting a v4 target through because it arrived in v6 notation.
        $unwrapped = self::unwrapMappedIpv4($ip);

        if ($unwrapped !== null) {
            return self::isBlockedV4($unwrapped);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::isBlockedV4($ip);
        }

        return self::isBlockedV6($ip);
    }

    /**
     * The IPv4 address inside an IPv4-mapped or IPv4-compatible IPv6 address.
     *
     * @param string $ip A valid IP address.
     *
     * @return string|null  The IPv4 address, or null when this is not a mapped address.
     *
     * @since 4.1.0
     */
    private static function unwrapMappedIpv4(string $ip): ?string
    {
        $packed = @inet_pton($ip);

        if ($packed === false || \strlen($packed) !== 16) {
            return null;
        }

        // ::ffff:a.b.c.d  (mapped) and ::a.b.c.d (deprecated "compatible") both put the IPv4
        // address in the last four bytes.
        $prefix = substr($packed, 0, 12);

        if ($prefix === str_repeat("\0", 10) . "\xff\xff" || $prefix === str_repeat("\0", 12)) {
            return inet_ntop(substr($packed, 12));
        }

        return null;
    }

    /**
     * @param string $ip A valid IPv4 address.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    private static function isBlockedV4(string $ip): bool
    {
        $long = ip2long($ip);

        if ($long === false) {
            return true;
        }

        foreach (self::BLOCKED_V4 as $cidr) {
            [$network, $bits] = explode('/', $cidr);
            $mask             = $bits === '0' ? 0 : (-1 << (32 - (int) $bits)) & 0xFFFFFFFF;

            if ((($long & $mask) & 0xFFFFFFFF) === (ip2long($network) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $ip A valid IPv6 address.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    private static function isBlockedV6(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false || \strlen($packed) !== 16) {
            return true;
        }

        foreach (self::BLOCKED_V6 as $cidr) {
            [$network, $bits] = explode('/', $cidr);
            $networkPacked    = @inet_pton($network);

            if ($networkPacked === false || !self::sharesPrefix($packed, $networkPacked, (int) $bits)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Whether two packed addresses agree on their first `$bits` bits.
     *
     * @param string $a    16 raw bytes.
     * @param string $b    16 raw bytes.
     * @param int    $bits Prefix length.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    private static function sharesPrefix(string $a, string $b, int $bits): bool
    {
        $wholeBytes = intdiv($bits, 8);

        if ($wholeBytes > 0 && strncmp($a, $b, $wholeBytes) !== 0) {
            return false;
        }

        $remaining = $bits % 8;

        if ($remaining === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining)) & 0xFF;

        return (\ord($a[$wholeBytes]) & $mask) === (\ord($b[$wholeBytes]) & $mask);
    }

    /**
     * Resolve a hostname to every address it answers with, keeping only the usable ones.
     *
     * Both families are resolved, because a host that has an AAAA record pointing into unique
     * local space and an A record on the public internet must not be reachable through the
     * former just because the latter looked fine.
     *
     * @param string $host The hostname.
     *
     * @return array{addresses: string[], blocked: string[]}  Addresses that passed, and those
     *                                                        that did not - the caller needs both,
     *                                                        because "some answers were blocked"
     *                                                        is not the same as "none resolved".
     *
     * @since 4.1.0
     */
    public static function resolve(string $host): array
    {
        $addresses = [];
        $blocked   = [];

        foreach (['A', 'AAAA'] as $type) {
            $records = @dns_get_record($host, $type === 'A' ? \DNS_A : \DNS_AAAA) ?: [];

            foreach ($records as $record) {
                $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');

                if ($ip === '') {
                    continue;
                }

                if (self::isBlocked($ip)) {
                    $blocked[] = $ip;
                    continue;
                }

                $addresses[] = $ip;
            }
        }

        return [
            'addresses' => array_values(array_unique($addresses)),
            'blocked'   => array_values(array_unique($blocked)),
        ];
    }
}
