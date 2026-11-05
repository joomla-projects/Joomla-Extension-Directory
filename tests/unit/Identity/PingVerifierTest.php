<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Identity;

use Jed\Component\Jed\Administrator\Identity\PingVerifier;
use PHPUnit\Framework\TestCase;

/**
 * When the JED believes a ping from the portal.
 *
 * @since 4.0.0
 */
final class PingVerifierTest extends TestCase
{
    private const SECRET = 'a-shared-secret-of-at-least-32-chars!';

    private const BODY = '{"task":"remove","guid":"11111111-1111-4111-8111-111111111111"}';

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testAGenuinePingPasses(): void
    {
        $sig = PingVerifier::sign(self::SECRET, '1000', self::BODY);

        $this->assertNull(PingVerifier::check(self::SECRET, '1000', $sig, self::BODY, 1100));
        $this->assertNull(PingVerifier::check(self::SECRET, '1000', strtoupper($sig), self::BODY, 1000 + PingVerifier::TOLERANCE));
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testRefusals(): void
    {
        $sig = PingVerifier::sign(self::SECRET, '1000', self::BODY);

        $short = 'short';

        $this->assertNotNull(
            PingVerifier::check($short, '1000', PingVerifier::sign($short, '1000', self::BODY), self::BODY, 1000),
            'a short secret is refused even with a matching signature'
        );
        $this->assertNotNull(PingVerifier::check('', '1000', $sig, self::BODY, 1000), 'no secret, no pings');
        $this->assertNotNull(PingVerifier::check(self::SECRET, '1000', $sig, self::BODY, 1000 + PingVerifier::TOLERANCE + 1), 'too old');
        $this->assertNotNull(PingVerifier::check(self::SECRET, '1000', $sig, self::BODY, 1000 - PingVerifier::TOLERANCE - 1), 'from the future');
        $this->assertNotNull(PingVerifier::check(self::SECRET, 'abc', $sig, self::BODY, 1000), 'bad timestamp');
        $this->assertNotNull(PingVerifier::check('other', '1000', $sig, self::BODY, 1000), 'wrong secret');
        $this->assertNotNull(PingVerifier::check(self::SECRET, '1000', $sig, self::BODY . ' ', 1000), 'body changed');
        $this->assertNotNull(PingVerifier::check(self::SECRET, '1001', $sig, self::BODY, 1000), 'timestamp changed');
        $this->assertNotNull(PingVerifier::check(self::SECRET, '1000', '', self::BODY, 1000), 'no signature');
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testParse(): void
    {
        $this->assertSame(
            ['task' => 'remove', 'guid' => '11111111-1111-4111-8111-111111111111'],
            PingVerifier::parse('{"task":" Remove ","guid":"11111111-1111-4111-8111-111111111111"}')
        );
        $this->assertSame(['task' => 'ping', 'guid' => ''], PingVerifier::parse('{"task":"ping","guid":""}'));
        $this->assertNull(PingVerifier::parse('not json'));
        $this->assertNull(PingVerifier::parse('{"task":"remove"}'));
        $this->assertNull(PingVerifier::parse('{"task":"remove","guid":"nope"}'));
        $this->assertNull(PingVerifier::parse('{"task":"","guid":""}'));
    }
}
