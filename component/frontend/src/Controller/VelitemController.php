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

use Joomla\CMS\MVC\Controller\BaseController;
use function defined;


/**
 * VEL Vulnerable Item Controller Class.
 *
 * @since 4.0.0
 */
class VelitemController extends BaseController
{
	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional
	 * @param   array   $config  Configuration array for model. Optionals
	 *
	 * @return object    The model
	 *
	 * @since 4.0.0
	 */
	public function getModel($name = 'Velitem', $prefix = 'Site', $config = array()): object
	{
		return parent::getModel($name, $prefix, array('ignore_request' => true));
	}
}
