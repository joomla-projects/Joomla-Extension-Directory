<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Apps;

use Jed\Component\Apps\Site\Service\AppsRequest;
use Jed\Component\Apps\Site\Service\InvalidRequestException;
use PHPUnit\Framework\TestCase;

/**
 * What the endpoint accepts from the Install from Web client, and what shares a cache entry.
 *
 * @since 4.0.0
 */
final class AppsRequestTest extends TestCase
{
    public function testDefaultsToTheDashboardForTheClientsMajorVersion(): void
    {
        $r = AppsRequest::fromQuery(['release' => '6.1']);

        $this->assertSame('dashboard', $r->view);
        $this->assertSame(0, $r->id);
        $this->assertSame(6, $r->major);
        $this->assertSame('', $r->ordering);
    }

    /**
     * @dataProvider invalidQueries
     */
    public function testRefusesWhatCannotBeAnswered(array $query, string $key): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage($key);

        AppsRequest::fromQuery($query);
    }

    public static function invalidQueries(): array
    {
        return [
            'unknown view'           => [['view' => 'admin'], 'COM_APPS_ERROR_VIEW'],
            'view with a path'       => [['view' => '../x'], 'COM_APPS_ERROR_VIEW'],
            'category without id'    => [['view' => 'category'], 'COM_APPS_ERROR_ID'],
            'extension id zero'      => [['view' => 'extension', 'id' => '0'], 'COM_APPS_ERROR_ID'],
            'id with letters'        => [['view' => 'category', 'id' => 'abc'], 'COM_APPS_ERROR_ID'],
            'id with sql'            => [['view' => 'extension', 'id' => '1 OR 1=1'], 'COM_APPS_ERROR_ID'],
            'negative id'            => [['view' => 'extension', 'id' => '-5'], 'COM_APPS_ERROR_ID'],
            'array instead of value' => [['view' => 'extension', 'id' => ['1']], 'COM_APPS_ERROR_ID'],
        ];
    }

    public function testAnUnknownOrderingFallsBackToTheDefault(): void
    {
        $this->assertSame('', AppsRequest::fromQuery(['ordering' => 'a.name; DROP TABLE x'])->ordering);
        $this->assertSame('score', AppsRequest::fromQuery(['ordering' => 'score'])->ordering);
    }

    public function testEveryOrderingIsAFixedColumnList(): void
    {
        foreach (AppsRequest::ORDERINGS as $sql) {
            $this->assertMatchesRegularExpression('/^[a-z_., ]+( (ASC|DESC))?(, [a-z_.]+ (ASC|DESC))*$/i', $sql);
        }
    }

    /**
     * @dataProvider searchTerms
     */
    public function testSearchTermsAreNormalisedLikeTheClientWritesThem(string $raw, string $expected): void
    {
        $this->assertSame($expected, AppsRequest::normaliseSearch($raw));
    }

    public static function searchTerms(): array
    {
        return [
            'client format'   => ['akeeba_backup', 'akeeba backup'],
            'upper case'      => ['SEO', 'seo'],
            'url encoded'     => ['site%20map', 'site map'],
            'wildcards gone'  => ['100%_free', '100 free'],
            'quotes and tags' => ['<b>"x"</b>', 'b x b'],
            'unicode letters' => ['café', 'café'],
            'only punctuation' => ['%%%', ''],
        ];
    }

    public function testASearchIsAlwaysADashboardRequest(): void
    {
        $r = AppsRequest::fromQuery(['view' => 'category', 'id' => '23', 'filter_search' => 'backup']);

        $this->assertSame('dashboard', $r->view);
        $this->assertSame(0, $r->id);
        $this->assertTrue($r->isSearch());
    }

    public function testASearchIsLimitedInLengthAndFlaggedWhenTooShort(): void
    {
        $this->assertSame(AppsRequest::SEARCH_MAX_LENGTH, mb_strlen(AppsRequest::fromQuery(['filter_search' => str_repeat('a', 500)])->search));
        $this->assertTrue(AppsRequest::fromQuery(['filter_search' => 'a'])->searchTooShort);
        $this->assertFalse(AppsRequest::fromQuery(['filter_search' => 'ab'])->searchTooShort);
        $this->assertFalse(AppsRequest::fromQuery(['filter_search' => 'a'], 1)->searchTooShort);
    }

    /**
     * @dataProvider versions
     */
    public function testTheVersionFilter(array $query, ?int $expected): void
    {
        $this->assertSame($expected, AppsRequest::fromQuery($query)->major);
    }

    public static function versions(): array
    {
        return [
            'current is the release'  => [['release' => '5.4', 'filter_version' => 'current'], 5],
            'explicit major'          => [['release' => '5.4', 'filter_version' => '4'], 4],
            'all versions'            => [['release' => '5.4', 'filter_version' => 'all'], null],
            'nonsense filter'         => [['release' => '5.4', 'filter_version' => 'x;y'], 5],
            'no release'              => [[], null],
            'nonsense release'        => [['release' => 'abc'], null],
            'absurd major'            => [['filter_version' => '99999'], null],
        ];
    }

    public function testRequestsThatAnswerTheSameShareACacheKey(): void
    {
        $a = AppsRequest::fromQuery(['view' => 'category', 'id' => '23', 'release' => '6.0', 'dev_level' => 'MA==', 'list' => 'grid', 'lang' => 'ZW4tR0I=']);
        $b = AppsRequest::fromQuery(['view' => 'category', 'id' => '23', 'release' => '6.3', 'dev_level' => 'Mg==', 'list' => 'list', 'lang' => 'ZnItRlI=']);

        $this->assertSame($a->cacheKey(), $b->cacheKey());
    }

    public function testRequestsThatAnswerDifferentlyDoNot(): void
    {
        $keys = [
            AppsRequest::fromQuery(['release' => '6.0'])->cacheKey(),
            AppsRequest::fromQuery(['release' => '5.4'])->cacheKey(),
            AppsRequest::fromQuery(['release' => '6.0', 'ordering' => 'score'])->cacheKey(),
            AppsRequest::fromQuery(['release' => '6.0', 'filter_search' => 'seo'])->cacheKey(),
            AppsRequest::fromQuery(['release' => '6.0', 'view' => 'category', 'id' => '5'])->cacheKey(),
            AppsRequest::fromQuery(['release' => '6.0', 'view' => 'extension', 'id' => '5'])->cacheKey(),
        ];

        $this->assertCount(count($keys), array_unique($keys));
    }

    public function testACacheKeyIsSafeAsAFileName(): void
    {
        $key = AppsRequest::fromQuery(['filter_search' => '../../etc/passwd', 'release' => '6.0'])->cacheKey();

        $this->assertMatchesRegularExpression('/^[a-z0-9_-]+$/i', $key);
    }
}
