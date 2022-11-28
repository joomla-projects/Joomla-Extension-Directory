<?php
/**
 * @package       JED
 *
 *
 * @copyright     (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Field;

defined('JPATH_BASE') or die;

use Exception;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

/**
 * Modified By Field
 *
 * @since  4.0.0
 */
class ModifiedbyField extends FormField
{
	/**
	 * The form field type.
	 *
	 * @var        string
	 * @since  4.0.0
	 */
	protected $type = 'modifiedby';

	/**
	 * Method to get the field input markup.
	 *
	 * @return    string    The field input markup.
	 *
	 * @since  4.0.0
	 * @throws Exception
	 */
	protected function getInput(): string
	{
		// Initialize variables.
		$html   = array();
		$user   = JedHelper::getUser();

		$html[] = '<input type="hidden" name="' . $this->name . '" value="' . $user->id . '" />';
		if (!$this->hidden)
		{
			$html[] = "<div>" . $user->name . " (" . $user->username . ")</div>";
		}

		return implode($html);
	}
}
