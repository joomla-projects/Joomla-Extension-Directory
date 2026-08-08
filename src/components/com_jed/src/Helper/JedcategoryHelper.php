<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Helper;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * One definition of "how many listings does this category hold".
 *
 * Both the categories overview and a single category page put a number on a badge, and before
 * this helper existed they computed it differently: the overview from
 * {@see JedHelper::getExtensionVisibilityCondition()}, the category page from a `state = 1`
 * subquery of its own. The same category therefore carried two different numbers depending on
 * which page you were looking at.
 *
 * @since 4.0.0
 */
class JedcategoryHelper
{
    /**
     * Per-request memo, keyed by the current user id.
     *
     * The visibility condition depends on who is asking - an owner sees their own unapproved
     * listings - so the counts cannot be shared between users.
     *
     * @var   array<string, array<int, int>>
     * @since 4.0.0
     */
    private static array $counts = [];

    /**
     * How many listings each category holds, counting its whole subtree.
     *
     * A parent's badge includes everything filed under its children, because that is what the
     * number is for: it tells a visitor how much lies down that branch before they open it. A
     * top-level category such as "Extension Specific" holds 40 listings of its own and 869
     * across its 70 subcategories - the smaller number describes the branch so poorly that it
     * reads as "nearly empty".
     *
     * Only published categories take part. An unpublished one cannot be browsed to, so counting
     * its contents would promise listings that are not reachable through the tree - the same
     * rule that governs the visibility condition itself (P1-13, 4.8).
     *
     * @param bool $recursive False for a category's own listings only.
     *
     * @return array<int, int>  Category id => number of listings.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function getCounts(bool $recursive = true): array
    {
        $cacheKey = ((int) JedHelper::getUser()->id) . '.' . ($recursive ? 'r' : 'o');

        if (isset(self::$counts[$cacheKey])) {
            return self::$counts[$cacheKey];
        }

        $own = self::countOwnPerCategory();

        if (!$recursive) {
            return self::$counts[$cacheKey] = $own;
        }

        return self::$counts[$cacheKey] = self::rollUp($own);
    }

    /**
     * The number for a single category, 0 when it holds nothing.
     *
     * @param int  $categoryId The category to count.
     * @param bool $recursive  False for the category's own listings only.
     *
     * @return int
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function getCount(int $categoryId, bool $recursive = true): int
    {
        return self::getCounts($recursive)[$categoryId] ?? 0;
    }

    /**
     * Listings per category, not counting subcategories.
     *
     * One query for the whole tree rather than one per category: the JED has several hundred
     * categories and the alternative is a query per badge on a page that is mostly badges.
     *
     * @return array<int, int>  Category id => number of listings.
     *
     * @since  4.0.0
     * @throws Exception
     */
    private static function countOwnPerCategory(): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('a.catid'))
            ->select('COUNT(*) AS ' . $db->quoteName('numitems'))
            ->from($db->quoteName('#__jed_extensions', 'a'))
            ->where(JedHelper::getExtensionVisibilityCondition($db))
            ->group($db->quoteName('a.catid'));

        $counts = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $counts[(int) $row->catid] = (int) $row->numitems;
        }

        return $counts;
    }

    /**
     * Add every category's own count to each of its published ancestors.
     *
     * Done over the parent chain in PHP rather than as a self-join on the nested set: there are
     * a few hundred categories against ~15,000 listings, so the join is the expensive half of a
     * sum that is cheap to do here.
     *
     * @param array<int, int> $own Category id => own listing count.
     *
     * @return array<int, int>  Category id => listing count including all descendants.
     *
     * @since  4.0.0
     * @throws Exception
     */
    private static function rollUp(array $own): array
    {
        $parents = self::getPublishedParentMap();

        // Every published category reports a number, including the ones holding nothing, so a
        // caller can tell "no listings" apart from "not a category".
        $totals = array_fill_keys(array_keys($parents), 0);

        foreach ($own as $catId => $count) {
            // A count filed under an unpublished (or missing) category is not browsable and is
            // therefore not rolled into anything. array_key_exists, not isset: a top-level
            // category's parent is deliberately null, and isset() reads that as "absent" - which
            // silently skipped every top-level category and left the whole tree on zero.
            if (!array_key_exists($catId, $parents)) {
                continue;
            }

            $current = $catId;

            // Walk up the chain, adding the count to each published ancestor. The walk ends at
            // the first id that is not a published com_jed category - Joomla's root, or an
            // unpublished category that breaks the chain - so nothing outside the browsable tree
            // collects a total. The seen-guard keeps a corrupted tree from looping forever
            // instead of taking the request down with it.
            $seen = [];

            while ($current !== null && array_key_exists($current, $parents) && !isset($seen[$current])) {
                $seen[$current]   = true;
                $totals[$current] = ($totals[$current] ?? 0) + $count;
                $current          = $parents[$current];
            }
        }

        return $totals;
    }

    /**
     * Published com_jed categories as id => parent id, parents outside the set nulled.
     *
     * @return array<int, int|null>
     *
     * @since  4.0.0
     * @throws Exception
     */
    private static function getPublishedParentMap(): array
    {
        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $extension = 'com_jed';

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'parent_id']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('published') . ' = 1')
            ->bind(':extension', $extension);

        $rows    = $db->setQuery($query)->loadObjectList() ?: [];
        $parents = [];

        foreach ($rows as $row) {
            $parents[(int) $row->id] = (int) $row->parent_id;
        }

        // Parent ids are left as they are. A top-level category points at Joomla's root, which is
        // extension 'system' and therefore not in this map, so the walk in rollUp() stops there on
        // its own. An earlier version nulled those ends "to make the boundary explicit" and did it
        // while iterating the same array - so nulling a top-level category made its children look
        // parentless too, and the flattening cascaded down the whole tree.
        return $parents;
    }
}
