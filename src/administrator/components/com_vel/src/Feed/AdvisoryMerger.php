<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Feed;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Cvss\Cvss;
use Jed\Component\Vel\Administrator\Enum\AdvisorySource;
use Jed\Component\Vel\Administrator\Enum\CreditRole;
use Jed\Component\Vel\Administrator\Enum\CveState;
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Jed\Component\Vel\Administrator\Enum\ReferenceTag;
use Jed\Component\Vel\Administrator\Enum\Severity;
use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Jed\Component\Vel\Administrator\Service\VelService;
use Jed\Component\Vel\Administrator\Version\Version;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * From a feed record to an advisory: create a draft, or fill what an existing advisory lacks
 * (VEL2-PRD §6.6.1 "merge").
 *
 * Merging is additive and never overwrites: a CVSS is set only where there is none, CWEs and
 * references only where the id or URL is new, version ranges only where the product has none.
 * What the team wrote stays; what the feed knows and the team did not is offered.
 *
 * @since 4.0.0
 */
final class AdvisoryMerger
{
    /**
     * @param DatabaseInterface $db      The database driver.
     * @param VelService        $service The lifecycle service.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db, private readonly VelService $service)
    {
    }

    /**
     * The existing advisory for a record, by CVE id.
     *
     * @param FeedRecord $record The record.
     *
     * @return object|null
     *
     * @since 4.0.0
     */
    public function findExisting(FeedRecord $record): ?object
    {
        return (new AdvisoryRepository($this->db))->findByCve($record->externalId);
    }

    /**
     * Create a draft advisory from a record.
     *
     * @param FeedRecord $record  The record.
     * @param int        $actorId Who (0 for the feed).
     *
     * @return int  The advisory id.
     *
     * @since 4.0.0
     */
    public function create(FeedRecord $record, int $actorId = 0): int
    {
        Factory::getApplication()->getLanguage()->load('com_vel', JPATH_ADMINISTRATOR . '/components/com_vel');

        $n      = $record->normalised;
        $prefix = (string) ComponentHelper::getParams('com_vel')->get('title_prefix', 'Joomla Extension - {vendor} - ');
        $title  = trim((string) ($n['title'] ?? ''));

        // Strip our own export prefix back off an imported title.
        $vendor = (string) ($n['affected'][0]['vendor'] ?? '');
        $own    = trim(str_replace('{vendor}', $vendor, $prefix));

        if ($own !== '' && str_starts_with($title, $own)) {
            $title = trim(mb_substr($title, mb_strlen($own)), " -–");
        }

        if ($title === '') {
            $title = $record->externalId;
        }

        $description = (string) ($n['description_html'] ?? '');

        if (trim($description) === '') {
            $description = '<p>' . nl2br(htmlspecialchars((string) ($n['description'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>';
        } elseif ($own !== '' && str_starts_with(trim(strip_tags($description)), $own)) {
            // The HTML carries the title prefix too; the advisory text should not.
            $description = preg_replace('/^(\s*<p[^>]*>)?\s*' . preg_quote($own, '/') . '\s*[-–]?\s*' . preg_quote(strip_tags($title), '/') . '\s*[-–]?\s*/u', '$1', $description) ?? $description;
        }

        $cvss = $this->cvssColumns($n['cvss'] ?? null);

        $id = $this->service->createDraft(array_merge([
            'title'          => mb_substr($title, 0, 255),
            'description'    => $description,
            'solution'       => (string) ($n['solution'] ?? ''),
            'workaround'     => (string) ($n['workaround'] ?? ''),
            'discovery'      => \in_array((string) ($n['discovery'] ?? ''), ['UNKNOWN', 'INTERNAL', 'EXTERNAL', 'USER'], true) ? (string) $n['discovery'] : 'UNKNOWN',
            'cve_id'         => $record->externalId,
            'cve_state'      => (string) ($n['state'] ?? 'PUBLISHED') === 'REJECTED' ? CveState::REJECTED->value : CveState::PUBLISHED->value,
            'cve_assigner'   => mb_substr((string) ($n['assigner'] ?? ''), 0, 64),
            'date_reserved'  => $n['dates']['reserved'] ?? null,
            'date_public'    => $n['dates']['published'] ?? null,
            'internal_notes' => Text::sprintf('COM_VEL_NOTE_CREATED_FROM_FEED', Text::_($record->source->label()), (string) ($n['source_url'] ?? '')),
        ], $cvss), $record->source === FeedSource::NVD ? AdvisorySource::NVD : AdvisorySource::CNA, $actorId);

        $this->addProducts($id, (array) ($n['affected'] ?? []));
        $this->addCwes($id, (array) ($n['cwes'] ?? []), []);
        $this->addReferences($id, (array) ($n['references'] ?? []), []);
        $this->addCredits($id, (array) ($n['credits'] ?? []));

        $this->service->refreshStatus($id, $actorId);

        return $id;
    }

    /**
     * Fill what an advisory lacks from a record.
     *
     * @param FeedRecord $record   The record.
     * @param int        $advisoryId The advisory.
     * @param int        $actorId  Who.
     *
     * @return string[]  What was added, as language-key-free sentences for the message.
     *
     * @since 4.0.0
     */
    public function merge(FeedRecord $record, int $advisoryId, int $actorId = 0): array
    {
        $advisory = (new AdvisoryRepository($this->db))->find($advisoryId);

        if ($advisory === null) {
            return [];
        }

        $n     = $record->normalised;
        $added = [];
        $now   = Factory::getDate()->toSql();

        // CVSS, only where there is none.
        if (empty($advisory->cvss_vector) && !empty($n['cvss']['vector'])) {
            $columns = $this->cvssColumns($n['cvss']);

            if (!empty($columns['cvss_vector'])) {
                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__vel_advisories'))
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':id', $advisoryId, ParameterType::INTEGER);

                foreach ($columns as $column => $value) {
                    $query->set($this->db->quoteName($column) . ' = :' . $column)->bind(':' . $column, $value);
                }

                $this->db->setQuery($query)->execute();
                $added[] = 'CVSS ' . $columns['cvss_vector'] . ' (' . ($n['cvss']['from'] ?? '') . ')';
            }
        }

        // CVE id and state, where the advisory had none.
        if (empty($advisory->cve_id)) {
            $this->service->setCve($advisoryId, $record->externalId, CveState::PUBLISHED, mb_substr((string) ($n['assigner'] ?? ''), 0, 64), $n['dates']['reserved'] ?? null, $actorId);
            $added[] = $record->externalId;
        }

        $existingCwes = array_map(static fn (object $c): string => strtoupper((string) $c->cwe_id), (array) $advisory->cwes);
        $newCwes      = $this->addCwes($advisoryId, (array) ($n['cwes'] ?? []), $existingCwes);

        if ($newCwes > 0) {
            $added[] = $newCwes . ' CWE';
        }

        $existingUrls  = array_map(static fn (object $r): string => (string) $r->url, (array) $advisory->references);
        $newReferences = $this->addReferences($advisoryId, (array) ($n['references'] ?? []), $existingUrls);

        if ($newReferences > 0) {
            $added[] = $newReferences . ' references';
        }

        // Products with no ranges get the record's ranges when the product names match.
        foreach ((array) $advisory->products as $product) {
            if ((array) $product->versions !== []) {
                continue;
            }

            foreach ((array) ($n['affected'] ?? []) as $candidate) {
                if (!self::sameProduct((string) $product->product, (string) $candidate['product'])) {
                    continue;
                }

                $ordering = 0;

                foreach ((array) ($candidate['versions'] ?? []) as $range) {
                    $this->db->insertObject('#__vel_advisory_versions', (object) [
                        'product_id'   => (int) $product->id,
                        'version_from' => mb_substr((string) $range['from'], 0, 50),
                        'version_to'   => mb_substr((string) $range['to'], 0, 50),
                        'version_type' => 'custom',
                        'ordering'     => $ordering++,
                    ], 'id');
                }

                if ($ordering > 0) {
                    $added[] = 'versions for ' . $product->product;
                }

                if ((string) $product->fixed_version === '' && (string) ($candidate['fixed'] ?? '') !== '') {
                    $fixed = mb_substr((string) $candidate['fixed'], 0, 50);
                    $this->db->setQuery(
                        $this->db->getQuery(true)->update($this->db->quoteName('#__vel_advisory_products'))
                            ->set($this->db->quoteName('fixed_version') . ' = :fixed')
                            ->where($this->db->quoteName('id') . ' = :id')
                            ->bind(':fixed', $fixed)->bind(':id', $product->id, ParameterType::INTEGER)
                    )->execute();
                    $added[] = 'fixed version for ' . $product->product;
                }
            }
        }

        if ((array) $advisory->products === []) {
            $this->addProducts($advisoryId, (array) ($n['affected'] ?? []));
            $added[] = \count((array) ($n['affected'] ?? [])) . ' products';
        }

        if ($added !== []) {
            $this->service->appendNote($advisoryId, Text::sprintf('COM_VEL_NOTE_MERGED_FROM_FEED', Text::_($record->source->label()), implode(', ', $added)));
            $this->service->refreshStatus($advisoryId, $actorId);
            $this->service->touchPublished($advisoryId, $actorId);
        }

        return $added;
    }

    /**
     * @param array|null $cvss The record's cvss block.
     *
     * @return array<string, mixed>  Column values, possibly empty.
     *
     * @since 4.0.0
     */
    private function cvssColumns(?array $cvss): array
    {
        if (!\is_array($cvss) || empty($cvss['vector'])) {
            return [];
        }

        $vector = (string) $cvss['vector'];

        // 3.0 vectors are accepted by the 3.1 parser and re-stamped 3.1; the score is recomputed.
        try {
            $result = Cvss::evaluate($vector, isset($cvss['score']) ? (float) $cvss['score'] : null);
        } catch (\InvalidArgumentException $e) {
            // A mismatch between the record's score and its vector: keep the vector's truth
            // for 3.x, and the supplied score for 4.0 which cannot be recomputed here.
            try {
                $result = str_starts_with($vector, 'CVSS:4.0/') ? Cvss::evaluate($vector, (float) ($cvss['score'] ?? 0)) : Cvss::evaluate($vector, null);
            } catch (\InvalidArgumentException $e2) {
                return [];
            }
        }

        return [
            'cvss_version'    => $result->version,
            'cvss_vector'     => $result->vector,
            'cvss_score'      => $result->score,
            'severity'        => $result->severity->value,
            'severity_reason' => '',
        ];
    }

    /**
     * @param int   $advisoryId The advisory.
     * @param array $affected   The record's products.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function addProducts(int $advisoryId, array $affected): void
    {
        $suffix   = trim((string) ComponentHelper::getParams('com_vel')->get('product_suffix', ' extension for Joomla'));
        $ordering = 0;

        foreach ($affected as $candidate) {
            $name = trim((string) ($candidate['product'] ?? ''));

            if ($suffix !== '' && str_ends_with($name, $suffix)) {
                $name = trim(mb_substr($name, 0, mb_strlen($name) - mb_strlen($suffix)));
            }

            $product = (object) [
                'advisory_id'    => $advisoryId,
                'vendor'         => mb_substr(trim((string) ($candidate['vendor'] ?? '')), 0, 255),
                'product'        => mb_substr($name, 0, 255),
                'extension_id'   => null,
                'supplier_url'   => '',
                'ext_type'       => null,
                'element'        => self::elementFromPackage((string) ($candidate['package'] ?? '')),
                'folder'         => null,
                'client_id'      => null,
                'fixed_version'  => mb_substr((string) ($candidate['fixed'] ?? ''), 0, 50),
                'patch_url'      => '',
                'default_status' => \in_array((string) ($candidate['default_status'] ?? ''), ['affected', 'unaffected', 'unknown'], true) ? (string) $candidate['default_status'] : 'unaffected',
                'ordering'       => $ordering++,
            ];

            if ($product->element !== null) {
                $product->ext_type = match (true) {
                    str_starts_with($product->element, 'com_') => 'component',
                    str_starts_with($product->element, 'mod_') => 'module',
                    str_starts_with($product->element, 'plg_') => 'plugin',
                    str_starts_with($product->element, 'tpl_') => 'template',
                    str_starts_with($product->element, 'lib_') => 'library',
                    str_starts_with($product->element, 'pkg_') => 'package',
                    default                                    => null,
                };

                if ($product->ext_type === 'plugin' && preg_match('/^plg_([a-z0-9\-]+)_([a-z0-9_\-]+)$/', $product->element, $m)) {
                    $product->folder  = $m[1];
                    $product->element = $m[2];
                } elseif ($product->ext_type === 'template') {
                    $product->element = substr($product->element, 4);
                }
            }

            $this->db->insertObject('#__vel_advisory_products', $product, 'id');

            $vOrdering = 0;

            foreach ((array) ($candidate['versions'] ?? []) as $range) {
                $this->db->insertObject('#__vel_advisory_versions', (object) [
                    'product_id'   => (int) $product->id,
                    'version_from' => mb_substr((string) ($range['from'] ?? Version::ANY), 0, 50),
                    'version_to'   => mb_substr((string) ($range['to'] ?? Version::ANY), 0, 50),
                    'version_type' => 'custom',
                    'ordering'     => $vOrdering++,
                ], 'id');
            }
        }
    }

    /**
     * @param int      $advisoryId The advisory.
     * @param array    $cwes       The record's CWEs.
     * @param string[] $existing   Ids already on the advisory.
     *
     * @return int  How many were added.
     *
     * @since 4.0.0
     */
    private function addCwes(int $advisoryId, array $cwes, array $existing): int
    {
        $names    = [];
        $ordering = \count($existing);
        $added    = 0;

        foreach ($cwes as $cwe) {
            $id = strtoupper(trim((string) ($cwe['id'] ?? '')));

            if (!preg_match('/^CWE-\d+$/', $id) || \in_array($id, $existing, true)) {
                continue;
            }

            if ($names === []) {
                $names = $this->db->setQuery(
                    $this->db->getQuery(true)->select($this->db->quoteName(['cwe_id', 'name']))->from($this->db->quoteName('#__vel_cwe'))
                )->loadAssocList('cwe_id', 'name') ?: [];
            }

            $description = trim((string) ($cwe['description'] ?? ''));

            if ($description === '' || $description === $id) {
                $description = isset($names[$id]) ? $id . ' ' . $names[$id] : $id;
            }

            $this->db->insertObject('#__vel_advisory_cwes', (object) [
                'advisory_id' => $advisoryId,
                'cwe_id'      => $id,
                'description' => mb_substr($description, 0, 255),
                'ordering'    => $ordering++,
            ], 'id');

            $existing[] = $id;
            $added++;
        }

        return $added;
    }

    /**
     * @param int      $advisoryId The advisory.
     * @param array    $references The record's references.
     * @param string[] $existing   URLs already on the advisory.
     *
     * @return int  How many were added.
     *
     * @since 4.0.0
     */
    private function addReferences(int $advisoryId, array $references, array $existing): int
    {
        $valid    = array_map(static fn (ReferenceTag $t): string => $t->value, ReferenceTag::cases());
        $ordering = \count($existing);
        $added    = 0;

        foreach ($references as $reference) {
            $url = trim((string) ($reference['url'] ?? ''));

            if (!preg_match('~^https?://~i', $url) || \in_array($url, $existing, true)) {
                continue;
            }

            // Our own pages are not references to ourselves.
            if (str_contains($url, '/vulnerable-extensions/')) {
                continue;
            }

            $tags = array_values(array_filter(array_map('strval', (array) ($reference['tags'] ?? [])), static fn (string $t): bool => \in_array($t, $valid, true)));

            $this->db->insertObject('#__vel_advisory_references', (object) [
                'advisory_id' => $advisoryId,
                'url'         => mb_substr($url, 0, 1024),
                'name'        => '',
                'tags'        => json_encode($tags),
                'ordering'    => $ordering++,
            ], 'id');

            $existing[] = $url;
            $added++;
        }

        return $added;
    }

    /**
     * @param int   $advisoryId The advisory.
     * @param array $credits    The record's credits.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function addCredits(int $advisoryId, array $credits): void
    {
        $ordering = 0;

        foreach ($credits as $credit) {
            $name = trim((string) ($credit['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $role = CreditRole::tryFrom((string) ($credit['role'] ?? '')) ?? CreditRole::FINDER;

            $this->db->insertObject('#__vel_advisory_credits', (object) [
                'advisory_id' => $advisoryId,
                'name'        => mb_substr($name, 0, 255),
                'role'        => $role->value,
                'user_id'     => null,
                'report_id'   => null,
                'is_public'   => 1,
                'ordering'    => $ordering++,
            ], 'id');
        }
    }

    /**
     * @param string $package A CVE `packageName`, which for Joomla records is the element.
     *
     * @return string|null
     *
     * @since 4.0.0
     */
    private static function elementFromPackage(string $package): ?string
    {
        $package = strtolower(trim($package));

        return $package !== '' && preg_match('/^[a-z0-9_\-.]+$/', $package) ? $package : null;
    }

    /**
     * @param string $a One product name.
     * @param string $b Another.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private static function sameProduct(string $a, string $b): bool
    {
        $normalise = static fn (string $s): string => preg_replace('/\s*(extension|plugin|component|module|template)?\s*for\s*joomla!?\s*$/i', '', strtolower(trim($s))) ?? strtolower(trim($s));

        return $normalise($a) === $normalise($b);
    }
}
