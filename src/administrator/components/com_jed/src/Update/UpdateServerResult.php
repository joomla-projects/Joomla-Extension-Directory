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

/**
 * The highest version advertised by an extension's Joomla update-site XML feed.
 *
 * @since 4.1.0
 */
final class UpdateServerResult
{
    /**
     * @param string      $version     The advertised version string.
     * @param string|null $downloadUrl The download URL for that version, if present.
     *
     * @since 4.1.0
     */
    public function __construct(
        public readonly string $version,
        public readonly ?string $downloadUrl = null
    ) {
    }
}
