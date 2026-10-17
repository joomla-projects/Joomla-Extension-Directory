<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Apps;

use Jed\Component\Apps\Site\Service\AppsLegacyRenderer;
use Jed\Component\Apps\Site\Service\AppsRequest;
use PHPUnit\Framework\TestCase;

/**
 * The markup Joomla 3's Install from Web plugin can use.
 *
 * Its script (tests/fixtures/com_apps/j3-client-2.1.2.js) puts the response into the page
 * unsanitised and finds its way around through inline handlers and jQuery selectors. Both are
 * read out of the script here, so that a handler we emit always names something the script
 * defines, and every element it selects in a response is there.
 *
 * @since 4.0.0
 */
final class LegacyMarkupContractTest extends TestCase
{
    private AppsLegacyRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new AppsLegacyRenderer(
            static fn (string $key, ...$args): string => $key,
            static fn (object $item): string => 'https://jed.example/extension/' . $item->alias,
            static fn (string $text): string => $text,
            'https://jed.example/',
            'https://jed.example/'
        );
    }

    private static function script(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/com_apps/j3-client-2.1.2.js');
    }

    private static function item(array $override = []): object
    {
        return (object) array_merge([
            'id' => 80, 'name' => 'Akeeba Backup', 'alias' => 'akeeba-backup', 'catid' => 139, 'intro' => 'Backs up your site.',
            'type' => 'free', 'extension_types' => '["com","mod","plugin"]', 'logo' => '/images/a.png',
            'score_overall' => '5.00', 'score_count' => 10, 'extension_version' => '10.4.0', 'created' => '2007-06-05 00:00:00',
            'uses_updater' => 1, 'update_url' => 'http://cdn.example/updates.xml', 'download_url' => 'https://dev.example/download',
            'joomla_versions' => '["30"]', 'license' => 'GPLv2', 'developer_url' => 'https://dev.example/', 'description' => 'Text',
        ], $override);
    }

    private function request(array $query = []): AppsRequest
    {
        return AppsRequest::fromQuery($query + ['release' => base64_encode('3.10')]);
    }

    private function listing(?array $items = null, array $query = []): string
    {
        return $this->renderer->listing($this->request($query), [
            (object) ['id' => 23, 'title' => 'Security', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'rgt' => 2],
        ], $items ?? [self::item()], null);
    }

    private function extension(array $override = []): string
    {
        return $this->renderer->extension($this->request(['view' => 'extension', 'id' => '80']), self::item($override), [], null, ['30' => '3']);
    }

    private static function xpath(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($doc);
    }

    /**
     * @return string[]  Decoded values of every onclick attribute.
     */
    private static function handlers(string $html): array
    {
        return array_map(static fn ($n) => $n->nodeValue, iterator_to_array(self::xpath($html)->query('//@onclick')));
    }

    public function testEveryHandlerCallsSomethingTheScriptDefines(): void
    {
        preg_match_all('/^\s*(Joomla(?:\.apps)?\.\w+)\s*=\s*function/m', self::script(), $defined);
        $defined = $defined[1];

        $this->assertContains('Joomla.loadweb', $defined);
        $this->assertContains('Joomla.installfromweb', $defined);
        $this->assertContains('Joomla.apps.initiateSearch', $defined);

        $handlers = array_merge(self::handlers($this->listing()), self::handlers($this->extension()));
        $this->assertNotEmpty($handlers);

        foreach ($handlers as $handler) {
            preg_match_all('/(Joomla(?:\.apps)?\.\w+)\(/', $handler, $calls);

            $this->assertNotEmpty($calls[1], 'no call in: ' . $handler);

            foreach ($calls[1] as $call) {
                $this->assertContains($call, $defined, $call . ' is not defined by the Joomla 3 plugin script');
            }
        }
    }

    public function testTheCardHandlerUsesTheGlobalTheScriptDeclares(): void
    {
        $this->assertStringContainsString('var apps_base_url', self::script());
        $this->assertContains(
            "Joomla.loadweb(apps_base_url+'index.php?option=com_apps&format=json&view=extension&id=80');",
            self::handlers($this->listing())
        );
    }

    public function testTheScriptsSelectorsAreAllInTheResponse(): void
    {
        preg_match_all("/jQuery\(['\"]#([\w-]+)['\"]\)/", self::script(), $m);

        // Elements of the plugin's own tab, outside the response.
        $page     = ['web-loader', 'web-loader-error', 'jed-container', 'web', 'myTabContent', 'element-box', 'install_url',
            'uploadform-web', 'uploadform-web-url', 'uploadform-web-name', 'uploadform-web-name-label', 'adminForm', 'appsloading',
            'joomlaapsinstallatinput', 'web-paginate-loader', 'web-paginate-error'];
        $optional = ['com-apps-filter-joomla-version'];
        $html     = $this->listing();

        foreach (array_unique($m[1]) as $id) {
            if (in_array($id, $page, true) || in_array($id, $optional, true)) {
                continue;
            }

            $this->assertTrue(str_contains($html, 'id="' . $id . '"'), 'the script selects #' . $id);
        }

        foreach (['grid-view', 'list-view', 'grid-container', 'list-container'] as $class) {
            $this->assertStringContainsString($class, $html);
        }

        $this->assertSame(1, self::xpath($html)->query('//a[contains(@class,"transcode")]')->length > 0 ? 1 : 0);
    }

    public function testTheInstallButtonHandlerIsAWellFormedCallForAnyName(): void
    {
        $names = ["Plain", "O'Brien's", 'Back\\slash', "Line\nbreak", "</script><script>alert(1)</script>", "'+alert(1)+'", "x\u{2028}y", '"quoted"'];

        foreach ($names as $name) {
            $handler = array_values(array_filter(
                self::handlers($this->extension(['name' => $name])),
                static fn (string $h): bool => str_contains($h, 'installfromweb')
            ))[0];

            // return Joomla.installfromweb('<url>', '<name>'): a string with no unescaped quote or line break.
            $this->assertMatchesRegularExpression(
                "/^return Joomla\\.installfromweb\\('https?:\\/\\/[^'\\s]+', '(?:[^'\\\\\\n\\r\\x{2028}\\x{2029}]|\\\\.)*'\\)$/u",
                $handler,
                'handler for ' . json_encode($name) . ': ' . $handler
            );
        }
    }

    public function testNoUrlCanBreakOutOfAHandler(): void
    {
        foreach (["https://x.example/'+alert(1)+'", 'javascript:alert(1)', "https://x.example/a'b"] as $url) {
            $html = $this->extension(['update_url' => $url, 'download_url' => $url]);

            $this->assertStringNotContainsString('installfromweb', $html);
            $this->assertStringNotContainsString('alert(1)', $html);
        }
    }

    public function testTextsAreEscaped(): void
    {
        $evil = '<img src=x onerror=alert(1)>"\'';
        $html = $this->extension(['name' => $evil, 'license' => $evil]) . $this->listing([self::item(['name' => $evil, 'intro' => $evil])], []);

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<script', $this->listing(null, ['filter_search' => '"><script>x</script>']));
    }

    public function testTheSearchTermStaysInTheBoxAsThePluginExpects(): void
    {
        $value = self::xpath($this->listing([], ['filter_search' => 'akeeba_backup']))->query('//input[@id="com-apps-searchbox"]/@value')->item(0)->nodeValue;

        $this->assertSame('akeeba backup', $value);
    }

    public function testAListingWithOnlyADownloadLinkOpensTheDevelopersSite(): void
    {
        $x = self::xpath($this->extension(['uses_updater' => 0, 'update_url' => '']));

        $this->assertSame(0, $x->query('//a[@onclick]')->length);
        $this->assertSame('https://dev.example/download', $x->query('//a[contains(@class,"btn-success")]/@href')->item(0)->nodeValue);
    }

    public function testPaidExtensionsAreMarkedAsTheOldDirectoryDoes(): void
    {
        $this->assertStringContainsString('label-jcommercial', $this->listing([self::item(['type' => 'paid'])]));
        $this->assertStringNotContainsString('label-jcommercial', $this->listing([self::item(['type' => 'free'])]));
    }
}
