<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Supports an HTML select list of categories
 *
 * @since 4.0.0
 */
class TimeupdatedField extends FormField
{
    /**
     * The form field type.
     *
     * @var   string
     * @since 4.0.0
     */
    protected $type = 'timeupdated';

    /**
     * Method to get the field input markup.
     *
     * @return string    The field input markup.
     *
     * @since 4.0.0
     */
    protected function getInput(): string
    {
        // Initialize variables.
        $html = [];

        $time_created = $this->value;

        // If time is empty or invalid, use current time in UTC for saving
        if (empty($time_created) || $time_created === '0000-00-00 00:00:00' || !strtotime((string) $time_created)) {
            $now          = Factory::getDate(); // UTC
            $time_created = $now->toSql(true);
        }

        // Store raw UTC date in hidden input
        $html[] = '<input type="hidden" name="' . $this->name . '" value="' . htmlspecialchars((string) $time_created, ENT_QUOTES, 'UTF-8') . '" />';


        $hidden = (bool) $this->element['hidden'];

        if (!$hidden) {
            $pretty_date = HTMLHelper::_('date', $time_created, Text::_('DATE_FORMAT_LC2'), true);
            $html[]      = "<div>" . $pretty_date . "</div>";
        }

        return implode('', $html);
    }
}
