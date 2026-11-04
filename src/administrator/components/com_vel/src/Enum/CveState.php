<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Enum;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Mirror of `cveMetadata.state`, plus "none" for an advisory with no CVE at all.
 *
 * @since 4.0.0
 */
enum CveState: string
{
    case NONE      = 'none';
    case RESERVED  = 'reserved';
    case PUBLISHED = 'published';
    case REJECTED  = 'rejected';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_CVE_STATE_' . strtoupper($this->value);
    }

    /**
     * @return string  The CVE record spelling, or '' for none.
     *
     * @since 4.0.0
     */
    public function cveValue(): string
    {
        return $this === self::NONE ? '' : strtoupper($this->value);
    }
}
