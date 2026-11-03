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
 * The check API's answer for one installed extension against one advisory product.
 *
 * Three answers, not two: "affected", "not affected", and "unknown" when the site's version
 * cannot be parsed or the product has no identity yet. A checker that turned "unknown" into "no"
 * would be telling site owners they are safe on the strength of a typo (VEL2-PRD §8.6).
 *
 * @since 4.0.0
 */
final class Matcher
{
    public const AFFECTED     = 'affected';
    public const NOT_AFFECTED = 'not_affected';
    public const UNKNOWN      = 'unknown';

    /**
     * @param Identity       $installed    The installed extension's identity.
     * @param string         $version      The installed version string.
     * @param Identity       $product      The advisory product's identity.
     * @param VersionRange[] $ranges       The product's affected ranges.
     * @param string         $fixedVersion The product's fixed version, or ''.
     *
     * @return array{match:string, reason:string}
     *
     * @since 4.0.0
     */
    public static function match(Identity $installed, string $version, Identity $product, array $ranges, string $fixedVersion = ''): array
    {
        if (!$product->isKnown()) {
            return ['match' => self::UNKNOWN, 'reason' => 'product_identity_unknown'];
        }

        if (!$installed->matches($product)) {
            return ['match' => self::NOT_AFFECTED, 'reason' => 'identity_mismatch'];
        }

        if ($ranges === []) {
            return ['match' => self::UNKNOWN, 'reason' => 'no_version_ranges'];
        }

        $parsed = Version::parse($version);

        if ($parsed === null) {
            return ['match' => self::UNKNOWN, 'reason' => 'version_not_parsable'];
        }

        // At or above the fixed version is safe whatever the ranges say: a range imported with an
        // exclusive upper bound is stored with that bound as its "to", and this is what keeps the
        // fixed release itself from being flagged.
        $fixed = $fixedVersion !== '' ? Version::parse($fixedVersion) : null;

        if ($fixed !== null && $parsed->compare($fixed) >= 0) {
            return ['match' => self::NOT_AFFECTED, 'reason' => 'at_or_above_fixed'];
        }

        foreach ($ranges as $range) {
            if ($range->contains($parsed)) {
                return ['match' => self::AFFECTED, 'reason' => $parsed->truncated ? 'version_truncated' : 'in_range'];
            }
        }

        return ['match' => self::NOT_AFFECTED, 'reason' => $parsed->truncated ? 'version_truncated' : 'out_of_range'];
    }
}
