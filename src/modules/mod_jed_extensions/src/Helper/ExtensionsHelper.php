<?php

/**
 * @package JED
 *
 * @subpackage mod_jed_extensions
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Module\Extensions\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Browse\BrowseList;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Fetches one browse list for the module.
 *
 * It queries directly rather than reusing the site's `ExtensionsModel`. That model carries the
 * whole browse page - pagination, the search filter, the Joomla-version filter, its own state
 * from the request - and a module that instantiates it inherits all of that, including reading
 * request variables that belong to whatever page it happens to be placed on. A module showing
 * "Top Rated" in a sidebar must show top rated, not top rated filtered by whatever the visitor
 * last searched for.
 *
 * What it does share is the definition of the lists themselves ({@see BrowseList}) and the
 * visibility rule ({@see JedHelper::getExtensionVisibilityCondition()}), which are the two things
 * that must never disagree with the pages.
 *
 * @since 4.0.0
 */
class ExtensionsHelper
{
    /**
     * The listings for a module instance.
     *
     * @param Registry $params The module parameters.
     *
     * @return object[]
     *
     * @since 4.0.0
     */
    public function getExtensions(Registry $params): array
    {
        $list  = BrowseList::fromKey((string) $params->get('browse_list', 'top-rated')) ?? BrowseList::TOP_RATED;
        $count = min(24, max(1, (int) $params->get('count', 6)));

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            // Everything JedHelper::cardData() reads. Selecting less is how the module ended up
            // rendering the shared card with four of its five signals blank (P1-14): the layout
            // was right, the row simply did not carry `type`, `modified`, the category or the
            // developer.
            ->select($db->quoteName([
                'a.id', 'a.name', 'a.alias', 'a.catid', 'a.intro', 'a.description', 'a.logo',
                'a.extension_types', 'a.joomla_versions', 'a.score_overall', 'a.score_count',
                'a.type', 'a.modified',
            ]))
            ->select($db->quoteName('cat.title', 'category_title'))
            ->select($db->quoteName('u.name', 'developer'))
            ->from($db->quoteName('#__jed_extensions', 'a'))
            ->innerJoin($db->quoteName('#__categories', 'cat'), 'cat.id = a.catid')
            ->leftJoin($db->quoteName('#__users', 'u'), 'u.id = a.created_by');

        // The same rule the pages use. A module is not a moderation view and must not become a
        // way to see a listing that is blocked, unapproved or offline (4.8).
        $query->where(JedHelper::getExtensionVisibilityCondition($db));

        $category = (int) $params->get('catid', 0);

        if ($category > 0) {
            $query->where($db->quoteName('a.catid') . ' = ' . $category);
        }

        if ($list === BrowseList::NOTEWORTHY) {
            // Not a sort of this table: what people have actually been looking at, from P1-12's
            // aggregate. INNER JOIN, because a listing nobody viewed is not noteworthy.
            $since = Factory::getDate('-' . BrowseList::NOTEWORTHY_DAYS . ' days')->format('Y-m-d');

            // A derived table, so the "table" is the subquery itself and there is no name for
            // quoteName() to wrap - only its alias. The join condition is passed separately, as
            // for any other join.
            $query->select('hits.views AS noteworthy')
                ->innerJoin(
                    '(SELECT ' . $db->quoteName('extension_id') . ', SUM(' . $db->quoteName('views') . ') AS '
                    . $db->quoteName('views') . ' FROM ' . $db->quoteName('#__jed_hit_stats')
                    . ' WHERE ' . $db->quoteName('period') . ' >= ' . $db->quote($since)
                    . ' GROUP BY ' . $db->quoteName('extension_id')
                    . ' HAVING SUM(' . $db->quoteName('views') . ') > 0) AS ' . $db->quoteName('hits'),
                    $db->quoteName('hits.extension_id') . ' = ' . $db->quoteName('a.id')
                )
                ->order($db->quoteName('noteworthy') . ' DESC');
        } else {
            [$column, $direction] = array_pad(explode(' ', $list->ordering(), 2), 2, 'DESC');
            $query->order($db->escape($column) . ' ' . (strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC'));
        }

        // The same tie-break the page applies, from the same definition. Without it a module and
        // a page carrying the same name showed different extensions, because several hundred
        // listings share a top score and MySQL orders equal rows as it pleases.
        $query->order($list->tieBreak());

        $rows = $db->setQuery($query, 0, $count)->loadObjectList() ?: [];

        foreach ($rows as $row) {
            $row->logo_url        = $row->logo ? JedHelper::formatImage((string) $row->logo, ImageSize::SMALL) : '';
            $row->card_text       = JedHelper::cardText($row->intro ?? null, $row->description ?? null);
            $row->includes_string = JedtrophyHelper::getTrophyIncludesStringFull((string) $row->extension_types);
            $row->version_string  = JedtrophyHelper::getTrophyVersionsStringFull((string) $row->joomla_versions);
        }

        return $rows;
    }
}
