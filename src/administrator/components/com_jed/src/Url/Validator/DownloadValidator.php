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
 * The download link — checked with a HEAD request, and judged very gently.
 *
 * This is the validator with the least authority to be firm, for two reasons the plan calls out.
 * Most downloads are not files at all: they are a product page, a cart, or a login, because the
 * developer sells the extension or asks for registration. And `requires_registration` exists as
 * a column precisely because the JED already knows that some of these *cannot* be fetched - so
 * when it is set, a page instead of a file is the expected answer, not a finding.
 *
 * A HEAD request, so a check on a 200 MB package does not pull 200 MB - and the size the header
 * claims is reported, not verified.
 *
 * @since 4.0.0
 */
class DownloadValidator implements UrlValidatorInterface
{
    /**
     * Archive types a Joomla package plausibly arrives as.
     *
     * @since 4.0.0
     */
    private const ARCHIVE_TYPES = [
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream',
        'application/x-gzip',
        'application/gzip',
        'application/x-tar',
        'application/x-bzip2',
        'multipart/x-zip',
    ];

    /**
     * @param SafeHttpFetcher $fetcher The guarded fetcher.
     *
     * @since 4.0.0
     */
    public function __construct(protected readonly SafeHttpFetcher $fetcher)
    {
    }

    /**
     * @param string               $url     The download URL.
     * @param array<string, mixed> $context Wants `requires_registration`.
     *
     * @return UrlCheckResult
     *
     * @since 4.0.0
     */
    public function validate(string $url, array $context = []): UrlCheckResult
    {
        $needsLogin = !empty($context['requires_registration']);
        $response   = $this->fetcher->fetch($url, false);

        if ($response->refused) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_REFUSED_' . strtoupper($response->reason));
        }

        if ($response->status === 0) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_FAILED_' . strtoupper($response->reason ?: 'FAILED'));
        }

        // A listing that says it requires registration and answers "log in first" is behaving
        // exactly as described. Reporting that as a problem would train the team to ignore this
        // validator on every paid extension in the catalogue.
        if (\in_array($response->status, [401, 402, 403], true)) {
            return $needsLogin
                ? UrlCheckResult::ok('COM_JED_URLCHECK_DOWNLOAD_BEHIND_LOGIN', [], $response->status)
                : UrlCheckResult::notice('COM_JED_URLCHECK_TURNED_AWAY', ['status' => $response->status], $response->status);
        }

        if ($response->status === 404 || $response->status === 410) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_NOT_FOUND', [], $response->status);
        }

        if (!$response->isOk()) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_UNEXPECTED_STATUS', ['status' => $response->status], $response->status);
        }

        $type = $response->contentType;

        if ($type !== '' && \in_array($type, self::ARCHIVE_TYPES, true)) {
            return UrlCheckResult::ok('COM_JED_URLCHECK_DOWNLOAD_OK', ['type' => $type], $response->status, $type);
        }

        if ($type !== '' && (str_contains($type, 'html') || str_contains($type, 'text'))) {
            // A page rather than a file. Very often correct - a product page with a Download
            // button on it - so this is the softest wording in the whole set.
            return $needsLogin
                ? UrlCheckResult::ok('COM_JED_URLCHECK_DOWNLOAD_BEHIND_LOGIN', [], $response->status)
                : UrlCheckResult::notice('COM_JED_URLCHECK_DOWNLOAD_IS_PAGE', [], $response->status);
        }

        return UrlCheckResult::ok('COM_JED_URLCHECK_OK', [], $response->status, $type ?: null);
    }
}
