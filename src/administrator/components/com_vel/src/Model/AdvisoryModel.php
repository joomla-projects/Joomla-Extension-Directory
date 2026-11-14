<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use InvalidArgumentException;
use Jed\Component\Vel\Administrator\Cvss\Cvss;
use Jed\Component\Vel\Administrator\Cvss\CvssException;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\CreditRole;
use Jed\Component\Vel\Administrator\Enum\ReferenceTag;
use Jed\Component\Vel\Administrator\Enum\Severity;
use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Jed\Component\Vel\Administrator\Service\JedBridge;
use Jed\Component\Vel\Administrator\Service\VelService;
use Jed\Component\Vel\Administrator\Version\Identity;
use Jed\Component\Vel\Administrator\Version\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Throwable;
use UnexpectedValueException;

/**
 * The advisory editor.
 *
 * The workflow is not here. Publishing, retiring, the status, the CVE fields and the three
 * auto dates run through {@see VelService} from the controller; what this model does is the
 * ordinary editing around them, and the one thing that is genuinely its job: turning the nested
 * subforms - products with their version ranges, CWEs, references, credits - into child rows and
 * back (VEL2-PRD §8.3).
 *
 * @since 4.0.0
 */
class AdvisoryModel extends AdminModel
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_VEL';

    /**
     * The columns VelService owns. Stripped from every form post before the table sees them.
     *
     * @var string[]
     *
     * @since 4.0.0
     */
    private const OWNED = [
        'state', 'source', 'date_reserved', 'date_published', 'date_updated', 'date_retired', 'retired_reason',
        'cve_id', 'cve_state', 'cve_assigner', 'published_json', 'jed_ticket_id', 'abandonware_case_id', 'hits',
    ];

    /**
     * @param string $type   The table type.
     * @param string $prefix The class prefix.
     * @param array  $config Configuration array.
     *
     * @return Table
     *
     * @since 4.0.0
     */
    public function getTable($type = 'Advisory', $prefix = 'Administrator', $config = []): Table
    {
        return $this->getMVCFactory()->createTable($type, $prefix, $config);
    }

    /**
     * @param array $data     Data for the form.
     * @param bool  $loadData Whether to load the data.
     *
     * @return Form|false
     *
     * @since 4.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_vel.advisory', 'advisory', ['control' => 'jform', 'load_data' => $loadData]) ?: false;
    }

    /**
     * @return array|object
     *
     * @since 4.0.0
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_vel.edit.advisory.data', []);

        if (empty($data)) {
            // `?: []` and not the bare result: getItem() returns false for an id that does not
            // exist, and Form::bind(false) is a TypeError rather than an empty form.
            $data = $this->getItem() ?: [];
        }

        return $data;
    }

    /**
     * Load an advisory with its children hydrated into the shape the subforms take.
     *
     * @param int|null $pk The advisory id.
     *
     * @return object|false
     *
     * @since 4.0.0
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item || empty($item->id)) {
            return $item;
        }

        $repository = new AdvisoryRepository($this->getDatabase());
        $full       = $repository->find((int) $item->id);

        if ($full === null) {
            return $item;
        }

        $item->products = [];

        foreach ($full->products as $product) {
            $row = (array) $product;
            unset($row['advisory_id'], $row['ordering']);

            $row['versions'] = [];

            foreach ($product->versions as $version) {
                $row['versions'][] = [
                    'id'           => (int) $version->id,
                    'version_from' => (string) $version->version_from,
                    'version_to'   => (string) $version->version_to,
                    'version_type' => (string) $version->version_type,
                ];
            }

            $item->products[] = $row;
        }

        $item->cwes = array_map(static fn (object $c): array => [
            'id'          => (int) $c->id,
            'cwe_id'      => (string) $c->cwe_id,
            'description' => (string) $c->description,
        ], $full->cwes);

        $item->references = array_map(static fn (object $r): array => [
            'id'   => (int) $r->id,
            'url'  => (string) $r->url,
            'name' => (string) $r->name,
            'tags' => (array) $r->tags,
        ], $full->references);

        $item->credits = array_map(static fn (object $c): array => [
            'id'        => (int) $c->id,
            'name'      => (string) $c->name,
            'role'      => (string) $c->role,
            'is_public' => (int) $c->is_public,
            'user_id'   => (int) ($c->user_id ?? 0),
            'report_id' => (int) ($c->report_id ?? 0),
        ], $full->credits);

        // For the CVE tab and the sidebar: read-only facts the form does not carry.
        $item->reference      = \sprintf('VEL-%04d', (int) $item->id);
        $item->state_enum     = AdvisoryState::tryFrom((string) $item->state) ?? AdvisoryState::DRAFT;
        $item->status_enum    = AdvisoryStatus::tryFrom((string) $item->status) ?? AdvisoryStatus::LIVE;
        $item->severity_enum  = $item->severity ? Severity::tryFrom((string) $item->severity) : null;
        $item->derived_status = (new VelService($this->getDatabase(), $repository))->deriveStatus($full);
        $item->problems       = (new VelService($this->getDatabase(), $repository))->validateForPublication($full);

        $item->history = $this->getDatabase()->setQuery(
            $this->getDatabase()->getQuery(true)
                ->select($this->getDatabase()->quoteName(['h.id', 'h.revision', 'h.state', 'h.created', 'h.created_by', 'u.name']))
                ->from($this->getDatabase()->quoteName('#__vel_advisory_history', 'h'))
                ->leftJoin($this->getDatabase()->quoteName('#__users', 'u'), $this->getDatabase()->quoteName('u.id') . ' = ' . $this->getDatabase()->quoteName('h.created_by'))
                ->where($this->getDatabase()->quoteName('h.advisory_id') . ' = :id')
                ->bind(':id', $item->id, ParameterType::INTEGER)
                ->order($this->getDatabase()->quoteName('h.revision') . ' DESC')
        )->loadObjectList() ?: [];

        $item->reports = $this->getDatabase()->setQuery(
            $this->getDatabase()->getQuery(true)
                ->select($this->getDatabase()->quoteName(['id', 'kind', 'state', 'reporter_name', 'reporter_organisation', 'extension_name', 'extension_version', 'created', 'channel']))
                ->from($this->getDatabase()->quoteName('#__vel_reports'))
                ->where($this->getDatabase()->quoteName('advisory_id') . ' = :id')
                ->bind(':id', $item->id, ParameterType::INTEGER)
                ->order($this->getDatabase()->quoteName('created') . ' ASC')
        )->loadObjectList() ?: [];

        return $item;
    }

    /**
     * Save the editable half of an advisory and its children.
     *
     * @param array $data The submitted data.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function save($data): bool
    {
        foreach (self::OWNED as $owned) {
            unset($data[$owned]);
        }

        $db     = $this->getDatabase();
        $bridge = new JedBridge($db);

        // --- Children: normalise before anything is written -----------------------------------
        try {
            $products   = $this->normaliseProducts((array) ($data['products'] ?? []), $bridge);
            $cwes       = $this->normaliseCwes((array) ($data['cwes'] ?? []));
            $references = $this->normaliseReferences((array) ($data['references'] ?? []));
            $credits    = $this->normaliseCredits((array) ($data['credits'] ?? []));
        } catch (UnexpectedValueException | InvalidArgumentException $e) {
            $this->setError($e->getMessage());

            return false;
        }

        unset($data['products'], $data['cwes'], $data['references'], $data['credits']);

        // --- CVSS: the vector is the source of truth ------------------------------------------
        $vector = trim((string) ($data['cvss_vector'] ?? ''));
        $score  = isset($data['cvss_score']) && $data['cvss_score'] !== '' ? (float) $data['cvss_score'] : null;

        if ($vector !== '') {
            try {
                $result = Cvss::evaluate($vector, $score);
            } catch (CvssException $e) {
                $this->setError(Text::sprintf('COM_VEL_ERROR_CVSS', Text::sprintf($e->getMessage(), ...$e->args)));

                return false;
            }

            $data['cvss_version']    = $result->version;
            $data['cvss_vector']     = $result->vector;
            $data['cvss_score']      = $result->score;
            $data['severity']        = $result->severity->value;
            $data['severity_reason'] = '';
        } else {
            $data['cvss_version'] = null;
            $data['cvss_vector']  = null;
            $data['cvss_score']   = null;
            $manual               = Severity::tryFrom((string) ($data['severity'] ?? ''));
            $data['severity']     = $manual?->value;

            if ($manual === null) {
                $data['severity_reason'] = '';
            }
        }

        // --- Status: derived unless pinned; written by the service after the children exist ---
        $status   = AdvisoryStatus::tryFrom((string) ($data['status'] ?? '')) ?? AdvisoryStatus::LIVE;
        $override = (int) ($data['status_override'] ?? 0) === 1;
        unset($data['status'], $data['status_override']);

        $isNew = empty($data['id']);

        if ($isNew) {
            $data['state']  = AdvisoryState::DRAFT->value;
            $data['status'] = $status->value;
            $data['source'] = 'manual';
        }

        $db->transactionStart();

        try {
            if (!parent::save($data)) {
                $db->transactionRollback();

                return false;
            }

            $id = (int) $this->getState($this->getName() . '.id');

            $this->writeChildren($id, $products, $cwes, $references, $credits);

            $db->transactionCommit();
        } catch (Throwable $e) {
            $db->transactionRollback();
            $this->setError($e->getMessage());

            return false;
        }

        $service = new VelService($db);
        $actor   = (int) Factory::getApplication()->getIdentity()->id;

        $service->setStatus($id, $status, $override, $actor);
        $service->touchPublished($id, $actor);

        return true;
    }

    /**
     * Replace the child rows. Delete-and-insert keyed by advisory id: at this scale simpler
     * than diffing, and nothing else references the children's ids.
     *
     * @param int   $advisoryId The advisory.
     * @param array $products   Normalised products, each with `versions`.
     * @param array $cwes       Normalised CWEs.
     * @param array $references Normalised references.
     * @param array $credits    Normalised credits.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function writeChildren(int $advisoryId, array $products, array $cwes, array $references, array $credits): void
    {
        $db = $this->getDatabase();

        foreach (['#__vel_advisory_products', '#__vel_advisory_cwes', '#__vel_advisory_references', '#__vel_advisory_credits'] as $table) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->where($db->quoteName('advisory_id') . ' = :id')
                    ->bind(':id', $advisoryId, ParameterType::INTEGER)
            )->execute();
        }

        foreach ($products as $ordering => $product) {
            $versions = $product['versions'];
            unset($product['versions'], $product['id']);

            $row              = (object) $product;
            $row->advisory_id = $advisoryId;
            $row->ordering    = $ordering;

            $db->insertObject('#__vel_advisory_products', $row, 'id');

            foreach ($versions as $vOrdering => $version) {
                unset($version['id']);

                $vRow             = (object) $version;
                $vRow->product_id = (int) $row->id;
                $vRow->ordering   = $vOrdering;

                $db->insertObject('#__vel_advisory_versions', $vRow, 'id');
            }
        }

        foreach ($cwes as $ordering => $cwe) {
            unset($cwe['id']);
            $row              = (object) $cwe;
            $row->advisory_id = $advisoryId;
            $row->ordering    = $ordering;
            $db->insertObject('#__vel_advisory_cwes', $row, 'id');
        }

        foreach ($references as $ordering => $reference) {
            unset($reference['id']);
            $row              = (object) $reference;
            $row->advisory_id = $advisoryId;
            $row->ordering    = $ordering;
            $db->insertObject('#__vel_advisory_references', $row, 'id');
        }

        foreach ($credits as $ordering => $credit) {
            unset($credit['id']);
            $row              = (object) $credit;
            $row->advisory_id = $advisoryId;
            $row->ordering    = $ordering;
            $db->insertObject('#__vel_advisory_credits', $row, 'id');
        }
    }

    /**
     * Subform rows come keyed "products0", "products1"... or numerically, depending on where
     * they came from; both become a list.
     *
     * @param array $rows The subform value.
     *
     * @return array<int, array>
     *
     * @since 4.0.0
     */
    private function rows(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (\is_object($row)) {
                $row = (array) $row;
            }

            if (\is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param array     $rows   The products subform value.
     * @param JedBridge $bridge To resolve the listing reference.
     *
     * @return array<int, array>
     *
     * @throws UnexpectedValueException  A listing reference that names nothing.
     * @throws InvalidArgumentException  A version bound that cannot be read.
     *
     * @since 4.0.0
     */
    private function normaliseProducts(array $rows, JedBridge $bridge): array
    {
        $products = [];

        foreach ($this->rows($rows) as $index => $row) {
            $vendor  = trim((string) ($row['vendor'] ?? ''));
            $product = trim((string) ($row['product'] ?? ''));

            if ($vendor === '' && $product === '' && trim((string) ($row['extension_id'] ?? '')) === '') {
                // An empty row the editor added and left; not an error.
                continue;
            }

            $extensionId = $bridge->resolveListing($row['extension_id'] ?? '');

            // A listing named but nothing else typed: fill vendor and product from it.
            if ($extensionId !== null && ($vendor === '' || $product === '')) {
                $defaults = $bridge->productDefaults($extensionId) ?? [];
                $vendor   = $vendor !== '' ? $vendor : (string) ($defaults['vendor'] ?? '');
                $product  = $product !== '' ? $product : (string) ($defaults['product'] ?? '');

                foreach (['supplier_url', 'ext_type', 'element', 'folder', 'client_id'] as $key) {
                    if (trim((string) ($row[$key] ?? '')) === '' && isset($defaults[$key])) {
                        $row[$key] = $defaults[$key];
                    }
                }
            }

            if ($vendor === '' || $product === '') {
                throw new UnexpectedValueException(Text::sprintf('COM_VEL_ERROR_PRODUCT_INCOMPLETE', $index + 1));
            }

            $identity = Identity::normalise($row['ext_type'] ?? null, $row['element'] ?? null, $row['folder'] ?? null, $row['client_id'] ?? null);

            $versions = [];

            foreach ($this->rows((array) ($row['versions'] ?? [])) as $vIndex => $version) {
                $from = trim((string) ($version['version_from'] ?? ''));
                $to   = trim((string) ($version['version_to'] ?? ''));

                if ($from === '' && $to === '') {
                    continue;
                }

                $from = $from === '' ? Version::ANY : $from;
                $to   = $to === '' ? Version::ANY : $to;

                foreach ([$from, $to] as $bound) {
                    if ($bound !== Version::ANY && Version::parse($bound) === null) {
                        throw new InvalidArgumentException(Text::sprintf('COM_VEL_ERROR_VERSION_UNREADABLE', $bound, $product));
                    }
                }

                $fromV = Version::parse($from);
                $toV   = Version::parse($to);

                if ($fromV !== null && $toV !== null && $fromV->compare($toV) > 0) {
                    throw new InvalidArgumentException(Text::sprintf('COM_VEL_ERROR_VERSION_RANGE_INVERTED', $from, $to, $product));
                }

                $versions[] = [
                    'id'           => (int) ($version['id'] ?? 0),
                    'version_from' => mb_substr($from, 0, 50),
                    'version_to'   => mb_substr($to, 0, 50),
                    'version_type' => \in_array((string) ($version['version_type'] ?? ''), ['custom', 'semver'], true) ? (string) $version['version_type'] : 'custom',
                ];
            }

            $fixed = trim((string) ($row['fixed_version'] ?? ''));

            if ($fixed !== '' && Version::parse($fixed) === null) {
                throw new InvalidArgumentException(Text::sprintf('COM_VEL_ERROR_VERSION_UNREADABLE', $fixed, $product));
            }

            $products[] = [
                'id'             => (int) ($row['id'] ?? 0),
                'vendor'         => mb_substr($vendor, 0, 255),
                'product'        => mb_substr($product, 0, 255),
                'extension_id'   => $extensionId,
                'supplier_url'   => mb_substr(trim((string) ($row['supplier_url'] ?? '')), 0, 255),
                'ext_type'       => $identity->type?->value,
                'element'        => $identity->element,
                'folder'         => $identity->folder,
                'client_id'      => $identity->clientId,
                'fixed_version'  => mb_substr($fixed, 0, 50),
                'patch_url'      => mb_substr(trim((string) ($row['patch_url'] ?? '')), 0, 255),
                'default_status' => \in_array((string) ($row['default_status'] ?? ''), ['unaffected', 'affected', 'unknown'], true) ? (string) $row['default_status'] : 'unaffected',
                'versions'       => $versions,
            ];
        }

        return $products;
    }

    /**
     * @param array $rows The CWE subform value.
     *
     * @return array<int, array>
     *
     * @since 4.0.0
     */
    private function normaliseCwes(array $rows): array
    {
        $db    = $this->getDatabase();
        $cwes  = [];
        $seen  = [];
        $names = [];

        foreach ($this->rows($rows) as $row) {
            $id = strtoupper(trim((string) ($row['cwe_id'] ?? '')));

            if ($id === '') {
                continue;
            }

            if (preg_match('/^\d+$/', $id)) {
                $id = 'CWE-' . $id;
            }

            if (!preg_match('/^CWE-\d{1,5}$/', $id)) {
                throw new InvalidArgumentException(Text::sprintf('COM_VEL_ERROR_CWE_INVALID', $id));
            }

            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id]   = true;
            $description = trim((string) ($row['description'] ?? ''));

            if ($description === '') {
                if ($names === []) {
                    $names = $db->setQuery(
                        $db->getQuery(true)->select($db->quoteName(['cwe_id', 'name']))->from($db->quoteName('#__vel_cwe'))
                    )->loadAssocList('cwe_id', 'name') ?: [];
                }

                $description = isset($names[$id]) ? $id . ' ' . $names[$id] : $id;
            }

            $cwes[] = [
                'id'          => (int) ($row['id'] ?? 0),
                'cwe_id'      => $id,
                'description' => mb_substr($description, 0, 255),
            ];
        }

        return $cwes;
    }

    /**
     * @param array $rows The references subform value.
     *
     * @return array<int, array>
     *
     * @since 4.0.0
     */
    private function normaliseReferences(array $rows): array
    {
        $references = [];
        $valid      = array_map(static fn (ReferenceTag $t): string => $t->value, ReferenceTag::cases());

        foreach ($this->rows($rows) as $row) {
            $url = trim((string) ($row['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            if (!preg_match('~^https?://~i', $url)) {
                throw new InvalidArgumentException(Text::sprintf('COM_VEL_ERROR_REFERENCE_URL', $url));
            }

            $tags = $row['tags'] ?? [];

            if (\is_string($tags)) {
                $decoded = json_decode($tags, true);
                $tags    = \is_array($decoded) ? $decoded : explode(',', $tags);
            }

            $tags = array_values(array_unique(array_filter(array_map('trim', array_map('strval', (array) $tags)), static fn (string $t): bool => \in_array($t, $valid, true))));

            $references[] = [
                'id'   => (int) ($row['id'] ?? 0),
                'url'  => mb_substr($url, 0, 1024),
                'name' => mb_substr(trim((string) ($row['name'] ?? '')), 0, 255),
                'tags' => json_encode($tags),
            ];
        }

        return $references;
    }

    /**
     * @param array $rows The credits subform value.
     *
     * @return array<int, array>
     *
     * @since 4.0.0
     */
    private function normaliseCredits(array $rows): array
    {
        $credits = [];

        foreach ($this->rows($rows) as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $role = CreditRole::tryFrom((string) ($row['role'] ?? '')) ?? CreditRole::FINDER;

            $credits[] = [
                'id'        => (int) ($row['id'] ?? 0),
                'name'      => mb_substr($name, 0, 255),
                'role'      => $role->value,
                'is_public' => (int) ($row['is_public'] ?? 1) === 1 ? 1 : 0,
                'user_id'   => (int) ($row['user_id'] ?? 0) > 0 ? (int) $row['user_id'] : null,
                'report_id' => (int) ($row['report_id'] ?? 0) > 0 ? (int) $row['report_id'] : null,
            ];
        }

        return $credits;
    }

    /**
     * Deleting a published advisory is not an edit: retire it instead, so the URL keeps
     * answering. Drafts and ready ones can go.
     *
     * @param object $record The record.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    protected function canDelete($record): bool
    {
        if (!empty($record->state) && \in_array((string) $record->state, [AdvisoryState::PUBLISHED->value, AdvisoryState::RETIRED->value], true)) {
            return false;
        }

        return Factory::getApplication()->getIdentity()->authorise('core.delete', 'com_vel');
    }

    /**
     * @param Table $table The table.
     *
     * @return void
     *
     * @since 4.0.0
     */
    protected function prepareTable($table): void
    {
        if (empty($table->id)) {
            $table->state = $table->state ?: AdvisoryState::DRAFT->value;
        }
    }
}
