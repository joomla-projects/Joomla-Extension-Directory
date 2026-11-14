<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\View\Legacyfeed;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Jed\Component\Vel\Administrator\Service\AdvisorySerializer;
use Jed\Component\Vel\Site\Helper\VelHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\AbstractView;
use Joomla\Database\DatabaseInterface;

/**
 * The pre-VEL2 feed at /vulnerable-extensions/json-feed (VEL2-PRD §6.8.3, D-8).
 *
 * The field names below are the ones the legacy article-based feed exposed, reconstructed from
 * the legacy com_jed VEL schema (`velvulnerableitem.xml`: risk_level, start_version,
 * vulnerable_version, patch_version, recommendation, update_notice, exploit_type, cve_id ...)
 * and the Live / Resolved / Unresolved category names. Research task R-04 captures the live
 * feed verbatim and this view is adjusted to match it byte for byte before go-live; until then
 * it is the best reconstruction the repository allows.
 *
 * Sent with Deprecation and Sunset headers and a `_deprecated` pointer to the v2 API.
 *
 * @since 4.0.0
 */
class JsonView extends AbstractView
{
    /**
     * @param string|null $tpl Unused.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        if ((int) VelHelper::option('legacy_feed_enabled', 1) !== 1) {
            $this->gone();

            return;
        }

        $sunset = (string) (VelHelper::option('legacy_feed_sunset_date') ?? '');

        if ($sunset !== '' && strtotime($sunset) !== false && strtotime($sunset) < time()) {
            $this->gone();

            return;
        }

        $repository = new AdvisoryRepository(Factory::getContainer()->get(DatabaseInterface::class));
        $rows       = $repository->allPublished(0);
        $serializer = VelHelper::serializer();
        $items      = [];

        foreach ($rows as $row) {
            $status   = AdvisoryStatus::tryFrom((string) $row->status) ?? AdvisoryStatus::LIVE;
            // Legacy category names: a consumer contract, not translated (see legacyRisk()).
            $category = match ($status) {
                AdvisoryStatus::LIVE      => 'Live VEL',
                AdvisoryStatus::PATCHED   => 'Resolved VEL',
                AdvisoryStatus::ABANDONED => 'Unresolved VEL',
            };

            // The legacy feed was one product per entry; a multi-product advisory becomes one
            // entry per product so that legacy checkers matching on the name still match.
            $products = (array) $row->products ?: [null];

            foreach ($products as $product) {
                $from = '';
                $to   = '';

                foreach ((array) ($product->versions ?? []) as $version) {
                    $from = $from === '' ? (string) $version->version_from : $from;
                    $to   = (string) $version->version_to;
                }

                $items[] = [
                    'id'                 => (int) $row->id,
                    'title'              => (string) $row->title,
                    'alias'              => (string) $row->alias,
                    'category'           => $category,
                    'status'             => $status->value,
                    'publish_up'         => (string) ($row->date_published ?? ''),
                    'modified'           => (string) ($row->date_updated ?? ''),
                    'link'               => $serializer->url($row),
                    'vulnerable_item'    => $product ? (string) $product->product : '',
                    'vendor'             => $product ? (string) $product->vendor : '',
                    'jed'                => $product && $product->extension_id ? $serializer->url($row) : '',
                    'risk_level'         => self::legacyRisk((string) $row->severity),
                    'start_version'      => $from === '*' ? '' : $from,
                    'vulnerable_version' => $to === '*' ? '' : $to,
                    'patch_version'      => $product ? (string) $product->fixed_version : '',
                    'recommendation'     => (string) ($row->solution ?? ''),
                    'update_notice'      => $product ? (string) $product->patch_url : '',
                    'exploit_type'       => self::legacyExploitType((array) $row->cwes),
                    'cve_id'             => (string) ($row->cve_id ?? ''),
                    'cwe_id'             => implode(', ', array_map(static fn ($c): string => (string) $c->cwe_id, (array) $row->cwes)),
                    'cvss_base_score'    => $row->cvss_score !== null ? (string) $row->cvss_score : '',
                    'introtext'          => AdvisorySerializer::text((string) $row->description),
                    'element'            => $product ? (string) ($product->element ?? '') : '',
                    'extension_type'     => $product ? (string) ($product->ext_type ?? '') : '',
                ];
            }
        }

        $document = [
            '_deprecated' => [
                'message' => Text::_('COM_VEL_LEGACY_FEED_DEPRECATED'),
                'api'     => rtrim(\Joomla\CMS\Uri\Uri::root(), '/') . VelHelper::listPath() . '/api/v2/index.json',
                'sunset'  => $sunset !== '' ? $sunset : null,
            ],
            'items' => $items,
        ];

        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Access-Control-Allow-Origin', '*', true);
        $app->setHeader('Cache-Control', 'public, max-age=' . max(0, (int) VelHelper::option('api_cache_seconds', 300)), true);
        $app->setHeader('Deprecation', 'true', true);
        $app->setHeader('Link', '<' . $document['_deprecated']['api'] . '>; rel="successor-version"', true);

        if ($sunset !== '' && strtotime($sunset) !== false) {
            $app->setHeader('Sunset', gmdate('D, d M Y H:i:s \G\M\T', strtotime($sunset)), true);
        }

        $app->sendHeaders();

        echo $json;

        $app->close();
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    private function gone(): void
    {
        $app = Factory::getApplication();
        $app->setHeader('status', '410', true);
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->sendHeaders();

        echo json_encode(['error' => ['code' => 'gone', 'message' => Text::_('COM_VEL_LEGACY_FEED_GONE'), 'api' => rtrim(\Joomla\CMS\Uri\Uri::root(), '/') . VelHelper::listPath() . '/api/v2/index.json']]);

        $app->close();
    }

    /**
     * The vocabulary below ('Live VEL', 'Very High', 'SQL Injection', ...) is deliberately not
     * translated: it reproduces the legacy feed's exact field values, which existing consumers
     * compare against as strings. Changing them by language would break those consumers - which
     * is the one thing a compatibility feed must not do.
     *
     * @param string $severity A severity.
     *
     * @return string  The legacy risk level vocabulary.
     *
     * @since 4.0.0
     */
    private static function legacyRisk(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Very High',
            'high'     => 'High',
            'medium'   => 'Medium',
            'low'      => 'Low',
            default    => '',
        };
    }

    /**
     * @param object[] $cwes The advisory's CWEs.
     *
     * @return string  The legacy exploit type label for the first CWE that maps.
     *
     * @since 4.0.0
     */
    private static function legacyExploitType(array $cwes): string
    {
        $map = [
            'CWE-89'  => 'SQL Injection',
            'CWE-79'  => 'XSS (Cross Site Scripting)',
            'CWE-80'  => 'XSS (Cross Site Scripting)',
            'CWE-22'  => 'Directory Traversal',
            'CWE-23'  => 'Directory Traversal',
            'CWE-200' => 'Information Disclosure',
        ];

        foreach ($cwes as $cwe) {
            if (isset($map[(string) $cwe->cwe_id])) {
                return $map[(string) $cwe->cwe_id];
            }
        }

        return $cwes === [] ? '' : 'Other';
    }
}
