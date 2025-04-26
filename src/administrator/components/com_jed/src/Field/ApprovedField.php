<?php

/**
 * @package    JED
 *
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use RuntimeException;

/**
 * List of approved states.
 *
 * @package  JED
 * @since    4.0.0
 */
class ApprovedField extends ListField
{
    /**
     * Type of field
     *
     * @var    string
     * @since  4.0.0
     */
    protected $type = 'Approved';

    /**
     * Build a list of approved states.
     *
     * @return  array  List of approved states.
     *
     * @since   4.0.0
     * @throws  RuntimeException
     */
    protected function getOptions(): array
    {
        $params  = ComponentHelper::getParams('com_jed');
        $codes   = $params->get('awaiting_response_codes');
        $options = [];

        array_walk(
            $codes,
            static function ($code) use (&$options) {
                $options[] = HTMLHelper::_(
                    'select.option',
                    $code->code,
                    $code->code . ' - ' . $code->name
                );
            }
        );

        return array_merge(parent::getOptions(), $options);
    }
}
