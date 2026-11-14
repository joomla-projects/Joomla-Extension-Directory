<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Queue;

use Jed\Component\Jed\Administrator\Queue\QueueService;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\QueryInterface;
use Joomla\Tests\Unit\UnitTestCase;
use ReflectionMethod;

/**
 * Reclaiming a stuck job must not undo a result that arrived while it was being reclaimed.
 *
 * @since 4.0.0
 */
final class QueueReclaimTest extends UnitTestCase
{
    /**
     * A real query object whose limit handling returns the SQL, so it can be turned into a string.
     */
    private function queryFor(DatabaseInterface $db): QueryInterface
    {
        return new class ($db) extends DatabaseQuery {
            public function groupConcat($expression, $separator = ',')
            {
                return 'GROUP_CONCAT(' . $expression . ')';
            }

            public function processLimit($query, $limit, $offset = 0)
            {
                return $query;
            }
        };
    }

    public function testTheResetOnlyTouchesTheRowThatWasListedAsStuck(): void
    {
        $executed = [];

        $db = $this->createMock(DatabaseInterface::class);
        $db->method('getQuery')->willReturnCallback(fn () => $this->queryFor($db));
        $db->method('quoteName')->willReturnArgument(0);
        $db->method('quote')->willReturnCallback(fn ($v) => "'" . $v . "'");
        $db->method('setQuery')->willReturnCallback(function ($query) use (&$executed, $db) {
            $executed[] = (string) $query;

            return $db;
        });
        $db->method('loadObjectList')->willReturn([(object) ['id' => 7, 'attempts' => 1]]);

        $service = new QueueService($db);
        (new ReflectionMethod($service, 'reclaimStuckJobs'))->invoke($service, 600);

        $update = array_values(array_filter($executed, static fn ($sql) => str_starts_with(ltrim($sql), 'UPDATE')));

        $this->assertCount(1, $update);
        $this->assertStringContainsString('status', $update[0]);
        $this->assertMatchesRegularExpression('/WHERE.*\bid\b.*AND.*\bstatus\b.*AND.*\battempts\b.*AND.*\bstarted_time\b/s', $update[0], 'the reset must be conditional on the row still being the stuck one');
    }
}
