<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Identity;

use Jed\Component\Jed\Administrator\Identity\ClaimRules;
use PHPUnit\Framework\TestCase;

/**
 * When an old JED account may be claimed, and when a mailed link is good.
 *
 * @since 4.0.0
 */
final class ClaimRulesTest extends TestCase
{
    private const OLD = ['id' => 7, 'username' => 'ann', 'email' => 'ann@example.org', 'block' => 0];

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testAnOrdinaryOldAccountMayBeClaimed(): void
    {
        $this->assertNull(ClaimRules::oldAccountProblem(self::OLD, 99, false));
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testAccountsThatMayNotBeClaimed(): void
    {
        $this->assertSame(ClaimRules::NOT_FOUND, ClaimRules::oldAccountProblem(null, 99, false));
        $this->assertSame(ClaimRules::SAME_ACCOUNT, ClaimRules::oldAccountProblem(self::OLD, 7, false));
        $this->assertSame(ClaimRules::LINKED, ClaimRules::oldAccountProblem(self::OLD, 99, true));
        $this->assertSame(ClaimRules::BLOCKED, ClaimRules::oldAccountProblem(['block' => 1] + self::OLD, 99, false));
        $this->assertSame(ClaimRules::PRIVILEGED, ClaimRules::oldAccountProblem(self::OLD, 99, false, true));
        $this->assertSame(ClaimRules::NO_EMAIL, ClaimRules::oldAccountProblem(['email' => ''] + self::OLD, 99, false));
        $this->assertSame(ClaimRules::NO_EMAIL, ClaimRules::oldAccountProblem(['email' => 'nope'] + self::OLD, 99, false));
        $this->assertSame(
            ClaimRules::PORTAL_USER,
            ClaimRules::oldAccountProblem(['username' => '11111111-1111-4111-8111-111111111111'] + self::OLD, 99, false)
        );
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testLinkChecks(): void
    {
        $claim = ['token_hash' => ClaimRules::hashToken('abc'), 'expires' => '2026-10-31 12:00:00'];

        $this->assertNull(ClaimRules::tokenProblem($claim, 'abc', '2026-10-31 11:59:59'));
        $this->assertSame(ClaimRules::NO_CLAIM, ClaimRules::tokenProblem(null, 'abc', '2026-10-31 11:00:00'));
        $this->assertSame(ClaimRules::EXPIRED, ClaimRules::tokenProblem($claim, 'abc', '2026-10-31 12:00:01'));
        $this->assertSame(ClaimRules::WRONG_TOKEN, ClaimRules::tokenProblem($claim, 'abd', '2026-10-31 11:00:00'));
        $this->assertSame(ClaimRules::WRONG_TOKEN, ClaimRules::tokenProblem($claim, '', '2026-10-31 11:00:00'));
    }
}
