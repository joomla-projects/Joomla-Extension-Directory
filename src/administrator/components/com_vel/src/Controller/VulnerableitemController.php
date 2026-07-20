<?php

/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Controller\FormController;

use function defined;

/**
 * Velvulnerableitem controller class.
 *
 * @since 4.0.0
 */
class VulnerableitemController extends FormController
{
    /**
     * A string showing the plural of the current object
     *
     * @var string
     *
     * @since 4.0.0
     */
    protected $view_list = 'Vulnerableitems';
}
