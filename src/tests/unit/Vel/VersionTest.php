<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Vel;

use Jed\Component\Vel\Administrator\Enum\ExtensionType;
use Jed\Component\Vel\Administrator\Version\Identity;
use Jed\Component\Vel\Administrator\Version\Matcher;
use Jed\Component\Vel\Administrator\Version\Version;
use Jed\Component\Vel\Administrator\Version\VersionRange;
use PHPUnit\Framework\TestCase;

/**
 * Version parsing and matching against the strings Joomla extensions actually carry.
 */
final class VersionTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function parses(): array
    {
        return [
            'plain'            => ['1.0.0', '1.0.0', false],
            'short'            => ['2.5', '2.5', false],
            'v prefix'         => ['v3.2.1', '3.2.1', false],
            'free suffix'      => ['1.0.0 Free', '1.0.0', true],
            'pro suffix'       => ['1.0.0 Pro', '1.0.0', true],
            'beta'             => ['3.2.1-beta2', '3.2.1-beta2', false],
            'rc underscore'    => ['1.0_rc1', '1.0-rc1', false],
            'version word'     => ['Version 4', '4', false],
            'patch level'      => ['1.0.0p1', '1.0.0-p1', false],
            'dev'              => ['4.0.0-dev', '4.0.0-dev', false],
            'stable in parens' => ['1.2.3 (stable)', '1.2.3', true],
        ];
    }

    /**
     * @dataProvider parses
     */
    public function testParse(string $input, string $normalised, bool $truncated): void
    {
        $version = Version::parse($input);

        self::assertNotNull($version, $input);
        self::assertSame($normalised, (string) $version, $input);
        self::assertSame($truncated, $version->truncated, $input);
    }

    public function testUnparsable(): void
    {
        self::assertNull(Version::parse('abc'));
        self::assertNull(Version::parse(''));
        self::assertNull(Version::parse('*'));
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function comparisons(): array
    {
        return [
            'equal padded'      => ['2.0', '2.0.0', 0],
            'minor'             => ['1.2', '1.10', -1],
            'beta before final' => ['1.0.0-beta', '1.0.0', -1],
            'alpha before beta' => ['1.0.0-alpha2', '1.0.0-beta1', -1],
            'rc before final'   => ['1.0.0-rc1', '1.0.0', -1],
            'rc numbers'        => ['1.0.0-rc2', '1.0.0-rc10', -1],
            'patch after final' => ['1.0.0-pl1', '1.0.0', 1],
            'v prefix ignored'  => ['v1.0', '1.0', 0],
            'free suffix'       => ['1.0.0 Free', '1.0.0', 0],
            'major'             => ['10.0.0', '9.9.9', 1],
        ];
    }

    /**
     * @dataProvider comparisons
     */
    public function testCompare(string $a, string $b, int $expected): void
    {
        self::assertSame($expected, Version::parse($a)->compare(Version::parse($b)), $a . ' <=> ' . $b);
    }

    public function testRange(): void
    {
        $range = VersionRange::fromStrings('1.0.0', '15.0.0');

        self::assertTrue($range->contains(Version::parse('1.0.0')));
        self::assertTrue($range->contains(Version::parse('15.0.0')));
        self::assertTrue($range->contains(Version::parse('15.0')));
        self::assertTrue($range->contains(Version::parse('7.3.2')));
        self::assertFalse($range->contains(Version::parse('15.0.1')));
        self::assertFalse($range->contains(Version::parse('0.9')));
        self::assertSame('1.0.0 - 15.0.0', (string) $range);
    }

    public function testOpenRanges(): void
    {
        $open = VersionRange::fromStrings('*', '*');
        self::assertTrue($open->contains(Version::parse('0.0.1')));
        self::assertTrue($open->isOpenEnded());
        self::assertSame('all versions', (string) $open);

        $upTo = VersionRange::fromStrings('*', '2.3.4');
        self::assertTrue($upTo->contains(Version::parse('2.3.4')));
        self::assertFalse($upTo->contains(Version::parse('2.3.5')));
        self::assertSame('<= 2.3.4', (string) $upTo);

        $from = VersionRange::fromStrings('3.0', '*');
        self::assertFalse($from->contains(Version::parse('2.9.9')));
        self::assertTrue($from->contains(Version::parse('99.0')));
        self::assertSame('>= 3.0', (string) $from);
    }

    public function testIdentityNormalisation(): void
    {
        $identity = Identity::normalise('Plugin', 'Modals', 'System', '0');

        self::assertSame(ExtensionType::PLUGIN, $identity->type);
        self::assertSame('modals', $identity->element);
        self::assertSame('system', $identity->folder);
        self::assertSame(0, $identity->clientId);
        self::assertSame('plugin/system/modals', (string) $identity);

        $module = Identity::normalise('module', 'mod_foo', 'ignored', 'administrator');
        self::assertNull($module->folder);
        self::assertSame(1, $module->clientId);
        self::assertSame('module/mod_foo@1', (string) $module);

        $unknown = Identity::normalise(null, null, null, null);
        self::assertFalse($unknown->isKnown());
        self::assertSame('', (string) $unknown);

        $bad = Identity::normalise('component', 'com_foo; drop', null, null);
        self::assertNull($bad->element);
    }

    public function testIdentityMatching(): void
    {
        $product = Identity::normalise('plugin', 'modals', 'system', null);

        self::assertTrue(Identity::normalise('plugin', 'modals', 'system', 0)->matches($product));
        self::assertFalse(Identity::normalise('plugin', 'modals', 'content', 0)->matches($product));
        self::assertFalse(Identity::normalise('module', 'modals', null, 0)->matches($product));

        $siteOnly = Identity::normalise('module', 'mod_foo', null, 0);
        self::assertTrue(Identity::normalise('module', 'mod_foo', null, 0)->matches($siteOnly));
        self::assertFalse(Identity::normalise('module', 'mod_foo', null, 1)->matches($siteOnly));
        self::assertTrue(Identity::normalise('module', 'mod_foo', null, null)->matches($siteOnly));
    }

    public function testMatcher(): void
    {
        $product = Identity::normalise('component', 'com_foo', null, null);
        $ranges  = [VersionRange::fromStrings('1.0', '2.3.4')];

        $installed = Identity::normalise('component', 'com_foo', null, 1);

        self::assertSame(Matcher::AFFECTED, Matcher::match($installed, '2.0.0', $product, $ranges)['match']);
        self::assertSame(Matcher::NOT_AFFECTED, Matcher::match($installed, '2.3.5', $product, $ranges)['match']);
        self::assertSame(Matcher::UNKNOWN, Matcher::match($installed, 'latest', $product, $ranges)['match']);
        self::assertSame('version_not_parsable', Matcher::match($installed, 'latest', $product, $ranges)['reason']);
        self::assertSame(Matcher::NOT_AFFECTED, Matcher::match(Identity::normalise('module', 'com_foo', null, null), '2.0.0', $product, $ranges)['match']);
        self::assertSame(Matcher::UNKNOWN, Matcher::match($installed, '2.0.0', $product, [])['match']);
        self::assertSame(Matcher::UNKNOWN, Matcher::match($installed, '2.0.0', Identity::normalise(null, null, null, null), $ranges)['match']);
    }

    public function testFixedVersionWinsOverAnExclusiveBoundStoredAsInclusive(): void
    {
        // An imported "lessThan 2.0.0" is stored as to=2.0.0 with fixed=2.0.0.
        $product   = Identity::normalise('component', 'com_foo', null, null);
        $installed = Identity::normalise('component', 'com_foo', null, null);
        $ranges    = [VersionRange::fromStrings('*', '2.0.0')];

        self::assertSame(Matcher::AFFECTED, Matcher::match($installed, '1.9.9', $product, $ranges, '2.0.0')['match']);
        self::assertSame(Matcher::NOT_AFFECTED, Matcher::match($installed, '2.0.0', $product, $ranges, '2.0.0')['match']);
        self::assertSame('at_or_above_fixed', Matcher::match($installed, '2.0.0', $product, $ranges, '2.0.0')['reason']);
        self::assertSame(Matcher::AFFECTED, Matcher::match($installed, '2.0.0', $product, $ranges)['match']);
    }
}
