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
 * The two forms that land in #__vel_reports.
 *
 * @since 4.0.0
 */
enum ReportKind: string
{
    case VULNERABILITY = 'vulnerability';
    case FIX_NOTICE    = 'fix_notice';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_REPORT_KIND_' . strtoupper($this->value);
    }
}
