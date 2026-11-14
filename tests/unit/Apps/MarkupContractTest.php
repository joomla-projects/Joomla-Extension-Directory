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
 * The markup the Joomla 6 Install from Web client can use.
 *
 * Two things must hold and both are checked on the rendered output. The client looks elements up
 * by id and class, and a missing one throws or leaves a button dead. And everything goes through
 * Joomla.sanitizeHtml, which deletes an element (children included) whose tag is not on its list
 * and strips an attribute that is not on it, silently. The list below is the one in
 * media/system/js/core.js (DefaultAllowlist) plus the three entries client.js adds.
 *
 * @since 4.0.0
 */
final class MarkupContractTest extends TestCase
{
    private const ALLOWED_ATTRIBUTES_ANY = ['class', 'dir', 'id', 'lang', 'role'];

    private const ALLOWLIST = [
        'a' => ['target', 'href', 'title', 'rel'], 'area' => [], 'b' => [], 'br' => [], 'col' => [], 'code' => [],
        'div' => [], 'em' => [], 'hr' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'i' => [], 'img' => ['src', 'srcset', 'alt', 'title', 'width', 'height'], 'li' => [], 'ol' => [], 'p' => [],
        'pre' => [], 's' => [], 'small' => [], 'span' => [], 'sub' => [], 'sup' => [], 'strong' => [], 'u' => [],
        'ul' => [], 'button' => ['type'],
        'input' => ['type', 'name', 'placeholder', 'inputmode'],
        'select' => ['name'], 'textarea' => ['name'], 'option' => ['value', 'selected'],
        'details' => ['open'], 'summary' => [],
    ];

    private AppsRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new AppsRenderer(
            static fn (string $key, ...$args): string => $args === [] ? $key : $key . '(' . implode(',', $args) . ')',
            static fn (object $item): string => 'https://jed.example/extension/' . $item->alias,
            static fn (string $text): string => '<p>' . htmlspecialchars($text) . '</p><blockquote>quoted</blockquote><script>alert(1)</script>',
            'https://jed.example/',
            'https://jed.example/'
        );
    }

    private static function category(int $id, int $parent, int $level, string $title, int $lft, int $rgt): object
    {
        return (object) ['id' => $id, 'parent_id' => $parent, 'level' => $level, 'title' => $title, 'lft' => $lft, 'rgt' => $rgt];
    }

    private static function categories(): array
    {
        return [
            self::category(23, 1, 1, 'Access & Security', 2, 5),
            self::category(139, 23, 2, 'Site Access', 3, 4),
            self::category(24, 1, 1, 'Admin', 6, 7),
        ];
    }

    private static function item(array $override = []): object
    {
        return (object) array_merge([
            'id' => 80, 'name' => 'Akeeba Backup', 'alias' => 'akeeba-backup', 'catid' => 139, 'intro' => 'Backs up your site.',
            'type' => 'free', 'extension_types' => '["com","mod","plugin"]', 'logo' => '/images/jed_logos/a.png',
            'score_overall' => '5.00', 'score_count' => 1135, 'extension_version' => '10.4.0', 'modified' => '2026-09-04 10:00:00',
            'created' => '2007-06-05 00:00:00', 'uses_updater' => 1, 'update_url' => 'http://cdn.example/updates.xml',
            'download_url' => 'https://dev.example/download', 'joomla_versions' => '["50","60"]', 'license' => 'GPLv2 or later',
            'developer_url' => 'https://dev.example/', 'description' => 'Long text',
        ], $override);
    }

    private function listing(array $items, array $query = []): string
    {
        $request = AppsRequest::fromQuery($query + ['release' => '6.0']);

        return $this->renderer->listing($request, self::categories(), $items, null);
    }

    private function dom(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($doc);
    }

    public function testTheListingProvidesWhatTheClientLooksUp(): void
    {
        $x = $this->dom($this->listing([self::item()]));

        foreach (['com-apps-searchbox', 'search-extensions', 'search-reset', 'com-apps-ordering', 'btn-grid-view', 'btn-list-view'] as $id) {
            $this->assertSame(1, $x->query('//*[@id="' . $id . '"]')->length, 'missing #' . $id);
        }

        foreach (['grid-view', 'list-view', 'grid-container', 'list-container'] as $class) {
            $this->assertGreaterThan(0, $x->query('//*[contains(concat(" ", @class, " "), " ' . $class . ' ")]')->length, 'missing .' . $class);
        }
    }

    public function testCardsOpenTheExtensionAndCategoriesAreTranscodeLinks(): void
    {
        $x = $this->dom($this->listing([self::item()]));

        $card = $x->query('//div[contains(@class,"load-extension")]/@data-url')->item(0)->nodeValue;
        $this->assertSame('index.php?option=com_apps&format=json&view=extension&id=80', $card);

        $links = $x->query('//a[contains(concat(" ", @class, " "), " transcode ")]/@href');
        $this->assertGreaterThan(2, $links->length);

        foreach ($links as $href) {
            $this->assertMatchesRegularExpression('/^index\.php\?option=com_apps&format=json&view=(dashboard|category|extension)(&id=\d+)?$/', $href->nodeValue);
        }
    }

    public function testEveryKindOfResponseCarriesTheSearchControlsTheClientWiresUp(): void
    {
        $request = AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']);
        $pages   = [
            $this->listing([self::item()]),
            $this->listing([]),
            $this->renderer->extension($request, self::item(), [], null, []),
            $this->renderer->message($request, [], 'COM_APPS_NO_RESULTS'),
        ];

        foreach ($pages as $html) {
            foreach (['com-apps-searchbox', 'search-extensions', 'search-reset', 'com-apps-ordering'] as $id) {
                $this->assertTrue(str_contains($html, 'id="' . $id . '"'), '#' . $id . ' is missing');
            }
        }
    }

    public function testNothingInsideACardIsAlsoALinkTheClientHandles(): void
    {
        $x = $this->dom($this->listing([self::item()]));

        $this->assertSame(0, $x->query('//*[contains(@class,"load-extension")]//a[contains(concat(" ", @class, " "), " transcode ")]')->length);
    }

    public function testTheOrderingSelectMarksTheCurrentChoice(): void
    {
        $x = $this->dom($this->listing([self::item()], ['ordering' => 'score']));

        $this->assertSame('score', $x->query('//select[@id="com-apps-ordering"]/option[@selected]/@value')->item(0)->nodeValue);
        $this->assertSame(
            ['', 'num_reviews', 'score', 'core_title', 'core_created_time', 'core_modified_time'],
            array_map(static fn ($n) => $n->nodeValue, iterator_to_array($x->query('//select[@id="com-apps-ordering"]/option/@value')))
        );
    }

    public function testTheSearchTermNeverReachesTheMarkup(): void
    {
        $html = $this->listing([], ['filter_search' => '"><script>x</script>']);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('script x', $html);
    }

    public function testTheSidebarOpensOnlyTheCurrentTopLevelCategory(): void
    {
        $request = AppsRequest::fromQuery(['view' => 'category', 'id' => '139']);
        $html    = $this->renderer->listing($request, self::categories(), [], self::categories()[1]);

        $this->assertStringContainsString('id=139', $html);
        $this->assertStringContainsString('class="nav-link transcode active"', $html);

        $closed = $this->renderer->listing(AppsRequest::fromQuery([]), self::categories(), [], null);
        $this->assertStringNotContainsString('id=139', $closed);
    }

    public function testAnEmptyListingSaysSo(): void
    {
        $this->assertStringContainsString('COM_APPS_NO_RESULTS', $this->listing([]));
        $this->assertStringContainsString('COM_APPS_ERROR_SEARCH_SHORT', $this->listing([], ['filter_search' => 'a']));
    }

    public function testAnUpdateServerListingGetsTheInstallButton(): void
    {
        $x = $this->dom($this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), self::item(), self::categories(), self::categories()[1], ['50' => '5', '60' => '6']));

        $button = $x->query('//button[@id="install-extension"]')->item(0);
        $this->assertNotNull($button);
        $this->assertSame('http://cdn.example/updates.xml', $button->getAttribute('data-downloadurl'));
        $this->assertSame('Akeeba Backup', $button->getAttribute('data-name'));
        $this->assertSame(0, $x->query('//*[@id="install-extension-from-external"]')->length);
        $this->assertStringContainsString('5, 6', $x->document->textContent);
    }

    public function testAListingWithOnlyADownloadLinkInstallsFromTheDevelopersSite(): void
    {
        $x = $this->dom($this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), self::item(['uses_updater' => 0, 'update_url' => '']), [], null, []));

        $this->assertSame(0, $x->query('//*[@id="install-extension"]')->length);
        $this->assertSame('https://dev.example/download', $x->query('//button[@id="install-extension-from-external"]/@data-downloadurl')->item(0)->nodeValue);
    }

    public function testAListingWithNothingToInstallFromOnlyLinksToItsPage(): void
    {
        $x = $this->dom($this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), self::item(['uses_updater' => 0, 'update_url' => '', 'download_url' => '']), [], null, []));

        $this->assertSame(0, $x->query('//button[@data-downloadurl]')->length);
        $this->assertStringContainsString('https://jed.example/extension/akeeba-backup', $x->document->saveHTML());
    }

    /**
     * @dataProvider unsafeUrls
     */
    public function testOnlyHttpUrlsReachTheInstallerAndTheLinks(string $url): void
    {
        $html = $this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), 
            self::item(['update_url' => $url, 'download_url' => $url, 'developer_url' => $url, 'logo' => $url]),
            [],
            null,
            []
        );

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data:text', $html);
        $this->assertStringNotContainsString('data-downloadurl', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public static function unsafeUrls(): array
    {
        return [
            'javascript'         => ['javascript:alert(1)'],
            'data'               => ['data:text/html,<script>1</script>'],
            'ftp'                => ['ftp://example.com/x.xml'],
            'a quote breaks out' => ['https://x.example/"onmouseover="alert(1)'],
            'whitespace'         => ['https://x.example/a b'],
            'protocol relative'  => ['//x.example/a'],
        ];
    }

    public function testNamesAndTextsAreEscaped(): void
    {
        $evil = '<img src=x onerror=alert(1)>"\'';
        $html = $this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), self::item(['name' => $evil, 'license' => $evil, 'intro' => $evil]), [], null, [])
            . $this->listing([self::item(['name' => $evil, 'intro' => $evil])]);

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('onerror=alert(1)>', $html);
    }

    public function testADescriptionKeepsOnlyWhatTheSanitizerKeeps(): void
    {
        $html = $this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), self::item(['description' => 'x']), [], null, []);

        $this->assertStringContainsString('<p>x</p>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<blockquote', $html);
        $this->assertStringContainsString('quoted', $html);
    }

    public function testLogoPathsAreResolvedAgainstTheSite(): void
    {
        $x = $this->dom($this->listing([self::item()]));

        $this->assertSame('https://jed.example/images/jed_logos/a.png', $x->query('//img/@src')->item(0)->nodeValue);
    }

    public function testEveryTagAndAttributeSurvivesTheClientsSanitizer(): void
    {
        $pages = [
            $this->listing([self::item(), self::item(['id' => 81, 'score_count' => 0, 'logo' => ''])]),
            $this->listing([], ['filter_search' => 'zz']),
            $this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), self::item(), self::categories(), self::categories()[1], ['50' => '5', '60' => '6']),
            $this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), self::item(['uses_updater' => 0]), [], null, []),
            $this->renderer->message(AppsRequest::fromQuery([]), self::categories(), 'COM_APPS_NO_RESULTS'),
        ];

        foreach ($pages as $html) {
            foreach ($this->dom($html)->query('//body//*') as $element) {
                $tag = $element->nodeName;

                if ($tag === 'body') {
                    continue;
                }

                $this->assertArrayHasKey($tag, self::ALLOWLIST, 'the sanitizer would remove <' . $tag . '> and its children');

                foreach ($element->attributes as $attribute) {
                    $name = $attribute->nodeName;
                    $ok   = in_array($name, self::ALLOWED_ATTRIBUTES_ANY, true)
                        || in_array($name, self::ALLOWLIST[$tag], true)
                        || preg_match('/^(aria|data)-[\w-]*$/i', $name) === 1;

                    $this->assertTrue($ok, 'the sanitizer would strip ' . $name . ' from <' . $tag . '>');
                }
            }
        }
    }

    public function testEveryIdTheInstalledClientNeedsIsEitherProvidedOrKnownOptional(): void
    {
        $client = dirname(__DIR__, 3) . '/joomla/media/plg_installer_webinstaller/js/client.js';

        if (!is_file($client)) {
            $this->markTestSkipped('No Joomla installation to read client.js from.');
        }

        preg_match_all('/getElementById\(\'([\w-]+)\'\)/', (string) file_get_contents($client), $m);

        // Provided by the Joomla page itself, not by the response.
        $page     = ['uploadform-web-cancel', 'uploadform-web', 'uploadform-web-install', 'uploadform-web-name-label',
            'uploadform-web-name', 'uploadform-web-url', 'jed-container', 'web-loader', 'web-loader-error', 'install_url',
            'adminForm', 'web', 'loading', 'myTab', 'joomlaapsinstallatinput'];
        // Present only when the response has the feature.
        $optional = ['com-apps-filter-joomla-version', 'install-extension', 'install-extension-from-external'];

        $html = $this->listing([self::item()]) . $this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), self::item(), [], null, [])
            . $this->renderer->extension(AppsRequest::fromQuery(['view' => 'extension', 'id' => '80']), self::item(['uses_updater' => 0]), [], null, []);

        foreach (array_unique($m[1]) as $id) {
            if (in_array($id, $page, true) || in_array($id, $optional, true)) {
                continue;
            }

            $this->assertTrue(str_contains($html, 'id="' . $id . '"'), 'client.js looks up #' . $id . ' and the response has none');
        }
    }
}
