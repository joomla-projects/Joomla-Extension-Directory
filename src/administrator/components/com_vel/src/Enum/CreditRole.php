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
 * CVE 5.x `credits[].type`, stored with underscores and exported with spaces.
 *
 * @since 4.0.0
 */
enum CreditRole: string
{
    case FINDER                = 'finder';
    case REPORTER              = 'reporter';
    case ANALYST               = 'analyst';
    case COORDINATOR           = 'coordinator';
    case REMEDIATION_DEVELOPER = 'remediation_developer';
    case REMEDIATION_REVIEWER  = 'remediation_reviewer';
    case REMEDIATION_VERIFIER  = 'remediation_verifier';
    case TOOL                  = 'tool';
    case SPONSOR               = 'sponsor';
    case OTHER                 = 'other';

    /**
     * @return string  The CVE record spelling ("remediation developer").
     *
     * @since 4.0.0
     */
    public function cveValue(): string
    {
        return str_replace('_', ' ', $this->value);
    }

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_CREDIT_ROLE_' . strtoupper($this->value);
    }
}
