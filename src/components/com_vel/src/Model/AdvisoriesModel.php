<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\ExtensionType;
use Jed\Component\Vel\Administrator\Enum\Severity;
use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * The public list (brief §4.1) - and the query behind the RSS feed and the paged JSON endpoint,
 * which take the same filters.
 *
 * Two rules are in `getListQuery()` rather than in a template, so that no future view can widen
 * them: only `published` rows, and no join to the reports, participants or messages tables.
 *
 * Filters are read from the request as plain GET parameters (not the `filter[]` convention) so
 * that list URLs are short and shareable: ?status=live&severity=high,critical&cvss_min=7.
 *
 * @since 4.0.0
 */
class AdvisoriesModel extends ListModel
{
    /**
     * @param array $config Configuration settings.
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'published', 'a.date_published',
                'updated', 'a.date_updated',
                'title', 'a.title',
                'score', 'a.cvss_score',
                'vendor',
            ];
        }

        parent::__construct($config);
    }

    /**
     * @param string $ordering  Default ordering column.
     * @param string $direction Default ordering direction.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function populateState($ordering = 'a.date_published', $direction = 'DESC'): void
    {
        $app    = Factory::getApplication();
        $input  = $app->getInput();
        $params = $app->getParams('com_vel');

        $this->setState('params', $params);

        $status = strtolower($input->getCmd('status', (string) $params->get('default_status', 'live')));
        $this->setState('filter.status', $status === 'all' || AdvisoryStatus::tryFrom($status) !== null ? $status : 'live');

        $this->setState('filter.search', trim($input->getString('search', '')));

        $severities = [];

        foreach (explode(',', strtolower($input->getString('severity', ''))) as $severity) {
            if (Severity::tryFrom(trim($severity)) !== null) {
                $severities[] = trim($severity);
            }
        }

        $this->setState('filter.severity', array_values(array_unique($severities)));

        $min = $input->getString('cvss_min', '');
        $max = $input->getString('cvss_max', '');
        $this->setState('filter.cvss_min', $min === '' ? null : max(0.0, min(10.0, (float) $min)));
        $this->setState('filter.cvss_max', $max === '' ? null : max(0.0, min(10.0, (float) $max)));

        $this->setState('filter.vendor', trim($input->getString('vendor', '')));

        $type = strtolower($input->getCmd('type', ''));
        $this->setState('filter.type', ExtensionType::tryFrom($type) !== null ? $type : '');

        // ?element[]=com_foo&element[]=mod_bar or ?element=com_foo,mod_bar - both are read.
        $elements = $input->get('element', [], 'raw');
        $elements = \is_string($elements) ? explode(',', $elements) : (array) $elements;
        $elements = array_values(array_filter(array_map(
            static fn ($e): string => preg_replace('/[^a-z0-9_\-.]/', '', strtolower(trim((string) $e))) ?? '',
            $elements
        )));
        $this->setState('filter.element', \array_slice($elements, 0, 100));

        $this->setState('filter.cve', strtoupper(trim($input->getString('cve', ''))));

        foreach (['from', 'to', 'updated_since'] as $date) {
            $value = trim($input->getString($date, ''));
            $this->setState('filter.' . $date, $value !== '' && strtotime($value) !== false ? Factory::getDate($value)->toSql() : '');
        }

        $this->setState('filter.include_withdrawn', (bool) $input->getInt('include_withdrawn', 0));

        $sort = $input->getCmd('sort', 'published');
        $dir  = strtoupper($input->getCmd('dir', 'desc')) === 'ASC' ? 'ASC' : 'DESC';

        $column = match ($sort) {
            'updated' => 'a.date_updated',
            'title'   => 'a.title',
            'score'   => 'a.cvss_score',
            default   => 'a.date_published',
        };

        $limit = $input->getUint('limit', (int) $params->get('list_limit', 25));
        $this->setState('list.limit', max(1, min(200, $limit)));
        $this->setState('list.start', $input->getUint('limitstart', $input->getUint('start', 0)));
        $this->setState('list.ordering', $column);
        $this->setState('list.direction', $dir);
    }

    /**
     * @param string $id A prefix for the store id.
     *
     * @return string
     *
     * @since 4.0.0
     */
    protected function getStoreId($id = ''): string
    {
        foreach (['status', 'search', 'severity', 'cvss_min', 'cvss_max', 'vendor', 'type', 'element', 'cve', 'from', 'to', 'updated_since', 'include_withdrawn'] as $filter) {
            $id .= ':' . json_encode($this->getState('filter.' . $filter));
        }

        return parent::getStoreId($id);
    }

    /**
     * @return QueryInterface
     *
     * @since 4.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            [
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.state'),
                $db->quoteName('a.status'),
                $db->quoteName('a.severity'),
                $db->quoteName('a.cvss_version'),
                $db->quoteName('a.cvss_vector'),
                $db->quoteName('a.cvss_score'),
                $db->quoteName('a.cve_id'),
                $db->quoteName('a.cve_assigner'),
                $db->quoteName('a.exploited'),
                $db->quoteName('a.date_published'),
                $db->quoteName('a.date_updated'),
                $db->quoteName('a.date_fixed'),
                $db->quoteName('a.date_retired'),
                $db->quoteName('a.retired_reason'),
                '(SELECT GROUP_CONCAT(DISTINCT ' . $db->quoteName('p.vendor') . ' SEPARATOR ", ") FROM '
                . $db->quoteName('#__vel_advisory_products', 'p') . ' WHERE ' . $db->quoteName('p.advisory_id') . ' = ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('vendor'),
                '(SELECT GROUP_CONCAT(' . $db->quoteName('p2.product') . ' ORDER BY ' . $db->quoteName('p2.ordering') . ' SEPARATOR ", ") FROM '
                . $db->quoteName('#__vel_advisory_products', 'p2') . ' WHERE ' . $db->quoteName('p2.advisory_id') . ' = ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('products'),
                '(SELECT GROUP_CONCAT(DISTINCT NULLIF(' . $db->quoteName('p3.fixed_version') . ', \'\') SEPARATOR ", ") FROM '
                . $db->quoteName('#__vel_advisory_products', 'p3') . ' WHERE ' . $db->quoteName('p3.advisory_id') . ' = ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('fixed_versions'),
            ]
        )
            ->from($db->quoteName('#__vel_advisories', 'a'));

        $published = AdvisoryState::PUBLISHED->value;

        if ($this->getState('filter.include_withdrawn')) {
            $retired = AdvisoryState::RETIRED->value;
            $query->whereIn($db->quoteName('a.state'), [$published, $retired], ParameterType::STRING);
        } else {
            $query->where($db->quoteName('a.state') . ' = :published')->bind(':published', $published);
        }

        $status = (string) $this->getState('filter.status');

        if ($status !== 'all' && AdvisoryStatus::tryFrom($status) !== null) {
            $query->where($db->quoteName('a.status') . ' = :status')->bind(':status', $status);
        }

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            if (preg_match('/^CVE-\d{4}-\d+$/i', $search)) {
                $cve = strtoupper($search);
                $query->where($db->quoteName('a.cve_id') . ' = :cve')->bind(':cve', $cve);
            } elseif (preg_match('/^VEL-?0*(\d+)$/i', $search, $m)) {
                $id = (int) $m[1];
                $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            } else {
                // Title, description, vendor, product (brief §4.1). LIKE rather than MATCH so
                // that a two-letter vendor or a partial product name still finds something.
                $like = '%' . str_replace(' ', '%', $search) . '%';
                $query->where(
                    '(' . $db->quoteName('a.title') . ' LIKE :s1'
                    . ' OR ' . $db->quoteName('a.description') . ' LIKE :s2'
                    . ' OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__vel_advisory_products', 'ps')
                    . ' WHERE ' . $db->quoteName('ps.advisory_id') . ' = ' . $db->quoteName('a.id')
                    . ' AND (' . $db->quoteName('ps.vendor') . ' LIKE :s3 OR ' . $db->quoteName('ps.product') . ' LIKE :s4 OR ' . $db->quoteName('ps.element') . ' LIKE :s5)))'
                )
                    ->bind(':s1', $like)
                    ->bind(':s2', $like)
                    ->bind(':s3', $like)
                    ->bind(':s4', $like)
                    ->bind(':s5', $like);
            }
        }

        $severities = (array) $this->getState('filter.severity');

        if ($severities !== []) {
            $query->whereIn($db->quoteName('a.severity'), $severities, ParameterType::STRING);
        }

        $min = $this->getState('filter.cvss_min');

        if ($min !== null) {
            $query->where($db->quoteName('a.cvss_score') . ' >= :min')->bind(':min', $min);
        }

        $max = $this->getState('filter.cvss_max');

        if ($max !== null) {
            $query->where($db->quoteName('a.cvss_score') . ' <= :max')->bind(':max', $max);
        }

        $vendor = (string) $this->getState('filter.vendor');

        if ($vendor !== '') {
            $vendorLike = '%' . $vendor . '%';
            $query->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__vel_advisory_products', 'pv')
                . ' WHERE ' . $db->quoteName('pv.advisory_id') . ' = ' . $db->quoteName('a.id')
                . ' AND ' . $db->quoteName('pv.vendor') . ' LIKE :vendor)')
                ->bind(':vendor', $vendorLike);
        }

        $type = (string) $this->getState('filter.type');

        if ($type !== '') {
            $query->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__vel_advisory_products', 'pt')
                . ' WHERE ' . $db->quoteName('pt.advisory_id') . ' = ' . $db->quoteName('a.id')
                . ' AND ' . $db->quoteName('pt.ext_type') . ' = :type)')
                ->bind(':type', $type);
        }

        $elements = (array) $this->getState('filter.element');

        if ($elements !== []) {
            $sub = $db->getQuery(true)
                ->select($db->quoteName('pe.advisory_id'))
                ->from($db->quoteName('#__vel_advisory_products', 'pe'))
                ->whereIn($db->quoteName('pe.element'), $elements, ParameterType::STRING);
            $query->where($db->quoteName('a.id') . ' IN (' . (string) $sub . ')');
        }

        $cve = (string) $this->getState('filter.cve');

        if ($cve !== '') {
            $query->where($db->quoteName('a.cve_id') . ' = :cvef')->bind(':cvef', $cve);
        }

        $from = (string) $this->getState('filter.from');

        if ($from !== '') {
            $query->where($db->quoteName('a.date_published') . ' >= :from')->bind(':from', $from);
        }

        $to = (string) $this->getState('filter.to');

        if ($to !== '') {
            $query->where($db->quoteName('a.date_published') . ' <= :to')->bind(':to', $to);
        }

        $since = (string) $this->getState('filter.updated_since');

        if ($since !== '') {
            $query->where($db->quoteName('a.date_updated') . ' >= :since')->bind(':since', $since);
        }

        $ordering  = (string) $this->getState('list.ordering', 'a.date_published');
        $direction = (string) $this->getState('list.direction', 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        if (!\in_array($ordering, ['a.date_published', 'a.date_updated', 'a.title', 'a.cvss_score'], true)) {
            $ordering = 'a.date_published';
        }

        $query->order($db->escape($ordering) . ' ' . $direction . ', ' . $db->quoteName('a.id') . ' DESC');

        return $query;
    }

    /**
     * The counts per status tab, on the current search/severity filters but not the status.
     *
     * @return array<string, int>
     *
     * @since 4.0.0
     */
    public function getStatusCounts(): array
    {
        $db        = $this->getDatabase();
        $published = AdvisoryState::PUBLISHED->value;

        $rows = $db->setQuery(
            $db->getQuery(true)
                ->select([$db->quoteName('status'), 'COUNT(*) AS ' . $db->quoteName('n')])
                ->from($db->quoteName('#__vel_advisories'))
                ->where($db->quoteName('state') . ' = :published')
                ->bind(':published', $published)
                ->group($db->quoteName('status'))
        )->loadAssocList('status', 'n') ?: [];

        $counts = ['all' => 0];

        foreach (AdvisoryStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
            $counts['all'] += $counts[$status->value];
        }

        return $counts;
    }

    /**
     * @return AdvisoryRepository
     *
     * @since 4.0.0
     */
    public function getRepository(): AdvisoryRepository
    {
        return new AdvisoryRepository($this->getDatabase());
    }
}
