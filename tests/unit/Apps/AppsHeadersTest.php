<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Apps;

use Jed\Component\Apps\Site\Service\AppsHeaders;
use Jed\Component\Apps\Site\Service\AppsResult;
use PHPUnit\Framework\TestCase;

/**
 * What a CDN and a browser are told about a response.
 *
 * @since 4.0.0
 */
final class AppsHeadersTest extends TestCase
{
    public function testBrowsingIsPublicAndCachedAtTheEdge(): void
    {
        $r = AppsHeaders::build(AppsResult::page('<p>x</p>', AppsResult::KIND_BROWSE), 3600, 300);

        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('public', $r['headers']['Cache-Control']);
        $this->assertStringContainsString('s-maxage=3600', $r['headers']['Cache-Control']);
        $this->assertStringContainsString('stale-while-revalidate=', $r['headers']['Cache-Control']);
        $this->assertStringContainsString('stale-if-error=', $r['headers']['Cache-Control']);
    }

    public function testSearchIsCachedForLess(): void
    {
        $r = AppsHeaders::build(AppsResult::page('<p>x</p>', AppsResult::KIND_SEARCH), 3600, 300);

        $this->assertStringContainsString('s-maxage=300', $r['headers']['Cache-Control']);
    }

    public function testAnErrorIsShortLivedAndHasNoEtag(): void
    {
        $r = AppsHeaders::build(AppsResult::error(404, 'nope'), 3600, 300);

        $this->assertSame(404, $r['status']);
        $this->assertStringContainsString('s-maxage=60', $r['headers']['Cache-Control']);
        $this->assertArrayNotHasKey('ETag', $r['headers']);
    }

    public function testNothingThatPreventsSharingIsSent(): void
    {
        foreach ([AppsResult::page('x', AppsResult::KIND_BROWSE), AppsResult::error(400, 'x')] as $result) {
            $headers = array_change_key_case(AppsHeaders::build($result, 3600, 300)['headers']);

            $this->assertArrayNotHasKey('set-cookie', $headers);
            $this->assertSame('Accept-Encoding', $headers['vary']);
            $this->assertStringNotContainsString('no-store', $headers['cache-control']);
            $this->assertStringNotContainsString('private', $headers['cache-control']);
        }
    }

    public function testCrossOriginReadsAreAllowedBecauseTheClientRunsInTheBackend(): void
    {
        $r = AppsHeaders::build(AppsResult::page('x', AppsResult::KIND_BROWSE), 3600, 300);

        $this->assertSame('*', $r['headers']['Access-Control-Allow-Origin']);
        $this->assertStringStartsWith('application/json', $r['headers']['Content-Type']);
    }

    public function testAMatchingEtagGetsAnEmpty304(): void
    {
        $page  = AppsResult::page('<p>x</p>', AppsResult::KIND_BROWSE);
        $first = AppsHeaders::build($page, 3600, 300);
        $again = AppsHeaders::build($page, 3600, 300, 'W/"other", ' . $first['headers']['ETag']);

        $this->assertSame(304, $again['status']);
        $this->assertSame('', $again['body']);
        $this->assertSame($first['headers']['ETag'], $again['headers']['ETag']);
    }

    public function testADifferentEtagGetsTheBody(): void
    {
        $r = AppsHeaders::build(AppsResult::page('<p>x</p>', AppsResult::KIND_BROWSE), 3600, 300, '"stale"');

        $this->assertSame(200, $r['status']);
        $this->assertNotSame('', $r['body']);
    }

    public function testTheBodyIsTheJsonTheClientParses(): void
    {
        $decoded = json_decode(AppsResult::page('<p>é/x</p>', AppsResult::KIND_BROWSE)->body, true);

        $this->assertTrue($decoded['success']);
        $this->assertSame('<p>é/x</p>', $decoded['data']['html']);
        $this->assertArrayHasKey('pluginuptodate', $decoded['data']);
    }
}
