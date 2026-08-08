<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Browse;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The browse lists the JED offers, defined once.
 *
 * These exist in three places at once - as a menu item, as a module instance, and as an ordering
 * in the list model - and the whole point of this enum is that all three name the same thing. The
 * legacy site had them as hand-built `/browse/<list>` pages and the current code has them as menu
 * items carrying `list_fullordering` in a query string, which is why "New" ended up meaning two
 * different things depending on where you clicked.
 *
 * **`RECENTLY_ADDED` and `NOTEWORTHY` are both here on purpose.** The legacy `New & Noteworthy`
 * list is computed from hits over a two-week window, and the current "New" preset sorts by
 * creation date - a different list wearing a very similar name. Both are worth having: one
 * answers "what has just arrived", the other "what are people actually looking at". Only the
 * naming was the problem, so the date-based one is `Recently Added` and `New & Noteworthy` keeps
 * the name it is publicly known by.
 *
 * @since 4.1.0
 */
enum BrowseList: string
{
    case TOP_RATED         = 'top-rated';
    case MOST_REVIEWED     = 'most-reviewed';
    case RECENTLY_ADDED    = 'recently-added';
    case RECENTLY_UPDATED  = 'recently-updated';
    case NOTEWORTHY        = 'new-noteworthy';

    /**
     * How many days of hit data `NOTEWORTHY` looks at.
     *
     * Two weeks, which is what the legacy `view_jed_new_noteworthy` used. Kept rather than
     * improved: the list is publicly known and people have expectations of it, and there is no
     * evidence that a different window would be better.
     *
     * @since 4.1.0
     */
    public const NOTEWORTHY_DAYS = 14;

    /**
     * The ordering this list applies, as `column direction`.
     *
     * `NOTEWORTHY` has none of its own - it is not a sort of the listing table but a join against
     * the hit aggregate, so the model builds it rather than ordering by a column.
     *
     * @return string
     *
     * @since 4.1.0
     */
    public function ordering(): string
    {
        return match ($this) {
            self::TOP_RATED        => 'score_overall DESC',
            self::MOST_REVIEWED    => 'score_count DESC',
            self::RECENTLY_ADDED   => 'a.created DESC',
            self::RECENTLY_UPDATED => 'a.modified DESC',
            self::NOTEWORTHY       => 'noteworthy DESC',
        };
    }

    /**
     * The tie-break applied after this list's own ordering.
     *
     * Without one these lists are not deterministic, and that is not theoretical: several hundred
     * listings share a `score_overall` of 5.00, so "Top Rated" was returning a different set on
     * the page and in the module - two things with the same name disagreeing in front of the
     * visitor. MySQL is free to order equal rows however it likes, and it does.
     *
     * The tie-break is not arbitrary either: among equally rated listings the one more people
     * have reviewed goes first, and `id` settles the rest so the answer is stable across requests.
     *
     * @return string
     *
     * @since 4.1.0
     */
    public function tieBreak(): string
    {
        return match ($this) {
            self::TOP_RATED => 'a.score_count DESC, a.id ASC',
            default         => 'a.id ASC',
        };
    }

    /**
     * The language key for this list's name.
     *
     * @return string
     *
     * @since 4.1.0
     */
    public function label(): string
    {
        return 'COM_JED_BROWSE_' . strtoupper(str_replace('-', '_', $this->value));
    }

    /**
     * The list for a key, or null when the key is not one of these.
     *
     * @param string|null $key The key, e.g. from a menu item parameter.
     *
     * @return self|null
     *
     * @since 4.1.0
     */
    public static function fromKey(?string $key): ?self
    {
        return self::tryFrom(trim((string) $key));
    }
}
