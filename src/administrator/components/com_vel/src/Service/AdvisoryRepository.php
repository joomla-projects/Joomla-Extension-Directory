<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Service;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Reads advisories with their children - products and their version ranges, CWEs, references,
 * credits - as one object, the way every consumer wants them: the editor, the public detail
 * page, the JSON endpoints, the CVE builder and the history snapshot.
 *
 * Reads only. Writes go through {@see VelService} and
 * {@see \Jed\Component\Vel\Administrator\Model\AdvisoryModel}.
 *
 * @since 4.0.0
 */
class AdvisoryRepository
{
    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @param int  $id         The advisory.
     * @param bool $publicOnly Refuse anything not in `published` (or `retired`, which is public
     *                         as a notice). The site side always passes true.
     *
     * @return object|null
     *
     * @since 4.0.0
     */
    public function find(int $id, bool $publicOnly = false): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_advisories'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject();

        if ($row === null) {
            return null;
        }

        if ($publicOnly && !\in_array((string) $row->state, [AdvisoryState::PUBLISHED->value, AdvisoryState::RETIRED->value], true)) {
            return null;
        }

        return $this->attachChildren($row);
    }

    /**
     * @param string $cveId      A CVE id, any case.
     * @param bool   $publicOnly See find().
     *
     * @return object|null
     *
     * @since 4.0.0
     */
    public function findByCve(string $cveId, bool $publicOnly = false): ?object
    {
        $cveId = strtoupper(trim($cveId));

        if ($cveId === '') {
            return null;
        }

        $id = (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__vel_advisories'))
                ->where($this->db->quoteName('cve_id') . ' = :cve')
                ->bind(':cve', $cveId)
        )->loadResult();

        return $id > 0 ? $this->find($id, $publicOnly) : null;
    }

    /**
     * @param string $alias      The alias.
     * @param bool   $publicOnly See find().
     *
     * @return object|null
     *
     * @since 4.0.0
     */
    public function findByAlias(string $alias, bool $publicOnly = false): ?object
    {
        $alias = trim($alias);

        if ($alias === '') {
            return null;
        }

        $id = (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__vel_advisories'))
                ->where($this->db->quoteName('alias') . ' = :alias')
                ->bind(':alias', $alias)
        )->loadResult();

        return $id > 0 ? $this->find($id, $publicOnly) : null;
    }

    /**
     * Resolve any of the forms a link can take - a bare id, "42:alias", "VEL-0042", a CVE id -
     * to an id.
     *
     * @param string $reference The reference.
     *
     * @return int  0 when nothing matches.
     *
     * @since 4.0.0
     */
    public function resolveReference(string $reference): int
    {
        $reference = trim($reference);

        if ($reference === '') {
            return 0;
        }

        if (preg_match('/^VEL-?0*(\d+)$/i', $reference, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^CVE-\d{4}-\d{4,}$/i', $reference)) {
            return (int) ($this->findByCve($reference)?->id ?? 0);
        }

        if (preg_match('/^(\d+)(?:[:\-].*)?$/', $reference, $m)) {
            return (int) $m[1];
        }

        return (int) ($this->findByAlias($reference)?->id ?? 0);
    }

    /**
     * Hang the children off an advisory row.
     *
     * @param object $row The advisory row.
     *
     * @return object  The same object, with `products` (each with `versions`), `cwes`,
     *                 `references` (with `tags` decoded) and `credits`.
     *
     * @since 4.0.0
     */
    public function attachChildren(object $row): object
    {
        $id = (int) $row->id;

        $row->products   = $this->products($id);
        $row->cwes       = $this->childRows('#__vel_advisory_cwes', $id);
        $row->references = $this->childRows('#__vel_advisory_references', $id);
        $row->credits    = $this->childRows('#__vel_advisory_credits', $id);

        foreach ($row->references as $reference) {
            $tags            = json_decode((string) ($reference->tags ?? '[]'), true);
            $reference->tags = \is_array($tags) ? array_values(array_filter(array_map('strval', $tags))) : [];
        }

        return $row;
    }

    /**
     * @param int $advisoryId The advisory.
     *
     * @return object[]  Products, each with a `versions` array.
     *
     * @since 4.0.0
     */
    public function products(int $advisoryId): array
    {
        $products = $this->childRows('#__vel_advisory_products', $advisoryId);

        if ($products === []) {
            return [];
        }

        $ids      = array_map(static fn (object $p): int => (int) $p->id, $products);
        $versions = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_advisory_versions'))
                ->whereIn($this->db->quoteName('product_id'), $ids)
                ->order([$this->db->quoteName('product_id') . ' ASC', $this->db->quoteName('ordering') . ' ASC', $this->db->quoteName('id') . ' ASC'])
        )->loadObjectList() ?: [];

        $byProduct = [];

        foreach ($versions as $version) {
            $byProduct[(int) $version->product_id][] = $version;
        }

        foreach ($products as $product) {
            $product->versions = $byProduct[(int) $product->id] ?? [];
        }

        return $products;
    }

    /**
     * @param string $table      A child table with an `advisory_id` and an `ordering`.
     * @param int    $advisoryId The advisory.
     *
     * @return object[]
     *
     * @since 4.0.0
     */
    private function childRows(string $table, int $advisoryId): array
    {
        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName($table))
                ->where($this->db->quoteName('advisory_id') . ' = :id')
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
                ->order([$this->db->quoteName('ordering') . ' ASC', $this->db->quoteName('id') . ' ASC'])
        )->loadObjectList() ?: [];
    }

    /**
     * Every published advisory, with children, for the dataset and the check API.
     *
     * @param int $retiredWithinDays Include retired advisories retired within this many days
     *                               (0 = none), marked `withdrawn`.
     *
     * @return object[]
     *
     * @since 4.0.0
     */
    public function allPublished(int $retiredWithinDays = 0): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__vel_advisories'))
            ->order($this->db->quoteName('date_published') . ' DESC');

        $published = AdvisoryState::PUBLISHED->value;

        if ($retiredWithinDays > 0) {
            $retired = AdvisoryState::RETIRED->value;
            $since   = (new \DateTimeImmutable('now -' . $retiredWithinDays . ' days'))->format('Y-m-d H:i:s');
            $query->where('(' . $this->db->quoteName('state') . ' = :published OR ('
                . $this->db->quoteName('state') . ' = :retired AND ' . $this->db->quoteName('date_retired') . ' >= :since))')
                ->bind(':published', $published)
                ->bind(':retired', $retired)
                ->bind(':since', $since);
        } else {
            $query->where($this->db->quoteName('state') . ' = :published')->bind(':published', $published);
        }

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];

        foreach ($rows as $row) {
            $this->attachChildren($row);
        }

        return $rows;
    }

    /**
     * Published advisories whose products name the given identity tuples - the check API's
     * first step, before version matching.
     *
     * @param array<int, array{type:?string, element:?string}> $identities Type/element pairs.
     *
     * @return object[]  Advisories with children.
     *
     * @since 4.0.0
     */
    public function publishedForIdentities(array $identities): array
    {
        $elements = [];

        foreach ($identities as $identity) {
            if (!empty($identity['element'])) {
                $elements[] = strtolower((string) $identity['element']);
            }
        }

        $elements = array_values(array_unique($elements));

        if ($elements === []) {
            return [];
        }

        $published = AdvisoryState::PUBLISHED->value;

        $ids = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('DISTINCT ' . $this->db->quoteName('p.advisory_id'))
                ->from($this->db->quoteName('#__vel_advisory_products', 'p'))
                ->innerJoin(
                    $this->db->quoteName('#__vel_advisories', 'a'),
                    $this->db->quoteName('a.id') . ' = ' . $this->db->quoteName('p.advisory_id')
                )
                ->where($this->db->quoteName('a.state') . ' = :published')
                ->whereIn($this->db->quoteName('p.element'), $elements, ParameterType::STRING)
                ->bind(':published', $published)
        )->loadColumn() ?: [];

        $rows = [];

        foreach ($ids as $id) {
            $row = $this->find((int) $id);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Published advisories naming a JED listing, for the badge on the listing page.
     *
     * @param int $extensionId The listing.
     *
     * @return object[]  Advisory rows (no children) with `fixed_version` from the product.
     *
     * @since 4.0.0
     */
    public function publishedForExtension(int $extensionId): array
    {
        if ($extensionId <= 0) {
            return [];
        }

        $published = AdvisoryState::PUBLISHED->value;

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select([
                    $this->db->quoteName('a.id'),
                    $this->db->quoteName('a.title'),
                    $this->db->quoteName('a.alias'),
                    $this->db->quoteName('a.status'),
                    $this->db->quoteName('a.severity'),
                    $this->db->quoteName('a.cvss_score'),
                    $this->db->quoteName('a.cve_id'),
                    $this->db->quoteName('a.date_published'),
                    $this->db->quoteName('p.fixed_version'),
                    $this->db->quoteName('p.product'),
                ])
                ->from($this->db->quoteName('#__vel_advisory_products', 'p'))
                ->innerJoin(
                    $this->db->quoteName('#__vel_advisories', 'a'),
                    $this->db->quoteName('a.id') . ' = ' . $this->db->quoteName('p.advisory_id')
                )
                ->where($this->db->quoteName('a.state') . ' = :published')
                ->where($this->db->quoteName('p.extension_id') . ' = :eid')
                ->bind(':published', $published)
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
                ->order($this->db->quoteName('a.date_published') . ' DESC')
        )->loadObjectList() ?: [];
    }
}
