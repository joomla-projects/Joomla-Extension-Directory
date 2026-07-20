<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Update;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use SimpleXMLElement;

/**
 * Parses a Joomla extension update-site XML feed (the document referenced by an
 * extension's `<updateservers><server>` URL) — distinct from
 * {@see \Jed\Component\Jed\Administrator\Parser\Parser}, which parses extension
 * *manifests*, not update feeds.
 *
 * @since 4.1.0
 */
class UpdateServerXmlParser
{
    /**
     * Parse the feed and return the highest version it advertises.
     *
     * @param string $xml The raw XML feed content.
     *
     * @return UpdateServerResult|null Null if the feed is empty, malformed, or has no <update> entries.
     *
     * @since 4.1.0
     */
    public function parse(string $xml): ?UpdateServerResult
    {
        $xml = trim($xml);

        if ($xml === '') {
            return null;
        }

        $useErrors = libxml_use_internal_errors(true);
        $document  = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        if ($document === false) {
            return null;
        }

        $best = null;

        foreach ($document->update ?? [] as $update) {
            /** @var SimpleXMLElement $update */
            $version = trim((string) $update->version);

            if ($version === '') {
                continue;
            }

            if ($best === null || version_compare($version, $best->version, '>')) {
                $downloadUrl = null;

                foreach ($update->downloads->downloadurl ?? [] as $downloadUrlNode) {
                    $downloadUrl = trim((string) $downloadUrlNode);
                    break;
                }

                $best = new UpdateServerResult($version, $downloadUrl ?: null);
            }
        }

        return $best;
    }
}
