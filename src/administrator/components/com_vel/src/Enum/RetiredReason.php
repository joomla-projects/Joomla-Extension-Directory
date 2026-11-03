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
 * Why a published advisory was withdrawn.
 *
 * @since 4.0.0
 */
enum RetiredReason: string
{
    case DUPLICATE           = 'duplicate';
    case NOT_A_VULNERABILITY = 'not_a_vulnerability';
    case CVE_REJECTED        = 'cve_rejected';
    case OTHER               = 'other';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_RETIRED_REASON_' . strtoupper($this->value);
    }
}
