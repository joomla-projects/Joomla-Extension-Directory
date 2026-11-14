<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Feeds;

use Jed\Component\Vel\Administrator\Feed\CvelistClient;
use RuntimeException;

/**
 * CvelistClient::parseSlice(): the strict, slice-at-a-time reader of deltaLog.json.
 *
 * "Strict" is the point: a log that did not load must fail loudly, never look like an empty one
 * (which used to end a run as "ok" and move it past everything in its window).
 */
final class DeltaLogParserTest extends FeedTestCase
{
    private static function head(): string
    {
        return (string) file_get_contents(JPATH_ROOT . '/vel_tests/fixtures/vel/cve/cvelist-deltalog-head.json');
    }

    private static function throws(callable $parse): ?string
    {
        try {
            $parse();

            return null;
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }
    }

    public function testSlicesOfAnySizeReadTheSameEntriesAsTheWholeFile(): void
    {
        $head  = self::head();
        $whole = CvelistClient::parseHead($head, true);

        self::assertNotEmpty($whole);

        foreach ([64, 500, 2000, 7000, \strlen($head)] as $size) {
            $offset = 0;
            $got    = [];
            $bytes  = $size;

            for ($guard = 0; $offset !== null && $guard < 10000; $guard++) {
                $slice = CvelistClient::parseSlice(substr($head, $offset, $bytes), $offset === 0);

                self::assertTrue($slice['aligned'], "slice at $offset of $size-byte slices is aligned");

                if ($slice['next'] === 0 && $slice['entries'] === []) {
                    $bytes *= 2;   // one entry is bigger than the slice: ask for more

                    continue;
                }

                array_push($got, ...$slice['entries']);

                $bytes  = $size;
                $offset = $slice['next'] === null || $offset + $slice['next'] >= \strlen($head) ? null : $offset + $slice['next'];
            }

            self::assertSame($whole, $got, "$size-byte slices reproduce the whole log");
        }
    }

    public function testACutSliceSaysWhereTheUnfinishedEntryStarts(): void
    {
        $head  = self::head();
        $slice = CvelistClient::parseSlice(substr($head, 0, (int) (\strlen($head) * 0.6)), true);

        self::assertNotNull($slice['next']);
        self::assertSame('{', $head[$slice['next']]);
        self::assertLessThan(\count(CvelistClient::parseHead($head, true)), \count($slice['entries']));
    }

    public function testBracesAndQuotesInsideStringsDoNotConfuseIt(): void
    {
        $text  = '[{"fetchTime":"2026-01-01T00:00:00Z","new":[{"cveId":"CVE-2026-1","note":"a } ] \\" {[ b"}],"updated":[]},{"fetchTime":"2026-01-01T00:00:00Z","new":[]}]';
        $slice = CvelistClient::parseSlice($text, true);

        self::assertCount(2, $slice['entries']);
        self::assertNull($slice['next']);
    }

    public function testALogThatDidNotLoadThrowsInsteadOfLookingEmpty(): void
    {
        $broken = [
            'an HTML page'                => '<html>Service Unavailable</html>',
            'a corrupt entry'             => '[{"fetchTime":"2026-01-01T00:00:00Z"},{"fetchTime" "x"},{"fetchTime":"2026-01-01T00:00:00Z"}]',
            'an entry with no fetchTime'  => '[{"new":[]}]',
            'garbage between entries'     => '[{"fetchTime":"2026-01-01T00:00:00Z"} x {"fetchTime":"2026-01-01T00:00:00Z"}]',
            'an unreadable fetchTime'     => '[{"fetchTime":"yesterday-ish?"}]',
            'a malformed list of changes' => '[{"fetchTime":"2026-01-01T00:00:00Z","new":"CVE-1"}]',
        ];

        foreach ($broken as $label => $text) {
            self::assertNotNull(self::throws(static fn () => CvelistClient::parseSlice($text, true)), "$label throws");
        }
    }

    public function testAnEmptyLogIsAnEmptyListNotAnError(): void
    {
        self::assertSame(['entries' => [], 'next' => null, 'aligned' => true], CvelistClient::parseSlice("[\n]", true));
    }

    public function testAContinuationThatDoesNotStartAtAnEntryIsMisaligned(): void
    {
        $head  = self::head();
        $inner = strpos($head, '{', strpos($head, '"new"'));

        self::assertFalse(CvelistClient::parseSlice(substr($head, $inner, 3000), false)['aligned'], 'starting at an inner object');
        self::assertFalse(CvelistClient::parseSlice(substr($head, 40, 3000), false)['aligned'], 'starting inside a string');
    }
}
