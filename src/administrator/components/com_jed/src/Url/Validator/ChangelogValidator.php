<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Url\Validator;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Url\SafeHttpFetcher;
use Jed\Component\Jed\Administrator\Url\UrlCheckResult;
use Jed\Component\Jed\Administrator\Url\UrlValidatorInterface;

/**
 * Joomla's `changelog.xml`, checked for structure.
 *
 * The format is `<changelog><changeset><version>…` with `<security>`, `<fix>`, `<addition>` and
 * friends inside each changeset. Joomla renders it in the update dialogue, so a document that
 * parses but has no changesets shows the user an empty box - which is worse than no changelog
 * URL at all, because it looks like the developer shipped a release with nothing in it.
 *
 * A plain HTML changelog page is not an error. Many developers link to a human-readable page and
 * that is a perfectly good thing to do; it just is not the machine-readable format, so it is
 * reported as a notice rather than a success.
 *
 * @since 4.0.0
 */
class ChangelogValidator implements UrlValidatorInterface
{
    /**
     * @param SafeHttpFetcher $fetcher The guarded fetcher.
     *
     * @since 4.0.0
     */
    public function __construct(protected readonly SafeHttpFetcher $fetcher)
    {
    }

    /**
     * @param string               $url     The changelog URL.
     * @param array<string, mixed> $context Listing context, unused here.
     *
     * @return UrlCheckResult
     *
     * @since 4.0.0
     */
    public function validate(string $url, array $context = []): UrlCheckResult
    {
        $response = $this->fetcher->fetch($url);

        if ($response->refused) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_REFUSED_' . strtoupper($response->reason));
        }

        if (!$response->isOk()) {
            return $response->status === 0
                ? UrlCheckResult::notice('COM_JED_URLCHECK_FAILED_' . strtoupper($response->reason ?: 'FAILED'))
                : UrlCheckResult::notice('COM_JED_URLCHECK_UNEXPECTED_STATUS', ['status' => $response->status], $response->status);
        }

        $body = trim($response->body);

        if (!str_contains($body, '<changelog')) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_CHANGELOG_NOT_XML', [], $response->status);
        }

        $useErrors = libxml_use_internal_errors(true);
        $document  = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        if ($document === false) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_CHANGELOG_MALFORMED', [], $response->status);
        }

        $changesets = 0;
        $newest     = null;

        foreach ($document->changeset ?? [] as $changeset) {
            $changesets++;
            $version = trim((string) $changeset->version);

            if ($version !== '' && ($newest === null || version_compare($version, $newest, '>'))) {
                $newest = $version;
            }
        }

        if ($changesets === 0) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_CHANGELOG_EMPTY', [], $response->status);
        }

        if ($newest === null) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_CHANGELOG_NO_VERSION', [], $response->status);
        }

        return UrlCheckResult::ok(
            'COM_JED_URLCHECK_CHANGELOG_OK',
            ['changesets' => $changesets, 'version' => $newest],
            $response->status,
            $newest
        );
    }
}
