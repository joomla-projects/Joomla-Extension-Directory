<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Link;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Which URL columns are checked, with which validator, and how much a failure weighs.
 *
 * **All of them are checked**, which is `P1-09`'s own proposal and the right one: the weighting
 * does the work a selection would have done. A dead `documentation_url` is then *visible* during
 * moderation without ever being an alarm, whereas leaving it out of the pass means nobody ever
 * finds out.
 *
 * The weights are ratios, not scores. A field at 1.0 reaches the developer threshold at the
 * documented number of consecutive failures; a field at 0.5 needs twice as many. What the
 * ordering says, read top to bottom:
 *
 *  - a **download link** that is gone makes the listing useless - that is the one thing a visitor
 *    came for, and 5,582 of the 5,583 live listings have one;
 *  - an **update server** that is gone means users are not being offered updates they should be
 *    getting, which is a security matter and not merely a broken link (5.3);
 *  - the **website** and **support** links are what somebody does when the extension misbehaves;
 *  - **demo**, **documentation**, **licence terms** and **source** are worth knowing about and
 *    worth nobody being woken for.
 *
 * @since 4.1.0
 */
final class LinkField
{
    /**
     * column => [validator key, weight].
     *
     * `internal_download_url` is deliberately absent: it is the JED's own copy, it is populated
     * for 8 of 5,583 listings, and it is not a promise made to a visitor.
     *
     * @var array<string, array{0: string, 1: float}>
     *
     * @since 4.1.0
     */
    public const FIELDS = [
        'download_url'      => ['download', 1.0],
        'update_url'        => ['updateserver', 1.0],
        'developer_url'     => ['reachable', 0.75],
        'support_url'       => ['reachable', 0.75],
        'demo_url'          => ['reachable', 0.5],
        'documentation_url' => ['reachable', 0.5],
        'license_url'       => ['reachable', 0.5],
        'changelog_url'     => ['changelog', 0.5],
        'git_url'           => ['git', 0.5],
    ];

    /**
     * The validator key for a column.
     *
     * @param string $field The column name.
     *
     * @return string  Empty when the column is not checked.
     *
     * @since 4.1.0
     */
    public static function validator(string $field): string
    {
        return self::FIELDS[$field][0] ?? '';
    }

    /**
     * How heavily a failure on this column counts.
     *
     * @param string $field The column name.
     *
     * @return float
     *
     * @since 4.1.0
     */
    public static function weight(string $field): float
    {
        return self::FIELDS[$field][1] ?? 0.0;
    }

    /**
     * Whether this many consecutive failures on this column reaches a threshold.
     *
     * Weighted, so the threshold is expressed once as "three failed runs" and each field's weight
     * decides what that means for it. And because the cadence is a fixed interval, three failed
     * runs is also a *duration* - which is what 4.9 asks for when it says a threshold must be a
     * count over a period rather than a bare count. A weekend of hoster downtime cannot reach it.
     *
     * @param string $field     The column name.
     * @param int    $failCount Consecutive failures.
     * @param int    $threshold The configured threshold.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public static function reaches(string $field, int $failCount, int $threshold): bool
    {
        $weight = self::weight($field);

        if ($weight <= 0.0) {
            return false;
        }

        return $failCount >= (int) ceil($threshold / $weight);
    }

    /**
     * Every column that is checked.
     *
     * @return string[]
     *
     * @since 4.1.0
     */
    public static function all(): array
    {
        return array_keys(self::FIELDS);
    }
}
