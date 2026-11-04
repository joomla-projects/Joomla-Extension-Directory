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
 * CVE 5.x `source.discovery`.
 *
 * @since 4.0.0
 */
enum Discovery: string
{
    case UNKNOWN  = 'UNKNOWN';
    case INTERNAL = 'INTERNAL';
    case EXTERNAL = 'EXTERNAL';
    case USER     = 'USER';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_DISCOVERY_' . $this->value;
    }
}
