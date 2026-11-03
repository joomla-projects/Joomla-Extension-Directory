<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\ReportKind;

/**
 * The developer's fix notice (VEL2-PRD §6.4.2): the report form with the other kind and the
 * smaller form.
 *
 * @since 4.0.0
 */
class FixnoticeModel extends ReportModel
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected string $formName = 'fixnotice';

    /**
     * @var ReportKind
     *
     * @since 4.0.0
     */
    protected ReportKind $kind = ReportKind::FIX_NOTICE;
}
