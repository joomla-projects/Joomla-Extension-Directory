<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Identity;

use Jed\Component\Jed\Administrator\Identity\Guid;
use Jed\Component\Jed\Administrator\Identity\MigrationPlanner;
use Jed\Component\Jed\Administrator\Identity\PortalExport;
use PHPUnit\Framework\TestCase;

/**
 * The rules for moving old JED accounts onto portal GUIDs.
 *
 * @since 4.0.0
 */
final class IdentityMigrationTest extends TestCase
{
    private const G1 = '11111111-1111-4111-8111-111111111111';
    private const G2 = '22222222-2222-4222-8222-222222222222';
    private const G3 = '33333333-3333-4333-8333-333333333333';

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testGuidShape(): void
    {
        $this->assertTrue(Guid::isValid(self::G1));
        $this->assertTrue(Guid::isValid(' ' . strtoupper(self::G1) . ' '));
        $this->assertFalse(Guid::isValid('not-a-guid'));
        $this->assertFalse(Guid::isValid(self::G1 . 'x'));
        $this->assertSame(self::G1, Guid::normalise(' ' . strtoupper(self::G1)));
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testExportWithHeaderInAnyOrder(): void
    {
        $parsed = PortalExport::parse("name,EmailAddress,GUID\nAnn,Ann@Example.org," . strtoupper(self::G1) . "\n\nBob,bob@example.org," . self::G2 . "\n");

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(
            [['guid' => self::G1, 'email' => 'ann@example.org'], ['guid' => self::G2, 'email' => 'bob@example.org']],
            $parsed['rows']
        );
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testExportWithoutHeaderAndSemicolons(): void
    {
        $parsed = PortalExport::parse("\xEF\xBB\xBF" . self::G1 . ";ann@example.org\r\n" . self::G2 . ";bob@example.org");

        $this->assertCount(2, $parsed['rows']);
        $this->assertSame([], $parsed['errors']);
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testExportReportsBadLinesAndKeepsTheGoodOnes(): void
    {
        $parsed = PortalExport::parse("guid,email\nbad," . "a@example.org\n" . self::G1 . ",nope\n" . self::G2 . ",ok@example.org\n");

        $this->assertCount(1, $parsed['rows']);
        $this->assertCount(2, $parsed['errors']);
        $this->assertStringContainsString('Line 2', $parsed['errors'][0]);
        $this->assertStringContainsString('Line 3', $parsed['errors'][1]);
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testExportHeaderWithoutEmailColumn(): void
    {
        $parsed = PortalExport::parse("guid,name\n" . self::G1 . ",Ann\n");

        $this->assertSame([], $parsed['rows']);
        $this->assertNotEmpty($parsed['errors']);
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testAnEmailMatchBecomesARename(): void
    {
        $plan = MigrationPlanner::plan(
            [['id' => 7, 'username' => 'ann', 'email' => 'Ann@Example.org', 'block' => 0]],
            [['guid' => self::G1, 'email' => 'ann@example.org']]
        );

        $this->assertSame([], $plan['skipped']);
        $this->assertSame(
            [['user_id' => 7, 'from' => 'ann', 'guid' => self::G1, 'email' => 'ann@example.org']],
            $plan['rename']
        );
    }

    /**
     * Blocked accounts and staff accounts are the ones worth impersonating, and the portal does not
     * verify addresses, so neither is ever matched.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testBlockedAndStaffAccountsAreNeverRenamed(): void
    {
        $plan = MigrationPlanner::plan(
            [
                ['id' => 1, 'username' => 'ann', 'email' => 'ann@example.org', 'block' => 1],
                ['id' => 2, 'username' => 'bob', 'email' => 'bob@example.org', 'block' => 0, 'privileged' => true],
            ],
            [
                ['guid' => self::G1, 'email' => 'ann@example.org'],
                ['guid' => self::G2, 'email' => 'bob@example.org'],
            ]
        );

        $this->assertSame([], $plan['rename']);
        $this->assertSame([MigrationPlanner::BLOCKED, MigrationPlanner::PRIVILEGED], array_column($plan['skipped'], 'reason'));
    }

    /**
     * Cells shown in error messages carry no control characters.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testExportErrorsCarryNoControlCharacters(): void
    {
        $parsed = PortalExport::parse("guid,email\n\"\x1b[31mred\",a@example.org\n");

        $this->assertCount(1, $parsed['errors']);
        $this->assertStringNotContainsString("\x1b", $parsed['errors'][0]);
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testNothingUnclearIsRenamed(): void
    {
        $users = [
            ['id' => 1, 'username' => 'ann', 'email' => 'ann@example.org'],
            ['id' => 2, 'username' => self::G2, 'email' => 'bob@example.org'],
            ['id' => 3, 'username' => 'carl', 'email' => 'carl@example.org'],
            ['id' => 4, 'username' => self::G3, 'email' => 'dora@example.org'],
            ['id' => 5, 'username' => 'dora2', 'email' => 'dora2@example.org'],
        ];
        $portal = [
            ['guid' => self::G1, 'email' => 'nobody@example.org'],
            ['guid' => self::G2, 'email' => 'bob@example.org'],
            ['guid' => self::G3, 'email' => 'dora2@example.org'],
            ['guid' => '44444444-4444-4444-8444-444444444444', 'email' => 'carl@example.org'],
            ['guid' => '55555555-5555-4555-8555-555555555555', 'email' => 'carl@example.org'],
        ];

        $plan    = MigrationPlanner::plan($users, $portal);
        $reasons = array_column($plan['skipped'], 'reason', 'guid');

        $this->assertSame([], $plan['rename']);
        $this->assertSame(MigrationPlanner::NO_MATCH, $reasons[self::G1]);
        $this->assertSame(MigrationPlanner::ALREADY_LINKED, $reasons[self::G2]);
        $this->assertSame(MigrationPlanner::GUID_TAKEN, $reasons[self::G3]);
        $this->assertSame(MigrationPlanner::DUPLICATE_EMAIL, $reasons['44444444-4444-4444-8444-444444444444']);
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function testDuplicateGuidsAndAccountsWithAnotherGuidAreSkipped(): void
    {
        $plan = MigrationPlanner::plan(
            [
                ['id' => 1, 'username' => self::G3, 'email' => 'ann@example.org'],
                ['id' => 2, 'username' => 'bob', 'email' => 'bob@example.org'],
            ],
            [
                ['guid' => self::G1, 'email' => 'ann@example.org'],
                ['guid' => self::G2, 'email' => 'bob@example.org'],
                ['guid' => self::G2, 'email' => 'bob2@example.org'],
            ]
        );

        $this->assertSame([], $plan['rename']);
        $this->assertSame(
            [MigrationPlanner::OTHER_GUID, MigrationPlanner::DUPLICATE_GUID, MigrationPlanner::DUPLICATE_GUID],
            array_column($plan['skipped'], 'reason')
        );
    }
}
