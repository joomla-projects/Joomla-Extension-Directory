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

use Jed\Component\Jed\Administrator\Update\UpdateServerXmlParser;
use Jed\Component\Jed\Administrator\Url\SafeHttpFetcher;
use Jed\Component\Jed\Administrator\Url\UrlCheckResult;
use Jed\Component\Jed\Administrator\Url\UrlValidatorInterface;

/**
 * The update server feed, checked for what it *says* rather than for answering at all.
 *
 * `P1-08` calls this the single most valuable validator, and the reason is the failure mode it
 * catches: a misconfigured update server answers 200 with a perfectly well-formed document that
 * advertises the wrong extension, or a version older than the one on the listing. Nothing about
 * that is visible from a reachability check, and today it surfaces only when a user's Joomla
 * fails to offer an update that exists.
 *
 * The parser is {@see UpdateServerXmlParser}, the one the periodic update check already uses. A
 * second XML reader beside it would drift, and then the form would promise something the
 * background job later disagreed with.
 *
 * @since 4.0.0
 */
class UpdateServerValidator implements UrlValidatorInterface
{
    /**
     * @param SafeHttpFetcher       $fetcher The guarded fetcher.
     * @param UpdateServerXmlParser $parser  The feed parser the update check already uses.
     *
     * @since 4.0.0
     */
    public function __construct(
        protected readonly SafeHttpFetcher $fetcher,
        protected readonly UpdateServerXmlParser $parser
    ) {
    }

    /**
     * @param string               $url     The update server URL.
     * @param array<string, mixed> $context Wants `extension_version` to compare against.
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

        if ($body === '') {
            return UrlCheckResult::notice('COM_JED_URLCHECK_UPDATE_EMPTY', [], $response->status);
        }

        // The shapes that are not an `<updates>` feed, distinguished because the developer needs
        // a different next step for each. "No <update> entries" for any of them would send them
        // looking for a bug in a file that is not the file they meant.
        if (!str_contains($body, '<updates')) {
            if (str_contains($body, '<extensionset')) {
                // A collection file. Joomla accepts these as an update site, so this is not
                // necessarily wrong - but it lists other feeds rather than versions, so there is
                // nothing here for the JED to compare against the listing.
                return UrlCheckResult::notice('COM_JED_URLCHECK_UPDATE_IS_COLLECTION', [], $response->status);
            }

            return UrlCheckResult::notice(
                str_contains($body, '<extension ') || str_contains($body, '<extension>')
                    ? 'COM_JED_URLCHECK_UPDATE_IS_MANIFEST'
                    : 'COM_JED_URLCHECK_UPDATE_NOT_A_FEED',
                [],
                $response->status
            );
        }

        $result = $this->parser->parse($body);

        if ($result === null) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_UPDATE_NO_ENTRIES', [], $response->status);
        }

        $listed = trim((string) ($context['extension_version'] ?? ''));

        if ($listed !== '' && version_compare($result->version, $listed, '<')) {
            // The feed is behind the listing. Nearly always a stale update server rather than a
            // wrong URL, and worth saying so plainly: users are not being offered an update to
            // the version the JED is advertising.
            return UrlCheckResult::notice(
                'COM_JED_URLCHECK_UPDATE_BEHIND',
                ['feed' => $result->version, 'listing' => $listed],
                $response->status
            );
        }

        if ($result->downloadUrl === null) {
            return UrlCheckResult::notice(
                'COM_JED_URLCHECK_UPDATE_NO_DOWNLOAD',
                ['version' => $result->version],
                $response->status
            );
        }

        return UrlCheckResult::ok(
            'COM_JED_URLCHECK_UPDATE_OK',
            ['version' => $result->version],
            $response->status,
            $result->version
        );
    }
}
