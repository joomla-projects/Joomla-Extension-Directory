<?php
/**
 * @package       JED
 *
 * @subpackage    VEL
 *
 * @copyright     (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use function defined;

/**
 * Velvulnerableitem controller class.
 *
 * @since  4.0.0
 */
class VelvulnerableitemController extends FormController
{
	/**
	 * A string showing the plural of the current object
	 *
	 * @var    string
	 *
	 * @since  4.0.0
	 */
	protected $view_list = 'Velvulnerableitems';
}
