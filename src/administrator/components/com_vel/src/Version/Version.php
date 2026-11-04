<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Version;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A Joomla extension version, normalised (VEL2-PRD §8.6).
 *
 * Joomla versions are not semver. "1.0.0 Free", "v3.2.1", "3.2.1-beta2", "2.5", "1.0_rc1" and
 * "Version 4" all occur in the wild, and a checker that compares them as strings is wrong more
 * often than it is right. This class turns what it can into numeric segments plus a pre-release
 * suffix, says so when it cannot, and compares the result the way PHP's version_compare would
 * after padding - so that "2.0" and "2.0.0" are the same version, which version_compare alone
 * denies.
 *
 * @since 4.0.0
 */
final class Version
{
    /**
     * The wildcard: "*" on a range bound means unbounded.
     *
     * @since 4.0.0
     */
    public const ANY = '*';

    /**
     * Pre-release words in ascending order, the same order version_compare uses.
     *
     * @var array<string, int>
     *
     * @since 4.0.0
     */
    private const PRE_RELEASE = ['dev' => 0, 'alpha' => 1, 'a' => 1, 'beta' => 2, 'b' => 2, 'rc' => 3, 'c' => 3];

    /**
     * @param int[]  $segments   The numeric segments.
     * @param string $suffix     The pre-release or patch-level suffix, lower-cased, or ''.
     * @param string $original   What was handed in.
     * @param bool   $truncated  Whether free text after the version was dropped ("1.0.0 Free").
     *
     * @since 4.0.0
     */
    private function __construct(
        public readonly array $segments,
        public readonly string $suffix,
        public readonly string $original,
        public readonly bool $truncated
    ) {
    }

    /**
     * @param string $input A version string as found on a site or typed into a form.
     *
     * @return self|null  Null when nothing version-like can be found in it.
     *
     * @since 4.0.0
     */
    public static function parse(string $input): ?self
    {
        $original = $input;
        $input    = trim(mb_strtolower($input));

        if ($input === '' || $input === self::ANY) {
            return null;
        }

        // Find the first thing that looks like a version. Free text before ("Version 1.2",
        // "v1.2") and after ("1.2 Free", "1.2 (stable)") is discarded, and the caller is told.
        if (!preg_match('/(\d+(?:\.\d+)*)((?:[.\-_+ ]?(?:dev|alpha|beta|rc|pl|a|b|c|p)(?![a-z])\.?\d*)?)(.*)$/i', $input, $m)) {
            return null;
        }

        $segments = array_map('intval', explode('.', $m[1]));
        $suffix   = preg_replace('/^[.\-_+ ]+/', '', $m[2] ?? '');
        $suffix   = str_replace('.', '', $suffix);
        $rest     = trim($m[3] ?? '');
        $before   = trim(mb_substr($input, 0, mb_strpos($input, $m[1])));

        // Something before the number that is not a "v"/"version" prefix means the number was
        // found inside prose, which is less trustworthy than finding it at the start.
        $truncated = $rest !== '' || ($before !== '' && !preg_match('/^(v|ver|version|release)\.?$/', $before));

        return new self($segments, $suffix, $original, $truncated);
    }

    /**
     * @param Version $other The other version.
     *
     * @return int  -1, 0 or 1.
     *
     * @since 4.0.0
     */
    public function compare(Version $other): int
    {
        $a   = $this->segments;
        $b   = $other->segments;
        $len = max(\count($a), \count($b));
        $a   = array_pad($a, $len, 0);
        $b   = array_pad($b, $len, 0);

        for ($i = 0; $i < $len; $i++) {
            if ($a[$i] !== $b[$i]) {
                return $a[$i] < $b[$i] ? -1 : 1;
            }
        }

        return self::compareSuffix($this->suffix, $other->suffix);
    }

    /**
     * A pre-release is less than the release; a patch level ("pl1", "p1") is greater; two
     * pre-releases compare by word then by number.
     *
     * @param string $a One suffix.
     * @param string $b The other.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private static function compareSuffix(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }

        $ra = self::suffixRank($a);
        $rb = self::suffixRank($b);

        if ($ra[0] !== $rb[0]) {
            return $ra[0] < $rb[0] ? -1 : 1;
        }

        return $ra[1] <=> $rb[1];
    }

    /**
     * @param string $suffix The suffix.
     *
     * @return array{0:int,1:int}  [word rank, number]. Release = 10, patch level = 20.
     *
     * @since 4.0.0
     */
    private static function suffixRank(string $suffix): array
    {
        if ($suffix === '') {
            return [10, 0];
        }

        if (!preg_match('/^([a-z]+)(\d*)$/', $suffix, $m)) {
            return [10, 0];
        }

        $word   = $m[1];
        $number = (int) ($m[2] === '' ? 0 : $m[2]);

        if ($word === 'pl' || $word === 'p') {
            return [20, $number];
        }

        return [self::PRE_RELEASE[$word] ?? 5, $number];
    }

    /**
     * @return string  The normalised form, e.g. "1.0.0-beta2".
     *
     * @since 4.0.0
     */
    public function __toString(): string
    {
        return implode('.', $this->segments) . ($this->suffix !== '' ? '-' . $this->suffix : '');
    }
}
