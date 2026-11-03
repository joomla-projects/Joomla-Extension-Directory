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

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Jed\Component\Vel\Administrator\Service\AdvisorySerializer;
use Jed\Component\Vel\Administrator\Version\Identity;
use Jed\Component\Vel\Administrator\Version\Matcher;
use Jed\Component\Vel\Administrator\Version\VersionRange;
use Jed\Component\Vel\Site\Helper\VelHelper;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * The JSON v2 endpoints' data (VEL2-PRD §6.8.2, Appendix B).
 *
 * Stateless by construction: the check endpoint reads the posted inventory, answers, and keeps
 * nothing - not the inventory, not the address, only a request counter somewhere else. A site's
 * list of installed extensions is a fingerprint (D-9).
 *
 * @since 4.0.0
 */
class ApiModel extends BaseDatabaseModel
{
    /**
     * @return AdvisoryRepository
     *
     * @since 4.0.0
     */
    private function repository(): AdvisoryRepository
    {
        return new AdvisoryRepository($this->getDatabase());
    }

    /**
     * A version key for the whole published set: changes whenever any published advisory
     * changes, so it serves as the dataset's ETag and cache key.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function getDatasetVersion(): string
    {
        $db        = $this->getDatabase();
        $published = AdvisoryState::PUBLISHED->value;
        $retired   = AdvisoryState::RETIRED->value;

        $row = $db->setQuery(
            $db->getQuery(true)
                ->select(['COUNT(*) AS ' . $db->quoteName('n'), 'MAX(' . $db->quoteName('date_updated') . ') AS ' . $db->quoteName('latest'), 'MAX(' . $db->quoteName('modified') . ') AS ' . $db->quoteName('modified')])
                ->from($db->quoteName('#__vel_advisories'))
                ->where($db->quoteName('state') . ' IN (:p, :r)')
                ->bind(':p', $published)
                ->bind(':r', $retired)
        )->loadAssoc() ?: ['n' => 0, 'latest' => '', 'modified' => ''];

        return hash('sha256', 'vel2:' . (int) $row['n'] . ':' . (string) $row['latest'] . ':' . (string) $row['modified']);
    }

    /**
     * The discovery document.
     *
     * @return array<string, mixed>
     *
     * @since 4.0.0
     */
    public function getIndex(): array
    {
        $root = rtrim(\Joomla\CMS\Uri\Uri::root(), '/') . VelHelper::listPath();

        return [
            'name'      => 'Joomla Vulnerable Extension List API',
            'version'   => '2.0',
            'endpoints' => [
                'advisories' => $root . '/api/v2/advisories.json',
                'advisory'   => $root . '/api/v2/advisories/{id|VEL-0042|CVE-YYYY-NNNN}.json',
                'cve'        => $root . '/api/v2/cve/{CVE-YYYY-NNNN}.json',
                'dataset'    => $root . '/api/v2/dataset.json',
                'check'      => $root . '/api/v2/check',
                'rss'        => $root . '?format=feed&type=rss',
                'atom'       => $root . '?format=feed&type=atom',
                'html'       => $root,
            ],
            'filters' => ['status', 'search', 'severity', 'cvss_min', 'cvss_max', 'vendor', 'type', 'element', 'cve', 'from', 'to', 'updated_since', 'include_withdrawn', 'sort', 'dir', 'limit', 'limitstart'],
            'dataset' => [
                'etag'                  => $this->getDatasetVersion(),
                'retired_included_days' => (int) VelHelper::option('retired_in_dataset_days', 365),
            ],
            'check' => [
                'method'      => 'POST',
                'max_entries' => (int) VelHelper::option('check_max_entries', 500),
            ],
            'legacy_feed' => (int) VelHelper::option('legacy_feed_enabled', 1) === 1 ? [
                'url'    => $root . '/json-feed',
                'sunset' => VelHelper::option('legacy_feed_sunset_date') ?: null,
            ] : null,
            'documentation' => 'https://extensions.joomla.org/vulnerable-extensions/api',
        ];
    }

    /**
     * The paged list, on the same filters as the HTML list.
     *
     * @param AdvisoriesModel $list The list model, already populated from the request.
     *
     * @return array<string, mixed>
     *
     * @since 4.0.0
     */
    public function getList(AdvisoriesModel $list): array
    {
        $serializer = VelHelper::serializer();
        $repository = $this->repository();
        $rows       = (array) $list->getItems();
        $total      = (int) $list->getTotal();
        $data       = [];

        foreach ($rows as $row) {
            $full = $repository->find((int) $row->id, true);

            if ($full !== null) {
                $data[] = $serializer->summary($full);
            }
        }

        return [
            'meta' => [
                'total'        => $total,
                'limit'        => (int) $list->getState('list.limit'),
                'start'        => (int) $list->getState('list.start'),
                'generated'    => gmdate('Y-m-d\TH:i:s\Z'),
                'dataset_etag' => $this->getDatasetVersion(),
            ],
            'data' => $data,
        ];
    }

    /**
     * @param string $reference id, "42-alias", "VEL-0042" or "CVE-…".
     *
     * @return array<string, mixed>|null
     *
     * @since 4.0.0
     */
    public function getOne(string $reference): ?array
    {
        $repository = $this->repository();
        $id         = $repository->resolveReference($reference);
        $advisory   = $id > 0 ? $repository->find($id, true) : null;

        return $advisory === null ? null : VelHelper::serializer()->serialize($advisory);
    }

    /**
     * The complete published dataset, cached until something published changes.
     *
     * @return string  The JSON document.
     *
     * @since 4.0.0
     */
    public function getDatasetJson(): string
    {
        $version = $this->getDatasetVersion();

        /** @var CallbackController $cache */
        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
            ->createCacheController('callback', ['defaultgroup' => 'com_vel', 'lifetime' => 1440, 'caching' => true]);

        return (string) $cache->get(function (string $version): string {
            $serializer = VelHelper::serializer();
            $rows       = $this->repository()->allPublished((int) VelHelper::option('retired_in_dataset_days', 365));
            $data       = [];

            foreach ($rows as $row) {
                $data[] = $serializer->serialize($row);
            }

            return json_encode([
                'meta' => [
                    'schema'    => '2.0',
                    'generated' => gmdate('Y-m-d\TH:i:s\Z'),
                    'count'     => \count($data),
                    'etag'      => $version,
                ],
                'data' => $data,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, [$version], 'dataset:' . $version);
    }

    /**
     * The check (VEL2-PRD Appendix B.4).
     *
     * @param array $body The decoded request body.
     *
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException  Bad request.
     *
     * @since 4.0.0
     */
    public function check(array $body): array
    {
        $entries = $body['extensions'] ?? null;

        if (!\is_array($entries)) {
            throw new \InvalidArgumentException('The body must carry an "extensions" array.');
        }

        $max = (int) VelHelper::option('check_max_entries', 500);

        if (\count($entries) > $max) {
            throw new \InvalidArgumentException(\sprintf('At most %d extensions per call.', $max));
        }

        $identities = [];
        $parsed     = [];

        foreach ($entries as $index => $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $identity = Identity::normalise($entry['type'] ?? null, $entry['element'] ?? null, $entry['folder'] ?? null, $entry['client_id'] ?? null);
            $parsed[] = [
                'input' => [
                    'type'      => $identity->type?->value,
                    'element'   => $identity->element,
                    'folder'    => $identity->folder,
                    'client_id' => $identity->clientId,
                    'version'   => (string) ($entry['version'] ?? ''),
                ],
                'identity' => $identity,
                'version'  => (string) ($entry['version'] ?? ''),
            ];

            if ($identity->isKnown()) {
                $identities[] = ['type' => $identity->type->value, 'element' => $identity->element];
            }
        }

        $advisories = $this->repository()->publishedForIdentities($identities);
        $serializer = VelHelper::serializer();
        $results    = [];

        foreach ($parsed as $entry) {
            $matches = [];
            $overall = Matcher::NOT_AFFECTED;
            $reason  = 'no_advisory';

            if (!$entry['identity']->isKnown()) {
                $overall = Matcher::UNKNOWN;
                $reason  = 'identity_incomplete';
            } else {
                foreach ($advisories as $advisory) {
                    foreach ((array) $advisory->products as $product) {
                        $productIdentity = Identity::normalise($product->ext_type, $product->element, $product->folder, $product->client_id);

                        if (!$entry['identity']->matches($productIdentity)) {
                            continue;
                        }

                        $ranges = [];

                        foreach ((array) $product->versions as $version) {
                            $ranges[] = VersionRange::fromStrings((string) $version->version_from, (string) $version->version_to);
                        }

                        $match = Matcher::match($entry['identity'], $entry['version'], $productIdentity, $ranges, (string) $product->fixed_version);

                        if ($match['match'] === Matcher::NOT_AFFECTED) {
                            continue;
                        }

                        $matches[] = [
                            'match'         => $match['match'],
                            'reason'        => $match['reason'],
                            'vel_id'        => \sprintf('VEL-%04d', (int) $advisory->id),
                            'cve_id'        => $advisory->cve_id ?: null,
                            'title'         => (string) $advisory->title,
                            'status'        => (string) $advisory->status,
                            'severity'      => $advisory->severity ?: null,
                            'cvss_score'    => $advisory->cvss_score !== null ? (float) $advisory->cvss_score : null,
                            'product'       => (string) $product->product,
                            'fixed_version' => (string) $product->fixed_version !== '' ? (string) $product->fixed_version : null,
                            'patch_url'     => (string) $product->patch_url !== '' ? (string) $product->patch_url : null,
                            'url'           => $serializer->url($advisory),
                        ];

                        if ($match['match'] === Matcher::AFFECTED) {
                            $overall = Matcher::AFFECTED;
                            $reason  = $match['reason'];
                        } elseif ($overall !== Matcher::AFFECTED) {
                            $overall = Matcher::UNKNOWN;
                            $reason  = $match['reason'];
                        }
                    }
                }
            }

            $results[] = [
                'input'      => $entry['input'],
                'match'      => $overall,
                'reason'     => $reason,
                'advisories' => $matches,
            ];
        }

        return [
            'meta' => [
                'checked'      => \count($results),
                'generated'    => gmdate('Y-m-d\TH:i:s\Z'),
                'dataset_etag' => $this->getDatasetVersion(),
            ],
            'results' => $results,
        ];
    }
}
