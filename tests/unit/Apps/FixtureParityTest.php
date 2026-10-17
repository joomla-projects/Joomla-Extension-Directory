<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Apps;

use Jed\Component\Apps\Site\Service\AppsRenderer;
use Jed\Component\Apps\Site\Service\AppsRequest;
use PHPUnit\Framework\TestCase;

/**
 * Compares what we render with responses recorded from the current directory, for the parts
 * both produce. See tests/fixtures/com_apps/README.md for why not all of it.
 *
 * @since 4.0.0
 */
final class FixtureParityTest extends TestCase
{
    private static function fixture(string $name): string
    {
        $path = dirname(__DIR__, 2) . '/fixtures/com_apps/' . $name . '.json';

        return json_decode((string) file_get_contents($path), true)['data']['html'];
    }

    private function ours(): string
    {
        $labels   = [
            'COM_APPS_CATEGORIES' => 'Categories', 'COM_APPS_HOME' => 'Home', 'COM_APPS_SORT_DEFAULT' => 'JED Default Sorting',
            'COM_APPS_SORT_REVIEWS' => 'Sort by Reviews', 'COM_APPS_SORT_SCORE' => 'Sort by Score', 'COM_APPS_SORT_NAME' => 'Sort by Name',
            'COM_APPS_SORT_NEWEST' => 'Newest First', 'COM_APPS_SORT_UPDATED' => 'Recently Updated',
            'COM_APPS_NO_RESULTS' => 'Cannot find any extensions for your version of Joomla. Please try a different search word or another category.',
        ];
        $renderer = new AppsRenderer(
            static fn (string $key, ...$a): string => $labels[$key] ?? $key,
            static fn (object $i): string => 'x',
            static fn (string $t): string => $t,
            'https://extensions.joomla.org'
        );

        return $renderer->listing(AppsRequest::fromQuery(['filter_search' => 'zzzz']), [
            (object) ['id' => 23, 'title' => 'Access & Security', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'rgt' => 2],
        ], [], null);
    }

    public function testTheOrderingChoicesAreTheSameValuesInTheSameOrder(): void
    {
        $pattern = '/<option value="([^"]*)"/';

        preg_match_all($pattern, self::fixture('search-no-results'), $live);
        preg_match_all($pattern, $this->ours(), $ours);

        $this->assertSame($live[1], $ours[1]);
    }

    public function testTheOrderingLabelsAreTheSame(): void
    {
        $pattern = '/<option value="[^"]*"[^>]*>([^<]*)</';

        preg_match_all($pattern, self::fixture('search-no-results'), $live);
        preg_match_all($pattern, $this->ours(), $ours);

        $this->assertSame($live[1], $ours[1]);
    }

    public function testTheEmptySearchMessageIsTheSame(): void
    {
        $this->assertStringContainsString('Cannot find any extensions for your version of Joomla. Please try a different search word or another category.', self::fixture('search-no-results'));
        $this->assertStringContainsString('Cannot find any extensions for your version of Joomla. Please try a different search word or another category.', $this->ours());
    }

    public function testCategoryLinksHaveTheShapeTheLiveDirectoryUses(): void
    {
        preg_match_all('/href="(index\.php\?option=com_apps&format=json&view=category&id=\d+)"/', self::fixture('dashboard-3-items'), $live);
        preg_match_all('/href="(index\.php\?option=com_apps&amp;format=json&amp;view=category&amp;id=\d+)"/', $this->ours(), $ours);

        $this->assertNotEmpty($live[1]);
        $this->assertSame(
            str_replace('&amp;', '&', $ours[1][0]),
            preg_replace('/id=\d+/', 'id=23', $live[1][0])
        );
    }

    public function testTheLiveDirectoryTreatsAnExtensionAsTheDataWeStore(): void
    {
        $live = self::fixture('extension-80');

        foreach (['Version', 'License', 'Download Type', 'Added On', 'Compatible Versions'] as $label) {
            $this->assertStringContainsString('<dt>' . $label . '</dt>', $live);
        }

        $this->assertMatchesRegularExpression('#Joomla\.installfromweb\(\'https?://[^\']+\.xml\'#', $live);
    }
}
