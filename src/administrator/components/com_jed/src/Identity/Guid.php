<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The identifier the Joomla Identity Portal gives a person.
 *
 * The portal sends it as the username, and it is the only key the JED trusts to say who somebody
 * is: names and addresses change and are not verified, the GUID does not.
 *
 * @since 4.0.0
 */
final class Guid
{
    /**
     * Whether a string has the shape of a GUID.
     *
     * @param string $value The candidate.
     *
     * @return boolean
     *
     * @since 4.0.0
     */
    public static function isValid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($value));
    }

    /**
     * The one spelling the JED stores and compares: trimmed, lower case.
     *
     * @param string $value The GUID as received.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function normalise(string $value): string
    {
        return strtolower(trim($value));
    }
}
