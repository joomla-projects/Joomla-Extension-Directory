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
use Jed\Component\Vel\Administrator\Version\Identity;

/**
 * The public JSON v2 shape of an advisory (VEL2-PRD Appendix B.1), built once here and used by
 * the list endpoint, the single endpoint, the dataset and the check API - so that four outputs
 * cannot disagree about a field name.
 *
 * Nothing in here reads the reports, participants or messages tables, and nothing internal -
 * notes, assignee, published JSON - is copied in. The output is public by construction.
 *
 * @since 4.0.0
 */
final class AdvisorySerializer
{
    /**
     * @param string $siteRoot  The absolute site root, without trailing slash.
     * @param string $listPath  The path of the advisories list, e.g. "/vulnerable-extensions".
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly string $siteRoot,
        private readonly string $listPath = '/vulnerable-extensions'
    ) {
    }

    /**
     * @param object $advisory An advisory with children.
     *
     * @return string  The canonical public URL.
     *
     * @since 4.0.0
     */
    public function url(object $advisory): string
    {
        return $this->siteRoot . $this->listPath . '/' . (int) $advisory->id . '-' . (string) $advisory->alias;
    }

    /**
     * @param object $advisory An advisory with children (from AdvisoryRepository).
     *
     * @return array<string, mixed>
     *
     * @since 4.0.0
     */
    public function serialize(object $advisory): array
    {
        $retired = (string) $advisory->state === AdvisoryState::RETIRED->value;

        $affected = [];

        foreach ((array) ($advisory->products ?? []) as $product) {
            $identity = Identity::normalise($product->ext_type, $product->element, $product->folder, $product->client_id);
            $ranges   = [];

            foreach ((array) ($product->versions ?? []) as $version) {
                $ranges[] = [
                    'from' => (string) $version->version_from,
                    'to'   => (string) $version->version_to,
                ];
            }

            $affected[] = [
                'vendor'            => (string) $product->vendor,
                'product'           => (string) $product->product,
                'jed_extension_id'  => $product->extension_id !== null ? (int) $product->extension_id : null,
                'jed_url'           => $product->extension_id !== null ? $this->siteRoot . '/extension/' . (int) $product->extension_id : null,
                'supplier_url'      => (string) $product->supplier_url !== '' ? (string) $product->supplier_url : null,
                'identity'          => $identity->isKnown() ? [
                    'type'      => $identity->type->value,
                    'element'   => $identity->element,
                    'folder'    => $identity->folder,
                    'client_id' => $identity->clientId,
                ] : null,
                'affected_versions' => $ranges,
                'fixed_version'     => (string) $product->fixed_version !== '' ? (string) $product->fixed_version : null,
                'patch_url'         => (string) $product->patch_url !== '' ? (string) $product->patch_url : null,
            ];
        }

        $cwes = [];

        foreach ((array) ($advisory->cwes ?? []) as $cwe) {
            $cwes[] = ['id' => (string) $cwe->cwe_id, 'name' => (string) $cwe->description];
        }

        $references = [];

        foreach ((array) ($advisory->references ?? []) as $reference) {
            $references[] = [
                'url'  => (string) $reference->url,
                'name' => (string) $reference->name !== '' ? (string) $reference->name : null,
                'tags' => array_values((array) $reference->tags),
            ];
        }

        $credits = [];

        foreach ((array) ($advisory->credits ?? []) as $credit) {
            if ((int) $credit->is_public !== 1) {
                continue;
            }

            $credits[] = ['name' => (string) $credit->name, 'role' => (string) $credit->role];
        }

        $description = (string) ($advisory->description ?? '');

        return [
            'id'               => (int) $advisory->id,
            'vel_id'           => \sprintf('VEL-%04d', (int) $advisory->id),
            'cve_id'           => $advisory->cve_id ?: null,
            'cve_assigner'     => (string) $advisory->cve_assigner !== '' ? (string) $advisory->cve_assigner : null,
            'url'              => $this->url($advisory),
            'title'            => (string) $advisory->title,
            'status'           => (string) $advisory->status,
            'severity'         => $advisory->severity ?: null,
            'cvss'             => $advisory->cvss_vector ? [
                'version' => (string) $advisory->cvss_version,
                'vector'  => (string) $advisory->cvss_vector,
                'score'   => (float) $advisory->cvss_score,
            ] : null,
            'exploited'        => (string) $advisory->exploited,
            'description_html' => $description,
            'description_text' => self::text($description),
            'solution'         => (string) ($advisory->solution ?? '') !== '' ? (string) $advisory->solution : null,
            'workaround'       => (string) ($advisory->workaround ?? '') !== '' ? (string) $advisory->workaround : null,
            'cwe'              => $cwes,
            'affected'         => $affected,
            'references'       => $references,
            'credits'          => $credits,
            'dates'            => [
                'reported'        => self::iso($advisory->date_reported ?? null),
                'vendor_notified' => self::iso($advisory->date_vendor_notified ?? null),
                'reserved'        => self::iso($advisory->date_reserved ?? null),
                'published'       => self::iso($advisory->date_published ?? null),
                'updated'         => self::iso($advisory->date_updated ?? null),
                'fixed'           => self::iso($advisory->date_fixed ?? null),
            ],
            'withdrawn'        => $retired ? self::iso($advisory->date_retired ?? null) : null,
            'withdrawn_reason' => $retired ? ($advisory->retired_reason ?: null) : null,
            'links'            => [
                'html'     => $this->url($advisory),
                'json'     => $this->siteRoot . $this->listPath . '/api/v2/advisories/' . (int) $advisory->id . '.json',
                'cve_json' => $advisory->cve_id ? $this->siteRoot . $this->listPath . '/api/v2/cve/' . (string) $advisory->cve_id . '.json' : null,
            ],
        ];
    }

    /**
     * The list-level subset: what the HTML list shows (brief §4.1), for the paged endpoint.
     *
     * @param object $advisory An advisory with children.
     *
     * @return array<string, mixed>
     *
     * @since 4.0.0
     */
    public function summary(object $advisory): array
    {
        $full = $this->serialize($advisory);

        unset($full['description_html'], $full['references'], $full['credits'], $full['workaround']);

        return $full;
    }

    /**
     * @param string $html HTML.
     *
     * @return string  Plain text, whitespace collapsed.
     *
     * @since 4.0.0
     */
    public static function text(string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/li|\/h\d)[^>]*>/i', "\n", $html) ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\n{3,}/', "\n\n", $text) ?? $text) ?? $text);
    }

    /**
     * @param string|null $sql A datetime in SQL form (UTC), or null.
     *
     * @return string|null  ISO 8601 in UTC with a Z, or null.
     *
     * @since 4.0.0
     */
    public static function iso(?string $sql): ?string
    {
        if ($sql === null || $sql === '' || str_starts_with($sql, '0000')) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($sql, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
