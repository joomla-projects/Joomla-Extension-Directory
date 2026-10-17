<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Privacy;

use Jed\Component\Jed\Administrator\Privacy\PrivacyExportService;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * A listing row is shared by everyone who ever touched it, so a data export must not carry the
 * download key or the JED team's own notes to whoever last edited it.
 *
 * @since 4.0.0
 */
final class PrivacyExportWithheldTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function listingTables(): array
    {
        return [
            'live listings' => ['#__jed_extensions'],
            'revisions'     => ['#__jed_extensions_history'],
        ];
    }

    /**
     * @dataProvider listingTables
     */
    public function testTeamNotesAndKeysAreWithheld(string $table): void
    {
        $withheld = (new ReflectionClassConstant(PrivacyExportService::class, 'WITHHELD'))->getValue();

        foreach (['download_key', 'internal_download_url', 'internal_note', 'approved_notes'] as $column) {
            $this->assertContains($column, $withheld[$table] ?? [], $column . ' must be withheld from ' . $table);
        }
    }
}
