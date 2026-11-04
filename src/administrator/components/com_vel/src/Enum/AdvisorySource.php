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
 * What created an advisory row. Never changes afterwards.
 *
 * @since 4.0.0
 */
enum AdvisorySource: string
{
    case MANUAL = 'manual';
    case REPORT = 'report';
    case CNA    = 'cna';
    case NVD    = 'nvd';
    case LEGACY = 'legacy';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_SOURCE_' . strtoupper($this->value);
    }
}
