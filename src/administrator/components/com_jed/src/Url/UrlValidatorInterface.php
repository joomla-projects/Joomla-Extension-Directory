<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Url;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One kind of check that can be run against a stored URL.
 *
 * The form XML names a validator by key, never by endpoint:
 *
 * ```xml
 * <field name="update_url" type="jedurl" validator="updateserver" />
 * ```
 *
 * An endpoint in form XML carries no meaning, can be rewritten from outside the application, and
 * makes every new check its own controller. A key resolves through {@see UrlValidatorRegistry}
 * to a class implementing this interface, so a new check is a class plus one registry line and
 * there is still exactly one AJAX endpoint to secure.
 *
 * @since 4.0.0
 */
interface UrlValidatorInterface
{
    /**
     * Check a URL that has already passed the format rules.
     *
     * Implementations must not throw for an unreachable target: "your server did not answer" is
     * a result, and the caller turns it into a notice.
     *
     * @param string               $url     The URL to check.
     * @param array<string, mixed> $context What is known about the listing - `extension_id`,
     *                                      `extension_version`, `extension_types`,
     *                                      `requires_registration`. A validator uses what it
     *                                      needs and ignores the rest.
     *
     * @return UrlCheckResult
     *
     * @since 4.0.0
     */
    public function validate(string $url, array $context = []): UrlCheckResult;
}
