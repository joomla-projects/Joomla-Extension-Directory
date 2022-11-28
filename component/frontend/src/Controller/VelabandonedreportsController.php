<?php
/**
 * @package       JED
 *
 * @subpackage    VEL
 *
 * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use function defined;


/**
 * Vel Abandoned Reports controller class.
 *
 * @since 4.0.0
 */
class VelabandonedreportsController extends FormController
{
	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional
	 * @param   array   $config  Configuration array for model. Optional
	 *
	 * @return object    The model
	 *
	 * @since 4.0.0
	 */
	public function getModel($name = 'Velabandonedreports', $prefix = 'Site', $config = array()): object
	{
		return parent::getModel($name, $prefix, array('ignore_request' => true));
	}
}
