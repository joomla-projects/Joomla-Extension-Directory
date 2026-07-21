<?php

/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Controller\FormController;

/**
 * VEL Reports Controller Class.
 *
 * @since 4.0.0
 */
class ReportsController extends FormController
{
    /**
     * Proxy for getModel.
     *
     * @param string $name   The model name. Optional.
     * @param string $prefix The class prefix. Optional
     * @param array  $config Configuration array for model. Optional
     *
     * @return object    The model
     *
     * @since 4.0.0
     */
    public function getModel($name = 'Reports', $prefix = 'Site', $config = []): object
    {
        return parent::getModel($name, $prefix, ['ignore_request' => true]);
    }
}
