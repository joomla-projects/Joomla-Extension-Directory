<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Apps;

use Jed\Component\Apps\Site\Service\AppsCatalogue;
use Jed\Component\Apps\Site\Service\AppsRenderer;
use Jed\Component\Apps\Site\Service\AppsRequest;
use Jed\Component\Apps\Site\Service\AppsResult;
use Jed\Component\Apps\Site\Service\AppsService;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * What the service answers for each kind of request, with the catalogue replaced by fixed data.
 *
 * @since 4.0.0
 */
final class AppsServiceTest extends TestCase
{
    private function service(array $items, ?object $item): AppsService
    {
        $catalogue = new class ($this->createMock(DatabaseInterface::class), $items, $item) extends AppsCatalogue {
            public array $seen = [];

            public function __construct(DatabaseInterface $db, private array $fixedItems, private ?object $fixedItem)
            {
                parent::__construct($db, 10);
            }

            public function categories(): array
            {
                return [(object) ['id' => 23, 'title' => 'Access', 'parent_id' => 1, 'level' => 1, 'lft' => 2, 'rgt' => 3]];
            }

            public function items(AppsRequest $request, array $categories = []): array
            {
                $this->seen[] = $request;

                return $this->fixedItems;
            }

            public function item(int $id): ?object
            {
                return $this->fixedItem;
            }

            public function versionLabels(): array
            {
                return ['60' => '6'];
            }
        };

        $translate = static fn (string $key, ...$args): string => $key;
        $renderer  = new AppsRenderer($translate, static fn (object $i): string => 'https://jed.example/x', static fn (string $t): string => $t, 'https://jed.example/');

        return new AppsService($catalogue, $renderer, $translate);
    }

    private static function item(): object
    {
        return (object) [
            'id' => 1, 'name' => 'X', 'alias' => 'x', 'catid' => 23, 'intro' => 'i', 'type' => 'free', 'extension_types' => '["com"]',
            'logo' => '', 'score_overall' => '0', 'score_count' => 0, 'extension_version' => '1', 'created' => null,
            'uses_updater' => 0, 'update_url' => '', 'download_url' => '', 'joomla_versions' => '["60"]', 'license' => '',
            'developer_url' => '', 'description' => '',
        ];
    }

    public function testTheDashboardIsABrowseAnswer(): void
    {
        $result = $this->service([self::item()], null)->handle(AppsRequest::fromQuery(['release' => '6.0']));

        $this->assertSame(200, $result->status);
        $this->assertSame(AppsResult::KIND_BROWSE, $result->kind);
        $this->assertStringContainsString('load-extension', $result->body);
    }

    public function testASearchIsASearchAnswer(): void
    {
        $result = $this->service([], null)->handle(AppsRequest::fromQuery(['filter_search' => 'seo']));

        $this->assertSame(AppsResult::KIND_SEARCH, $result->kind);
        $this->assertStringContainsString('COM_APPS_NO_RESULTS', $result->body);
    }

    public function testATooShortSearchNeverReachesTheDatabase(): void
    {
        $service = $this->service([self::item()], null);
        $result  = $service->handle(AppsRequest::fromQuery(['filter_search' => 'a']));

        $this->assertStringContainsString('COM_APPS_ERROR_SEARCH_SHORT', $result->body);
        $this->assertStringNotContainsString('load-extension', $result->body);
    }

    public function testAnUnknownCategoryIsA404(): void
    {
        $result = $this->service([], null)->handle(AppsRequest::fromQuery(['view' => 'category', 'id' => '999']));

        $this->assertSame(404, $result->status);
        $this->assertSame(AppsResult::KIND_ERROR, $result->kind);
    }

    public function testAnExtensionThatIsNotVisibleIsA404(): void
    {
        $result = $this->service([], null)->handle(AppsRequest::fromQuery(['view' => 'extension', 'id' => '5']));

        $this->assertSame(404, $result->status);
        $this->assertSame('COM_APPS_ERROR_NOT_FOUND', json_decode($result->body, true)['message']);
    }

    public function testAVisibleExtensionIsRendered(): void
    {
        $result = $this->service([], self::item())->handle(AppsRequest::fromQuery(['view' => 'extension', 'id' => '1']));

        $this->assertSame(200, $result->status);
        $this->assertStringContainsString('<h2>X</h2>', json_decode($result->body, true)['data']['html']);
    }

    public function testAnAnswerSurvivesTheCacheRoundTrip(): void
    {
        $result = $this->service([self::item()], null)->handle(AppsRequest::fromQuery([]));
        $again  = AppsResult::fromStored(json_decode(json_encode($result->toStored()), true));

        $this->assertSame($result->body, $again->body);
        $this->assertSame($result->kind, $again->kind);
        $this->assertSame($result->status, $again->status);
    }
}
