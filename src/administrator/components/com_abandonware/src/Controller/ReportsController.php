<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Controller\AdminController;

/**
 * The report list controller. Read-mostly: a report is evidence of what somebody said at a moment,
 * and editing one would destroy the thing it is kept for.
 *
 * @since 4.0.0
 */
class ReportsController extends AdminController
{
    /**
     * @param string $name   The model name.
     * @param string $prefix The class prefix.
     * @param array  $config Configuration array.
     *
     * @return \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since 4.0.0
     */
    public function getModel($name = 'Report', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
