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
 * A source repository that exists and is publicly readable.
 *
 * "Publicly readable" is the whole point of the check. A private repository answers 404 to an
 * anonymous request, which is indistinguishable from one that was deleted - and either way the
 * link on the listing is useless to the reader it is there for. So both are reported the same
 * way, and the message says what a visitor would experience rather than guessing which it is.
 *
 * The URL is otherwise treated as an ordinary web address: no API calls, no tokens, no
 * host-specific knowledge beyond recognising the well-known forges for the wording.
 *
 * @since 4.0.0
 */
class GitValidator implements UrlValidatorInterface
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
     * @param string               $url     The repository URL.
     * @param array<string, mixed> $context Listing context, unused here.
     *
     * @return UrlCheckResult
     *
     * @since 4.0.0
     */
    public function validate(string $url, array $context = []): UrlCheckResult
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        $isForge = (bool) preg_match('/(^|\.)(github\.com|gitlab\.com|bitbucket\.org|codeberg\.org)$/', $host);

        // github.com on its own is not a repository, and neither is github.com/someone. Cheap to
        // notice, and it is a common paste.
        if ($isForge && substr_count($path, '/') < 1) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_GIT_NOT_A_REPOSITORY');
        }

        $response = $this->fetcher->fetch($url, false);

        if ($response->refused) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_REFUSED_' . strtoupper($response->reason));
        }

        if ($response->status === 0) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_FAILED_' . strtoupper($response->reason ?: 'FAILED'));
        }

        if (\in_array($response->status, [401, 403, 404, 410], true)) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_GIT_NOT_PUBLIC', [], $response->status);
        }

        if (!$response->isOk()) {
            return UrlCheckResult::notice('COM_JED_URLCHECK_UNEXPECTED_STATUS', ['status' => $response->status], $response->status);
        }

        return UrlCheckResult::ok('COM_JED_URLCHECK_GIT_OK', [], $response->status);
    }
}
