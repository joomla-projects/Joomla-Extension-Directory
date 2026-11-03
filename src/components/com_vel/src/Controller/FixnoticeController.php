<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The fix-notice form's controller: the report controller with the other model.
 *
 * @since 4.0.0
 */
class FixnoticeController extends ReportController
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $view_item = 'fixnotice';

    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected string $modelName = 'Fixnotice';
}
