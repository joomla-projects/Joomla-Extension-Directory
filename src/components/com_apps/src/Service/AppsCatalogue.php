<?php

/**
 * @package JED
 *
 * @subpackage Apps
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Apps\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Reads what the Install from Web tab shows from the com_jed tables.
 *
 * Only listings the public site shows are returned: approved, published by the developer, not
 * blocked, not deleted (see Jed\Component\Jed\Administrator\Listing\ListingAccess). The columns
 * are the four of the IDX_jed_extensions_visibility index, so a listing is one index range scan.
 *
 * @since 4.0.0
 */
class AppsCatalogue
{
    private const COLUMNS = [
        'a.id', 'a.name', 'a.alias', 'a.catid', 'a.intro', 'a.type', 'a.extension_types', 'a.logo',
        'a.score_overall', 'a.score_count', 'a.extension_version', 'a.modified', 'a.created',
        'a.uses_updater', 'a.update_url', 'a.download_url', 'a.joomla_versions', 'a.license',
        'a.developer_url',
    ];

    public function __construct(private readonly DatabaseInterface $db, private readonly int $maxItems = 128)
    {
    }

    /**
     * Published com_jed categories in tree order.
     *
     * @return object[]  id, title, parent_id, level, lft, rgt
     *
     * @since 4.0.0
     */
    public function categories(): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'title', 'parent_id', 'level', 'lft', 'rgt']))
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_jed'))
            ->where($this->db->quoteName('published') . ' = 1')
            ->where($this->db->quoteName('level') . ' > 0')
            ->order($this->db->quoteName('lft'));

        return (array) $this->db->setQuery($query)->loadObjectList();
    }

    /**
     * Listings for the dashboard (all, or a search) or one category with its sub-categories.
     *
     * @return object[]
     *
     * @since 4.0.0
     */
    public function items(AppsRequest $request, array $categories = []): array
    {
        $db    = $this->db;
        $query = $this->baseQuery()
            ->select($db->quoteName(self::COLUMNS));

        if ($request->view === 'category') {
            $ids = self::subtreeIds($categories, $request->id);

            if ($ids === []) {
                return [];
            }

            $query->whereIn($db->quoteName('a.catid'), $ids);
        }

        $this->addVersionFilter($query, $request->major);

        foreach (explode(' ', $request->search) as $term) {
            if ($term === '') {
                continue;
            }

            $like = $query->quote('%' . $db->escape($term, true) . '%', false);
            $query->where('(' . $db->quoteName('a.name') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('a.intro') . ' LIKE ' . $like . ')');
        }

        // A name that matches every term comes first, as on the old directory
        $inName = [];

        foreach (explode(' ', $request->search) as $term) {
            if ($term !== '') {
                $inName[] = $db->quoteName('a.name') . ' LIKE ' . $query->quote('%' . $db->escape($term, true) . '%', false);
            }
        }

        if ($inName !== []) {
            $query->order('(' . implode(' AND ', $inName) . ') DESC');
        }

        $query->order(AppsRequest::ORDERINGS[$request->ordering]);

        $db->setQuery($query, 0, $this->maxItems);

        return (array) $db->loadObjectList();
    }

    /**
     * One visible listing with its description, or null.
     *
     * @since 4.0.0
     */
    public function item(int $id): ?object
    {
        $db    = $this->db;
        $query = $this->baseQuery()
            ->select($db->quoteName(array_merge(self::COLUMNS, ['a.description'])))
            ->where($db->quoteName('a.id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $db->setQuery($query, 0, 1)->loadObject() ?: null;
    }

    /**
     * Labels of the Joomla versions a listing declares ("6", "5 (b/c)").
     *
     * @return string[]
     *
     * @since 4.0.0
     */
    public function versionLabels(): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'label']))
            ->from($this->db->quoteName('#__jed_joomla_versions'))
            ->where($this->db->quoteName('published') . ' = 1')
            ->order($this->db->quoteName('id'));

        $labels = [];

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            $labels[(string) $row->id] = (string) $row->label;
        }

        return $labels;
    }

    /**
     * Ids of a category and everything under it.
     *
     * @param object[] $categories As returned by categories().
     *
     * @return int[]
     *
     * @since 4.0.0
     */
    public static function subtreeIds(array $categories, int $id): array
    {
        foreach ($categories as $category) {
            if ((int) $category->id === $id) {
                $ids = [];

                foreach ($categories as $other) {
                    if ((int) $other->lft >= (int) $category->lft && (int) $other->rgt <= (int) $category->rgt) {
                        $ids[] = (int) $other->id;
                    }
                }

                return $ids;
            }
        }

        return [];
    }

    /**
     * #__jed_joomla_versions ids for a major version: 6 => 60 ("6") and 61 ("6 (b/c)").
     *
     * @return string[]
     *
     * @since 4.0.0
     */
    public static function versionIds(int $major): array
    {
        return [(string) ($major * 10), (string) ($major * 10 + 1)];
    }

    private function baseQuery()
    {
        $db = $this->db;

        return $db->getQuery(true)
            ->from($db->quoteName('#__jed_extensions', 'a'))
            ->where($db->quoteName('a.approved') . ' = 1')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.blocked') . ' = 0')
            ->where($db->quoteName('a.deleted') . ' = 0');
    }

    private function addVersionFilter($query, ?int $major): void
    {
        if ($major === null) {
            return;
        }

        $conditions = [];

        foreach (self::versionIds($major) as $versionId) {
            $conditions[] = $this->db->quoteName('a.joomla_versions') . ' LIKE '
                . $query->quote('%"' . $versionId . '"%');
        }

        $query->where('(' . implode(' OR ', $conditions) . ')');
    }
}
