<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

/**
 * Supports an HTML select list of categories
 *
 * @since 4.0.0
 */
class ModifiedbyField extends FormField
{
    /**
     * The form field type.
     *
     * @var   string
     * @since 4.0.0
     */
    protected $type = 'modifiedby';

    /**
     * Method to get the field input markup.
     *
     * @return string  The field input markup.
     *
     * @throws \Exception
     * @since 4.0.0
     */
    protected function getInput(): string
    {
        // Initialize variables.
        $html   = [];
        $user   = Factory::getApplication()->getIdentity();
        $html[] = '<input type="hidden" name="' . $this->name . '" value="' . $user->id . '" />';

        if (!$this->hidden) {
            $html[] = "<div>" . $user->name . " (" . $user->username . ")</div>";
        }

        return implode($html);
    }
}
