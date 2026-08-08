<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Url;

use Jed\Component\Jed\Administrator\Url\IpGuard;
use PHPUnit\Framework\TestCase;

/**
 * The SSRF address guard.
 *
 * `P1-08` asks for "a table of hostile inputs that must all be rejected", and this is it. The
 * reason it is spelled out at this length rather than sampled is that a hole here is silent:
 * the endpoint keeps working, the developer sees a normal result, and the only sign that
 * `169.254.169.254` was reachable is in somebody else's logs.
 *
 * Every case is named after the trick it represents, so a failure says which class of bypass
 * reopened rather than just which string.
 *
 * @since 4.0.0
 */
final class IpGuardTest extends TestCase
{
    /**
     * Addresses this server must refuse to connect to.
     *
     * @return array<string, array{0: string}>
     *
     * @since 4.0.0
     */
    public static function blocked(): array
    {
        return [
            // Loopback, in the notations that all mean the same socket.
            'loopback'                     => ['127.0.0.1'],
            'loopback, other host in /8'   => ['127.1.2.3'],
            'loopback, v6'                 => ['::1'],
            'loopback, v4-mapped v6'       => ['::ffff:127.0.0.1'],
            'loopback, v4-compatible v6'   => ['::127.0.0.1'],
            'loopback, v4-mapped in hex'   => ['::ffff:7f00:1'],

            // Cloud instance metadata. The single most valuable SSRF target there is.
            'AWS/GCP/Azure metadata'       => ['169.254.169.254'],
            'link-local, anything else'    => ['169.254.1.1'],
            'metadata over v4-mapped v6'   => ['::ffff:169.254.169.254'],

            // RFC1918, the network the application server is actually standing in.
            'private 10/8'                 => ['10.0.0.1'],
            'private 172.16/12 low'        => ['172.16.0.1'],
            'private 172.16/12 high'       => ['172.31.255.254'],
            'private 192.168/16'           => ['192.168.1.1'],
            'private 10/8 via v6 mapping'  => ['::ffff:10.0.0.1'],

            // Unspecified and broadcast - both reach somewhere on some stacks.
            'unspecified v4'               => ['0.0.0.0'],
            'this-network /8'              => ['0.1.2.3'],
            'unspecified v6'               => ['::'],
            'broadcast'                    => ['255.255.255.255'],

            // Ranges people forget.
            'carrier-grade NAT'            => ['100.64.0.1'],
            'IETF protocol assignments'    => ['192.0.0.1'],
            'benchmarking'                 => ['198.18.0.1'],
            'TEST-NET-1'                   => ['192.0.2.1'],
            'TEST-NET-2'                   => ['198.51.100.1'],
            'TEST-NET-3'                   => ['203.0.113.1'],
            '6to4 relay anycast'           => ['192.88.99.1'],
            'multicast v4'                 => ['239.255.255.250'],
            'reserved 240/4'               => ['240.0.0.1'],

            // IPv6 families.
            'unique local'                 => ['fc00::1'],
            'unique local, fd'             => ['fd12:3456:789a::1'],
            'link-local v6'                => ['fe80::1'],
            'multicast v6'                 => ['ff02::1'],
            'documentation v6'             => ['2001:db8::1'],
            'NAT64 well-known prefix'      => ['64:ff9b::7f00:1'],
            'discard-only'                 => ['100::1'],

            // Not an address at all. The guard decides whether to open a socket, so anything it
            // does not understand has one safe answer.
            'empty'                        => [''],
            'whitespace'                   => ['   '],
            'a hostname'                   => ['localhost'],
            'a domain'                     => ['example.com'],
            'decimal notation for 127.0.0.1' => ['2130706433'],
            'octal notation'               => ['0177.0.0.1'],
            'hex notation'                 => ['0x7f000001'],
            'short form 127.1'             => ['127.1'],
            'trailing dot'                 => ['127.0.0.1.'],
            'port appended'                => ['127.0.0.1:80'],
            'CIDR'                         => ['10.0.0.0/8'],
            'nonsense'                     => ['not an ip'],
            'a URL'                        => ['http://127.0.0.1/'],
            'v6 in brackets'               => ['[::1]'],
        ];
    }

    /**
     * @dataProvider blocked
     *
     * @param string $ip The address.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testRefusesAddressesThatMustNotBeReached(string $ip): void
    {
        $this->assertTrue(
            IpGuard::isBlocked($ip),
            var_export($ip, true) . ' must never be connected to'
        );
    }

    /**
     * Addresses on the public internet, which must not be caught by the guard.
     *
     * A guard that blocks everything is safe and useless: it would fail every legitimate
     * developer URL, and the reachability check would report the whole catalogue as broken.
     *
     * @return array<string, array{0: string}>
     *
     * @since 4.0.0
     */
    public static function allowed(): array
    {
        return [
            'a public v4 address'        => ['93.184.216.34'],
            'another one'               => ['8.8.8.8'],
            'just below 10/8'           => ['9.255.255.255'],
            'just above 10/8'           => ['11.0.0.0'],
            'just below 172.16/12'      => ['172.15.255.255'],
            'just above 172.16/12'      => ['172.32.0.0'],
            'just below 192.168/16'     => ['192.167.255.255'],
            'just above 192.168/16'     => ['192.169.0.0'],
            'just below 127/8'          => ['126.255.255.255'],
            'just above 127/8'          => ['128.0.0.1'],
            'just below link-local'     => ['169.253.255.255'],
            'just above link-local'     => ['169.255.0.0'],
            'just below CGNAT'          => ['100.63.255.255'],
            'just above CGNAT'          => ['100.128.0.0'],
            'a public v6 address'       => ['2606:2800:220:1:248:1893:25c8:1946'],
            'public v6, one below fc00' => ['fbff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
            'public v6, above fe80/10'  => ['fec0::1'],
            'a public v4 mapped into v6' => ['::ffff:93.184.216.34'],
        ];
    }

    /**
     * @dataProvider allowed
     *
     * @param string $ip The address.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testAllowsPublicAddresses(string $ip): void
    {
        $this->assertFalse(
            IpGuard::isBlocked($ip),
            var_export($ip, true) . ' is a public address and must remain reachable'
        );
    }

    /**
     * The boundaries of every blocked range, from both sides.
     *
     * Off-by-one in a netmask is the classic way one of these ends up one address too small,
     * and it is invisible in normal use.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testRangeEdgesAreInclusive(): void
    {
        $edges = [
            '10.0.0.0'        => true,
            '10.255.255.255'  => true,
            '172.16.0.0'      => true,
            '172.31.255.255'  => true,
            '192.168.0.0'     => true,
            '192.168.255.255' => true,
            '127.0.0.0'       => true,
            '127.255.255.255' => true,
            '169.254.0.0'     => true,
            '169.254.255.255' => true,
        ];

        foreach ($edges as $ip => $expected) {
            $this->assertSame($expected, IpGuard::isBlocked((string) $ip), (string) $ip);
        }
    }
}
