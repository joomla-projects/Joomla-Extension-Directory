<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Transfer;

use Jed\Component\Jed\Administrator\Transfer\TransferService;
use Jed\Component\Jed\Administrator\Transfer\TransferState;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\QueryInterface;
use Joomla\Tests\Unit\UnitTestCase;
use RuntimeException;

/**
 * Both parties can confirm a transfer at the same moment. The write has to be conditional, so the
 * later one is retried from fresh data instead of overwriting the earlier one.
 *
 * @since 4.0.0
 */
final class TransferConfirmRaceTest extends UnitTestCase
{
    private const TOKEN = 'recipient-token';

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

    /**
     * @param int[]    $affected Rows affected by each successive state update.
     * @param string[] $updates  Receives the SQL of every update.
     * @param int      $reads    Receives how many times the transfer row was read.
     */
    private function service(array $affected, array &$updates, int &$reads): TransferService
    {
        $row = [
            'id'              => 5,
            'extension_id'    => 10,
            'from_user_id'    => 1,
            'to_user_id'      => 2,
            'state'           => 'pending',
            'expires'         => gmdate('Y-m-d H:i:s', time() + 3600),
            'from_token_hash' => hash('sha256', 'owner-token'),
            'to_token_hash'   => hash('sha256', self::TOKEN),
        ];

        $db = $this->createMock(DatabaseInterface::class);
        $db->method('getQuery')->willReturnCallback(fn () => $this->queryFor($db));
        $db->method('quoteName')->willReturnArgument(0);
        $db->method('quote')->willReturnCallback(fn ($v) => "'" . $v . "'");
        $db->method('setQuery')->willReturnCallback(function ($query) use (&$updates, $db) {
            if (str_starts_with(ltrim((string) $query), 'UPDATE')) {
                $updates[] = (string) $query;
            }

            return $db;
        });
        $db->method('loadAssoc')->willReturnCallback(function () use ($row, &$reads) {
            $reads++;

            return $row;
        });
        $db->method('getAffectedRows')->willReturnOnConsecutiveCalls(...$affected);

        return new TransferService($db);
    }

    public function testAConfirmationThatLostTheRaceIsRetriedFromFreshData(): void
    {
        $updates = [];
        $reads   = 0;

        $state = $this->service([0, 1], $updates, $reads)->confirm(5, self::TOKEN, 2);

        $this->assertSame(TransferState::TO_CONFIRMED, $state);
        $this->assertSame(2, $reads, 'the transfer is read again after the other side got in first');
        $this->assertCount(2, $updates);
    }

    public function testTheStateUpdateIsConditionalOnTheStateAndTheTokenThatWereRead(): void
    {
        $updates = [];
        $reads   = 0;

        $this->service([1], $updates, $reads)->confirm(5, self::TOKEN, 2);

        $this->assertCount(1, $updates);
        $this->assertMatchesRegularExpression('/WHERE.*\bid\b.*AND.*\bstate\b\s*=\s*\'pending\'.*AND.*to_token_hash\s*=\s*\'' . hash('sha256', self::TOKEN) . '\'/s', $updates[0]);
    }

    public function testItGivesUpAfterAFewLostRaces(): void
    {
        $updates = [];
        $reads   = 0;

        $this->expectException(RuntimeException::class);

        try {
            $this->service([0, 0, 0, 0], $updates, $reads)->confirm(5, self::TOKEN, 2);
        } finally {
            $this->assertSame(3, $reads);
        }
    }
}
