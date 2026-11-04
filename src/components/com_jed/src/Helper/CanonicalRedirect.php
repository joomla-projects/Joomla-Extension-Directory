<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_jed
 *
 * @copyright   (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Decides whether a listing was requested at a path other than its own.
 *
 * A listing URL is read by its id, so a link written before the alias or the category changed
 * still shows the listing, but under a second address. Answering those with a permanent redirect
 * to the current address keeps old links and search results working without two copies of the
 * page being indexed.
 *
 * @since 4.0.0
 */
final class CanonicalRedirect
{
    /**
     * @param string $requestPath   Path the visitor asked for.
     * @param string $canonicalPath Path the listing is routed to now.
     * @param string $query         Query string of the request, without the leading question mark.
     *
     * @return string|null Where to send the visitor, or null when the request is already right.
     *
     * @since 4.0.0
     */
    public static function target(string $requestPath, string $canonicalPath, string $query = ''): ?string
    {
        if ($canonicalPath === '' || self::normalise($requestPath) === self::normalise($canonicalPath)) {
            return null;
        }

        return $canonicalPath . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Percent-encoding and a trailing slash are not different addresses.
     *
     * @since 4.0.0
     */
    private static function normalise(string $path): string
    {
        return rtrim(rawurldecode($path), '/');
    }
}
