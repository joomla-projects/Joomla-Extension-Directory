<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Transfer;

use Jed\Component\Jed\Administrator\Transfer\TransferService;
use PHPUnit\Framework\TestCase;

/**
 * The lookup log stores a hash of the address that was looked up. Erasing a person has to find
 * the rows about them, so the same address must always give the same value.
 *
 * @since 4.0.0
 */
final class LookupHashTest extends TestCase
{
    public function testTheCaseAndSurroundingSpaceOfAnAddressDoNotChangeItsHash(): void
    {
        $this->assertSame(
            TransferService::lookupHash('Jane.Doe@Example.test'),
            TransferService::lookupHash('  jane.doe@example.test ')
        );
    }

    public function testDifferentAddressesGiveDifferentHashes(): void
    {
        $this->assertNotSame(TransferService::lookupHash('a@example.test'), TransferService::lookupHash('b@example.test'));
    }

    public function testTheHashIsAHexDigestThatDoesNotContainTheAddress(): void
    {
        $hash = TransferService::lookupHash('jane@example.test');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        $this->assertStringNotContainsString('jane', $hash);
    }
}
