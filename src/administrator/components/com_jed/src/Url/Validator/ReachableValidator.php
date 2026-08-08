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
 * "Does this address answer, and does it look like a page?"
 *
 * Used for the website, demo, support and documentation links - the ones where all the JED can
 * reasonably ask is whether a human clicking them would arrive somewhere.
 *
 * The judgements are deliberately soft. A 403 is reported as *probably fine, we were turned
 * away*, because the JED's checker arrives from a datacentre address with an unusual user agent,
 * which is exactly the profile bot protection is built to reject - and the developer's visitors
 * do not look like that. A 404 is the one status worth being firm about: the developer's own
 * server says the page is not there.
 *
 * @since 4.0.0
 */
class ReachableValidator implements UrlValidatorInterface
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
     * @param string               $url     The URL.
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

        if ($response->status === 0) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_FAILED_' . strtoupper($response->reason ?: 'FAILED'));
        }

        if ($response->status === 404 || $response->status === 410) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_NOT_FOUND', [], $response->status);
        }

        if ($response->status === 401 || $response->status === 403 || $response->status === 429) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_TURNED_AWAY', ['status' => $response->status], $response->status);
        }

        if ($response->status >= 500) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_SERVER_ERROR', ['status' => $response->status], $response->status);
        }

        if (!$response->isOk()) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_UNEXPECTED_STATUS', ['status' => $response->status], $response->status);
        }

        // A page, not a download. A support link that hands the visitor a zip file is a mistake
        // worth pointing out - gently, because plenty of sites answer with no type at all.
        if ($response->contentType !== '' && !str_contains($response->contentType, 'html') && !str_contains($response->contentType, 'text')) {
            return UrlCheckResult::notice(
                'COM_JED_URLCHECK_NOT_A_PAGE',
                ['type' => $response->contentType],
                $response->status
            );
        }

        if ($response->redirects !== []) {
            return UrlCheckResult::ok(
                'COM_JED_URLCHECK_OK_REDIRECTED',
                ['target' => (string) $response->finalUrl],
                $response->status,
                (string) $response->finalUrl
            );
        }

        return UrlCheckResult::ok('COM_JED_URLCHECK_OK', [], $response->status);
    }
}
