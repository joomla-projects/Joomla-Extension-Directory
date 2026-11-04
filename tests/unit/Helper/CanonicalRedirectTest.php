<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Helper;

use Jed\Component\Jed\Site\Helper\CanonicalRedirect;
use PHPUnit\Framework\TestCase;

/**
 * A listing asked for at an old alias or category is sent to its current address; a request that
 * is already right is left alone, so the redirect can never loop.
 *
 * @since 4.0.0
 */
final class CanonicalRedirectTest extends TestCase
{
    private const CURRENT = '/index.php/communication/live-support/28-required-band';

    public function testTheCurrentAddressIsLeftAlone(): void
    {
        $this->assertNull(CanonicalRedirect::target(self::CURRENT, self::CURRENT));
        $this->assertNull(CanonicalRedirect::target(self::CURRENT, self::CURRENT, 'utm_source=x'));
    }

    public function testAnOldAliasIsSentToTheCurrentAddress(): void
    {
        $this->assertSame(
            self::CURRENT,
            CanonicalRedirect::target('/index.php/communication/live-support/28-old-name', self::CURRENT)
        );
    }

    public function testTheBareIdAndAnOldCategoryAreSentToo(): void
    {
        $this->assertSame(self::CURRENT, CanonicalRedirect::target('/index.php/communication/live-support/28', self::CURRENT));
        $this->assertSame(self::CURRENT, CanonicalRedirect::target('/index.php/e-commerce/28-required-band', self::CURRENT));
    }

    public function testTheQueryStringSurvivesTheRedirect(): void
    {
        $this->assertSame(
            self::CURRENT . '?utm_source=x&tab=reviews',
            CanonicalRedirect::target('/index.php/communication/live-support/28-old-name', self::CURRENT, 'utm_source=x&tab=reviews')
        );
    }

    public function testEncodingAndATrailingSlashAreNotADifferentAddress(): void
    {
        $this->assertNull(CanonicalRedirect::target(self::CURRENT . '/', self::CURRENT));
        $this->assertNull(CanonicalRedirect::target('/index.php/communication/live%2Dsupport/28-required-band', self::CURRENT));
    }

    public function testWithoutACanonicalAddressNothingHappens(): void
    {
        $this->assertNull(CanonicalRedirect::target('/anything', ''));
    }
}
