<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Apps;

use Jed\Component\Apps\Site\Service\AppsLegacyRenderer;
use Jed\Component\Apps\Site\Service\AppsRenderer;
use Jed\Component\Apps\Site\Service\AppsRendererInterface;
use Jed\Component\Apps\Site\Service\AppsRequest;
use PHPUnit\Framework\TestCase;

/**
 * Compares what we render with responses recorded from the live directory: the Bootstrap 5 ones
 * that Joomla 4 and later get, and the Bootstrap 2 ones that Joomla 3 gets. See
 * tests/fixtures/com_apps/README.md.
 *
 * @since 4.0.0
 */
final class FixtureParityTest extends TestCase
{
    private const LABELS = [
        'COM_APPS_CATEGORIES' => 'Categories', 'COM_APPS_HOME' => 'Home', 'COM_APPS_SORT_DEFAULT' => 'JED Default Sorting',
        'COM_APPS_SORT_REVIEWS' => 'Sort by Reviews', 'COM_APPS_SORT_SCORE' => 'Sort by Score', 'COM_APPS_SORT_NAME' => 'Sort by Name',
        'COM_APPS_SORT_NEWEST' => 'Newest First', 'COM_APPS_SORT_UPDATED' => 'Recently Updated',
        'COM_APPS_FILTER_ALL' => 'All Joomla! Versions', 'COM_APPS_FILTER_CURRENT' => 'Current Joomla! Version',
        'COM_APPS_NO_RESULTS' => 'Cannot find any extensions for your version of Joomla. Please try a different search word or another category.',
        'COM_APPS_INSTALL' => 'Install', 'COM_APPS_LISTING' => 'Directory Listing', 'COM_APPS_DEVELOPER_SITE' => 'Developer Website',
    ];

    private static function fixture(string $name): string
    {
        $path = dirname(__DIR__, 2) . '/fixtures/com_apps/' . $name . '.json';

        return json_decode((string) file_get_contents($path), true)['data']['html'];
    }

    private function renderer(bool $legacy): AppsRendererInterface
    {
        $arguments = [
            static fn (string $key, ...$a): string => self::LABELS[$key] ?? $key,
            static fn (object $i): string => 'https://extensions.joomla.org/extension/akeeba-backup/',
            static fn (string $t): string => $t,
            'https://extensions.joomla.org',
        ];

        return $legacy ? new AppsLegacyRenderer(...$arguments) : new AppsRenderer(...$arguments);
    }

    private static function xpath(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($doc);
    }

    private function dashboard(bool $legacy, array $query = []): string
    {
        $request = AppsRequest::fromQuery($query + ['release' => base64_encode($legacy ? '3.9' : '5.4')]);
        $item    = (object) [
            'id' => 80, 'name' => 'Akeeba Backup', 'alias' => 'akeeba-backup', 'catid' => 138, 'intro' => 'Backup.', 'type' => 'free',
            'extension_types' => '["com","mod","plugin"]', 'logo' => '', 'score_overall' => '5', 'score_count' => 1135,
        ];

        return $this->renderer($legacy)->listing($request, [
            (object) ['id' => 23, 'title' => 'Access & Security', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'rgt' => 2],
        ], $query === [] ? [$item] : [], null);
    }

    private function extension(bool $legacy): string
    {
        $request = AppsRequest::fromQuery(['view' => 'extension', 'id' => '80', 'release' => base64_encode($legacy ? '3.9' : '5.4')]);
        $item    = (object) [
            'id' => 80, 'name' => 'Akeeba Backup', 'alias' => 'akeeba-backup', 'catid' => 138, 'intro' => '', 'type' => 'free',
            'extension_types' => '["com"]', 'logo' => '', 'score_overall' => '5', 'score_count' => 1135, 'extension_version' => '10.4.0',
            'created' => '2007-06-05 00:00:00', 'uses_updater' => 1, 'update_url' => 'http://cdn.akeebabackup.com/updates/akeebabackupjed.xml',
            'download_url' => '', 'joomla_versions' => '["60"]', 'license' => 'GPLv2 or later', 'developer_url' => 'https://www.akeeba.com/',
            'description' => 'Backup.',
        ];

        return $this->renderer($legacy)->extension($request, $item, [], null, ['60' => '6']);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function generations(): array
    {
        return ['Joomla 4 and later' => [false, 'modern'], 'Joomla 3' => [true, 'legacy']];
    }

    /**
     * @dataProvider generations
     */
    public function testTheOrderingChoicesAreTheSameValuesAndLabelsInTheSameOrder(bool $legacy, string $generation): void
    {
        $query = '//select[@id="com-apps-ordering"]/option';
        $live  = self::xpath(self::fixture($generation . '-search-no-results'))->query($query);
        $ours  = self::xpath($this->dashboard($legacy))->query($query);
        $read  = static fn (\DOMNodeList $nodes): array => array_map(static fn ($n) => [$n->getAttribute('value'), trim($n->textContent)], iterator_to_array($nodes));

        $this->assertNotEmpty($read($live));
        $this->assertSame($read($live), $read($ours));
    }

    /**
     * @dataProvider generations
     */
    public function testTheEmptySearchMessageIsTheSame(bool $legacy, string $generation): void
    {
        $message = 'Cannot find any extensions for your version of Joomla. Please try a different search word or another category.';

        $this->assertStringContainsString($message, self::fixture($generation . '-search-no-results'));
        $this->assertStringContainsString($message, $this->dashboard($legacy, ['filter_search' => 'zzzz']));
    }

    /**
     * @dataProvider generations
     */
    public function testCategoryLinksHaveTheShapeTheLiveDirectoryUses(bool $legacy, string $generation): void
    {
        $query = '//a[contains(concat(" ", @class, " "), " transcode ")]/@href';
        $live  = self::xpath(self::fixture($generation . '-dashboard-3-items'))->query($query);
        $ours  = self::xpath($this->dashboard($legacy))->query($query);

        $this->assertGreaterThan(0, $live->length);
        $this->assertSame($live->item(0)->nodeValue, $ours->item(0)->nodeValue);
        $this->assertSame($live->item(1)->nodeValue, preg_replace('/id=\d+/', 'id=23', $ours->item(1)->nodeValue));
    }

    /**
     * @dataProvider generations
     */
    public function testEveryIdTheLiveToolbarHasIsInOurs(bool $legacy, string $generation): void
    {
        $query = '//*[@id]/@id';
        $live  = array_map(static fn ($n) => $n->nodeValue, iterator_to_array(self::xpath(self::fixture($generation . '-dashboard-3-items'))->query($query)));
        $ours  = array_map(static fn ($n) => $n->nodeValue, iterator_to_array(self::xpath($this->dashboard($legacy))->query($query)));

        $this->assertNotEmpty($live);
        $this->assertSame([], array_values(array_diff($live, $ours)), 'ids the live directory sends and we do not');
    }

    /**
     * @dataProvider generations
     */
    public function testTheInstallButtonCarriesTheUpdateServerTheWayTheLiveOneDoes(bool $legacy, string $generation): void
    {
        $live = self::fixture($generation . '-extension-80');
        $ours = $this->extension($legacy);

        // The inline handler holds the URL and the name; the attribute is decoded before it is read.
        $handler = static fn (string $html): string => (string) (self::xpath($html)->query('//a[@onclick]/@onclick')->item(0)?->nodeValue);

        if ($legacy) {
            $this->assertMatchesRegularExpression('/^return Joomla\.installfromweb\(\'http:\/\/[^\']+\.xml\', \'Akeeba Backup\'\)$/', $handler($live));
            $this->assertMatchesRegularExpression('/^return Joomla\.installfromweb\(\'http:\/\/[^\']+\.xml\', \'Akeeba Backup\'\)$/', $handler($ours));

            return;
        }

        foreach ([$live, $ours] as $html) {
            $button = self::xpath($html)->query('//button[@id="install-extension"]')->item(0);

            $this->assertNotNull($button);
            $this->assertMatchesRegularExpression('/^http:\/\/[^"]+\.xml$/', $button->getAttribute('data-downloadurl'));
            $this->assertSame('Akeeba Backup', $button->getAttribute('data-name'));
        }
    }

    /**
     * @dataProvider generations
     */
    public function testTheExtensionPageShowsTheSameFacts(bool $legacy, string $generation): void
    {
        foreach (['Version', 'License', 'Download Type', 'Added On', 'Compatible Versions'] as $label) {
            $this->assertStringContainsString('<dt>' . $label . '</dt>', self::fixture($generation . '-extension-80'));
        }

        $ours = $this->extension($legacy);

        foreach (['COM_APPS_VERSION' => 'Version'] as $key => $label) {
            $this->assertStringContainsString('10.4.0', $ours);
        }
    }

    public function testTheOldMarkupUsesNoClassTheOldDirectoryDoesNot(): void
    {
        $known = [];

        foreach (['legacy-dashboard-3-items', 'legacy-extension-80', 'legacy-search-no-results'] as $name) {
            foreach (self::xpath(self::fixture($name))->query('//@class') as $attribute) {
                $known = array_merge($known, preg_split('/\s+/', trim($attribute->nodeValue)) ?: []);
            }
        }

        $ours = [];

        foreach ([$this->dashboard(true), $this->dashboard(true, ['filter_search' => 'zz']), $this->extension(true)] as $html) {
            foreach (self::xpath($html)->query('//@class') as $attribute) {
                $ours = array_merge($ours, preg_split('/\s+/', trim($attribute->nodeValue)) ?: []);
            }
        }

        $this->assertSame([], array_values(array_diff(array_unique($ours), array_unique($known))));
    }
}
