<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Privacy;

use Jed\Component\Jed\Administrator\Privacy\PrivacyDeterminations;
use Jed\Component\Jed\Administrator\Privacy\PrivacyRemovalService;
use Jed\Component\Tickets\Administrator\Privacy\TicketPrivacyService;
use PHPUnit\Framework\TestCase;

/**
 * The determinations 8.12 asks for, checked against the schema they are about.
 *
 * This is the test that makes `P1-18` hold over time. The plugins can be right on the day they
 * are written and quietly wrong a year later, because the failure mode is not a broken query -
 * it is a table somebody adds without deciding what happens to it on an erasure request, which
 * nothing complains about and nobody sees. Comparing the catalogue against the install schema
 * turns that silence into a failing test.
 *
 * @since 4.1.0
 */
final class PrivacyDeterminationsTest extends TestCase
{
    /**
     * The repository root.
     *
     * @since 4.1.0
     */
    private const ROOT = __DIR__ . '/../../..';

    /**
     * Every table com_jed installs has exactly one determination.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testEveryJedTableHasADetermination(): void
    {
        $this->assertSameTables(
            $this->tablesIn(self::ROOT . '/src/administrator/components/com_jed/sql/install.mysql.utf8.sql'),
            PrivacyDeterminations::allTables(),
            'com_jed'
        );
    }

    /**
     * Every table com_tickets installs has exactly one determination.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testEveryTicketTableHasADetermination(): void
    {
        $this->assertSameTables(
            $this->tablesIn(self::ROOT . '/src/administrator/components/com_tickets/sql/install.mysql.utf8.sql'),
            array_merge(
                array_keys(TicketPrivacyService::IN_SCOPE),
                array_keys(TicketPrivacyService::OUT_OF_SCOPE)
            ),
            'com_tickets'
        );
    }

    /**
     * A table is either in scope or out of it, never both.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testNoTableIsBothInAndOutOfScope(): void
    {
        $this->assertSame(
            [],
            array_intersect(array_keys(PrivacyDeterminations::IN_SCOPE), array_keys(PrivacyDeterminations::OUT_OF_SCOPE)),
            'A table cannot both hold personal data and hold none.'
        );
    }

    /**
     * Every determination states a handling the services implement.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testHandlingsAreKnown(): void
    {
        $known = [
            PrivacyDeterminations::ANONYMISE,
            PrivacyDeterminations::DELETE,
            PrivacyDeterminations::RETAIN,
            PrivacyDeterminations::CONDITIONAL,
        ];

        foreach (PrivacyDeterminations::IN_SCOPE as $table => $determination) {
            $this->assertContains($determination['handling'], $known, $table . ' states an unknown handling.');
            $this->assertArrayHasKey('export', $determination, $table . ' does not say whether it is exported.');
        }
    }

    /**
     * Every reason a determination gives has wording in the component's language file.
     *
     * A capability screen that prints raw language keys states nothing, which is worse than not
     * having one: it looks like an answer.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testEveryReasonHasWording(): void
    {
        $jedStrings     = file_get_contents(self::ROOT . '/src/administrator/components/com_jed/language/en-GB/com_jed.ini');
        $ticketStrings  = file_get_contents(self::ROOT . '/src/administrator/components/com_tickets/language/en-GB/com_tickets.ini');

        $keys = array_merge(
            array_column(PrivacyDeterminations::IN_SCOPE, 'reason'),
            array_values(PrivacyDeterminations::OUT_OF_SCOPE)
        );

        foreach ($keys as $key) {
            $this->assertStringContainsString($key . '=', $jedStrings, $key . ' has no wording in com_jed.ini.');
        }

        $ticketKeys = array_merge(
            array_column(TicketPrivacyService::IN_SCOPE, 'reason'),
            array_values(TicketPrivacyService::OUT_OF_SCOPE)
        );

        foreach ($ticketKeys as $key) {
            $this->assertStringContainsString($key . '=', $ticketStrings, $key . ' has no wording in com_tickets.ini.');
        }
    }

    /**
     * The listing a privacy block uses must be a reason code the component seeds, or
     * `ExtensionModel::block()` would reject it and the backend would show a blank reason.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testTheOwnerWithdrawnBlockReasonIsSeeded(): void
    {
        $script = file_get_contents(self::ROOT . '/src/administrator/components/com_jed/script.php');

        $this->assertStringContainsString(
            "'" . PrivacyRemovalService::BLOCK_REASON_OWNER_WITHDRAWN . "' =>",
            $script,
            'The block reason a privacy erasure applies is not seeded into #__jed_block_reasons.'
        );
    }

    /**
     * Bans still in force survive an erasure request; expired and absent ones do not.
     *
     * @param array<string, mixed>|null $row      The user's privilege row.
     * @param bool                      $expected Whether it has to be retained.
     *
     * @return void
     *
     * @dataProvider banRecords
     *
     * @since 4.1.0
     */
    public function testBanRetentionRule(?array $row, bool $expected): void
    {
        $this->assertSame($expected, PrivacyRemovalService::banMustBeRetained($row, '2026-08-08 12:00:00'));
    }

    /**
     * @return array<string, array{0: array<string, mixed>|null, 1: bool}>
     *
     * @since 4.1.0
     */
    public static function banRecords(): array
    {
        return [
            'no row at all'            => [null, false],
            'privileges but no ban'    => [['banned' => 0, 'banned_until' => null], false],
            'ban that has run out'     => [['banned' => 1, 'banned_until' => '2026-08-01 00:00:00'], false],
            'ban still running'        => [['banned' => 1, 'banned_until' => '2026-09-01 00:00:00'], true],
            'ban with no end date'     => [['banned' => 1, 'banned_until' => null], true],
            'ban with a zero end date' => [['banned' => 1, 'banned_until' => '0000-00-00 00:00:00'], true],
            // The boundary: a ban whose end is exactly now has ended.
            'ban ending this second'   => [['banned' => 1, 'banned_until' => '2026-08-08 12:00:00'], false],
        ];
    }

    /**
     * Assert two table lists describe the same set, naming what is missing on either side.
     *
     * @param string[] $schema      Tables the install SQL creates.
     * @param string[] $determined  Tables the catalogue answers for.
     * @param string   $component   For the failure message.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function assertSameTables(array $schema, array $determined, string $component): void
    {
        sort($schema);
        sort($determined);

        $this->assertSame(
            [],
            array_values(array_diff($schema, $determined)),
            $component . ' installs tables with no privacy determination. Add each to IN_SCOPE or OUT_OF_SCOPE.'
        );

        $this->assertSame(
            [],
            array_values(array_diff($determined, $schema)),
            $component . ' states determinations for tables it does not install.'
        );
    }

    /**
     * The `#__jed_` tables an install file creates.
     *
     * @param string $file The SQL file.
     *
     * @return string[]
     *
     * @since 4.1.0
     */
    private function tablesIn(string $file): array
    {
        $this->assertFileExists($file);

        preg_match_all(
            '/CREATE TABLE(?: IF NOT EXISTS)?\s+`(#__jed_[a-z0-9_]+)`/i',
            (string) file_get_contents($file),
            $matches
        );

        return array_values(array_unique($matches[1]));
    }
}
