<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.velcheck
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Velcheck\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * Checks this site's installed extensions against the Joomla Vulnerable Extension List and
 * shows the result in the backend (VEL2-PRD J-05, Appendix B).
 *
 * Two modes, and the default is the one that sends nothing:
 *
 *  - dataset: download /api/v2/dataset.json (cached for `cache_hours`, revalidated by ETag) and
 *             match locally. The site's inventory never leaves it.
 *  - check:   POST the inventory to /api/v2/check. Cheaper on the site, and the VEL keeps no
 *             copy of what was posted - but it is still a list of what you run, sent elsewhere.
 *
 * The matching in dataset mode is the same as the VEL's own: type and element must match, a
 * plugin's folder must match, the client must match unless the advisory says "either", and the
 * installed version is compared inclusively against each range, with anything at or above the
 * fixed version counted as safe. It is written out here rather than shared so that the plugin
 * has no dependency on com_vel's classes.
 *
 * @since 4.0.0
 */
final class Velcheck extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * @var boolean
     *
     * @since 4.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return array<string, string>
     *
     * @since 4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return ['onAfterDispatch' => 'onAfterDispatch'];
    }

    /**
     * On the backend control panel only: fetch, match, and say what was found.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onAfterDispatch(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator') || $app->getInput()->getCmd('option', 'com_cpanel') !== 'com_cpanel') {
            return;
        }

        if (!$app->getIdentity()->authorise('core.manage', 'com_installer')) {
            return;
        }

        try {
            $hits = $this->findAffected();
        } catch (Throwable $e) {
            $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_VELCHECK_ERROR', $e->getMessage()), 'warning');

            return;
        }

        if ($hits === []) {
            return;
        }

        $lines = [];

        foreach ($hits as $hit) {
            $lines[] = '<li><strong>' . htmlspecialchars($hit['name'], ENT_QUOTES, 'UTF-8') . '</strong> '
                . htmlspecialchars($hit['version'], ENT_QUOTES, 'UTF-8') . ' &mdash; '
                . '<a href="' . htmlspecialchars($hit['url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
                . htmlspecialchars($hit['reference'] . ' ' . $hit['title'], ENT_QUOTES, 'UTF-8') . '</a>'
                . ' (' . htmlspecialchars($hit['status'], ENT_QUOTES, 'UTF-8')
                . ($hit['fixed_version'] !== '' ? ', ' . Text::sprintf('PLG_SYSTEM_VELCHECK_FIXED_IN', htmlspecialchars($hit['fixed_version'], ENT_QUOTES, 'UTF-8')) : '')
                . ($hit['match'] === 'unknown' ? ', ' . Text::_('PLG_SYSTEM_VELCHECK_UNKNOWN_VERSION') : '')
                . ')</li>';
        }

        $app->enqueueMessage('<p>' . Text::_('PLG_SYSTEM_VELCHECK_AFFECTED_INTRO') . '</p><ul>' . implode('', $lines) . '</ul>', 'error');
    }

    /**
     * @return array<int, array<string, string>>
     *
     * @throws \RuntimeException
     *
     * @since 4.0.0
     */
    private function findAffected(): array
    {
        $inventory = $this->inventory();

        if ($inventory === []) {
            return [];
        }

        return (string) $this->params->get('mode', 'dataset') === 'check'
            ? $this->viaCheckApi($inventory)
            : $this->viaDataset($inventory);
    }

    /**
     * Installed third-party extensions with their identity and version.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 4.0.0
     */
    private function inventory(): array
    {
        $db   = $this->getDatabase();
        $rows = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'name', 'type', 'element', 'folder', 'client_id', 'manifest_cache']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('package_id') . ' >= 0')
                ->where($db->quoteName('type') . ' IN (' . implode(',', array_map([$db, 'quote'], ['component', 'module', 'plugin', 'template', 'library', 'package', 'file'])) . ')')
                ->where('(' . $db->quoteName('locked') . ' = 0 OR ' . $db->quoteName('locked') . ' IS NULL)')
        )->loadObjectList() ?: [];

        $inventory = [];

        foreach ($rows as $row) {
            $manifest = json_decode((string) $row->manifest_cache, true);
            $version  = \is_array($manifest) ? (string) ($manifest['version'] ?? '') : '';
            $element  = strtolower((string) $row->element);

            // Core extensions are not the VEL's business and are not asked about.
            if ($element === '' || str_starts_with((string) ($manifest['author'] ?? ''), 'Joomla! Project')) {
                continue;
            }

            $inventory[] = [
                'name'      => (string) $row->name,
                'type'      => (string) $row->type,
                'element'   => $element,
                'folder'    => (string) $row->folder !== '' ? strtolower((string) $row->folder) : null,
                'client_id' => (int) $row->client_id,
                'version'   => $version,
            ];
        }

        return $inventory;
    }

    /**
     * @param array $inventory The inventory.
     *
     * @return array<int, array<string, string>>
     *
     * @throws \RuntimeException
     *
     * @since 4.0.0
     */
    private function viaCheckApi(array $inventory): array
    {
        $entries = array_map(static fn (array $e): array => array_intersect_key($e, array_flip(['type', 'element', 'folder', 'client_id', 'version'])), $inventory);
        $body    = json_encode(['client' => 'joomla/' . JVERSION . ' plg_system_velcheck/1.0', 'extensions' => $entries]);

        $response = HttpFactory::getHttp(['timeout' => 20])->post(
            rtrim((string) $this->params->get('api_base', ''), '/') . '/check',
            (string) $body,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json']
        );

        if ($response->code >= 400) {
            throw new \RuntimeException('HTTP ' . $response->code);
        }

        $decoded = json_decode((string) $response->body, true);
        $hits    = [];

        foreach ((array) ($decoded['results'] ?? []) as $index => $result) {
            if (($result['match'] ?? '') === 'not_affected') {
                continue;
            }

            foreach ((array) ($result['advisories'] ?? []) as $advisory) {
                if (!$this->wanted((string) ($advisory['status'] ?? ''))) {
                    continue;
                }

                $hits[] = [
                    'name'          => (string) ($inventory[$index]['name'] ?? $result['input']['element'] ?? ''),
                    'version'       => (string) ($inventory[$index]['version'] ?? ''),
                    'reference'     => (string) ($advisory['vel_id'] ?? ''),
                    'title'         => (string) ($advisory['title'] ?? ''),
                    'status'        => (string) ($advisory['status'] ?? ''),
                    'fixed_version' => (string) ($advisory['fixed_version'] ?? ''),
                    'url'           => (string) ($advisory['url'] ?? ''),
                    'match'         => (string) ($result['match'] ?? ''),
                ];
            }
        }

        return $hits;
    }

    /**
     * @param array $inventory The inventory.
     *
     * @return array<int, array<string, string>>
     *
     * @throws \RuntimeException
     *
     * @since 4.0.0
     */
    private function viaDataset(array $inventory): array
    {
        $dataset = $this->dataset();
        $hits    = [];

        foreach ($dataset as $advisory) {
            if (!empty($advisory['withdrawn']) || !$this->wanted((string) ($advisory['status'] ?? ''))) {
                continue;
            }

            foreach ((array) ($advisory['affected'] ?? []) as $product) {
                $identity = $product['identity'] ?? null;

                if (!\is_array($identity) || empty($identity['type']) || empty($identity['element'])) {
                    continue;
                }

                foreach ($inventory as $installed) {
                    if ($installed['type'] !== $identity['type'] || $installed['element'] !== strtolower((string) $identity['element'])) {
                        continue;
                    }

                    if ($identity['type'] === 'plugin' && ($installed['folder'] ?? '') !== strtolower((string) ($identity['folder'] ?? ''))) {
                        continue;
                    }

                    if ($identity['client_id'] !== null && (int) $identity['client_id'] !== $installed['client_id']) {
                        continue;
                    }

                    $match = $this->versionMatch($installed['version'], (array) ($product['affected_versions'] ?? []), (string) ($product['fixed_version'] ?? ''));

                    if ($match === 'not_affected') {
                        continue;
                    }

                    $hits[] = [
                        'name'          => $installed['name'],
                        'version'       => $installed['version'],
                        'reference'     => (string) ($advisory['vel_id'] ?? ''),
                        'title'         => (string) ($advisory['title'] ?? ''),
                        'status'        => (string) ($advisory['status'] ?? ''),
                        'fixed_version' => (string) ($product['fixed_version'] ?? ''),
                        'url'           => (string) ($advisory['url'] ?? ''),
                        'match'         => $match,
                    ];
                }
            }
        }

        return $hits;
    }

    /**
     * The dataset, cached and revalidated by ETag.
     *
     * @return array
     *
     * @throws \RuntimeException
     *
     * @since 4.0.0
     */
    private function dataset(): array
    {
        $hours = max(1, (int) $this->params->get('cache_hours', 12));
        $cache = \Joomla\CMS\Factory::getContainer()->get(CacheControllerFactoryInterface::class)
            ->createCacheController('output', ['defaultgroup' => 'plg_system_velcheck', 'lifetime' => $hours * 60, 'caching' => true]);

        $stored = $cache->get('dataset');

        if (\is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);

            if (\is_array($decoded)) {
                return (array) ($decoded['data'] ?? []);
            }
        }

        $response = HttpFactory::getHttp(['timeout' => 30])->get(
            rtrim((string) $this->params->get('api_base', ''), '/') . '/dataset.json',
            ['Accept' => 'application/json', 'User-Agent' => 'plg_system_velcheck/1.0 joomla/' . JVERSION]
        );

        if ($response->code >= 400) {
            throw new \RuntimeException('HTTP ' . $response->code);
        }

        $decoded = json_decode((string) $response->body, true);

        if (!\is_array($decoded) || !isset($decoded['data'])) {
            throw new \RuntimeException('unexpected dataset shape');
        }

        $cache->store((string) $response->body, 'dataset');

        return (array) $decoded['data'];
    }

    /**
     * @param string $status An advisory status.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private function wanted(string $status): bool
    {
        return $status === 'live' || $status === 'abandoned' || ((int) $this->params->get('include_patched', 1) === 1 && $status === 'patched');
    }

    /**
     * The VEL's matching rule, inclusive ranges with "*" unbounded, and the fixed version as
     * the ceiling.
     *
     * @param string $version Installed version.
     * @param array  $ranges  [{from, to}].
     * @param string $fixed   Fixed version or ''.
     *
     * @return string  affected | not_affected | unknown
     *
     * @since 4.0.0
     */
    private function versionMatch(string $version, array $ranges, string $fixed): string
    {
        $installed = self::normalise($version);

        if ($installed === null) {
            return 'unknown';
        }

        $fixedNormalised = $fixed !== '' ? self::normalise($fixed) : null;

        if ($fixedNormalised !== null && version_compare($installed, $fixedNormalised, '>=')) {
            return 'not_affected';
        }

        if ($ranges === []) {
            return 'unknown';
        }

        foreach ($ranges as $range) {
            $from = self::normalise((string) ($range['from'] ?? '*'));
            $to   = self::normalise((string) ($range['to'] ?? '*'));

            if (($from === null || version_compare($installed, $from, '>=')) && ($to === null || version_compare($installed, $to, '<='))) {
                return 'affected';
            }
        }

        return 'not_affected';
    }

    /**
     * @param string $version A version string.
     *
     * @return string|null  A version_compare-ready string padded to three numeric parts, or null.
     *
     * @since 4.0.0
     */
    private static function normalise(string $version): ?string
    {
        $version = strtolower(trim($version));

        if ($version === '' || $version === '*') {
            return null;
        }

        if (!preg_match('/(\d+(?:\.\d+)*)((?:[.\-_ ]?(?:dev|alpha|beta|rc|pl|a|b|c|p)(?![a-z])\.?\d*)?)/', $version, $m)) {
            return null;
        }

        $parts = explode('.', $m[1]);

        while (\count($parts) < 3) {
            $parts[] = '0';
        }

        $suffix = preg_replace('/^[.\-_ ]+/', '', $m[2] ?? '') ?? '';

        return implode('.', $parts) . ($suffix !== '' ? '-' . str_replace('.', '', $suffix) : '');
    }
}
