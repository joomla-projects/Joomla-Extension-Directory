<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Apps;

use Jed\Component\Apps\Site\Service\AppsCatalogue;
use PHPUnit\Framework\TestCase;

/**
 * The pure parts of the catalogue: the category tree and the Joomla version ids.
 *
 * @since 4.0.0
 */
final class AppsCatalogueTest extends TestCase
{
    private static function tree(): array
    {
        $row = static fn (int $id, int $parent, int $level, int $lft, int $rgt): object => (object) [
            'id' => $id, 'parent_id' => $parent, 'level' => $level, 'lft' => $lft, 'rgt' => $rgt, 'title' => 'c' . $id,
        ];

        return [
            $row(23, 1, 1, 2, 7),
            $row(139, 23, 2, 3, 4),
            $row(140, 23, 2, 5, 6),
            $row(24, 1, 1, 8, 9),
        ];
    }

    public function testACategoryIncludesItsSubCategories(): void
    {
        $this->assertSame([23, 139, 140], AppsCatalogue::subtreeIds(self::tree(), 23));
    }

    public function testALeafAndASiblingDoNotLeakIntoEachOther(): void
    {
        $this->assertSame([139], AppsCatalogue::subtreeIds(self::tree(), 139));
        $this->assertSame([24], AppsCatalogue::subtreeIds(self::tree(), 24));
    }

    public function testAnUnknownOrUnpublishedCategoryHasNoIds(): void
    {
        $this->assertSame([], AppsCatalogue::subtreeIds(self::tree(), 999));
        $this->assertSame([], AppsCatalogue::subtreeIds([], 23));
    }

    public function testAJoomlaMajorVersionMapsToItsTwoListingIds(): void
    {
        $this->assertSame(['60', '61'], AppsCatalogue::versionIds(6));
        $this->assertSame(['40', '41'], AppsCatalogue::versionIds(4));
    }
}
