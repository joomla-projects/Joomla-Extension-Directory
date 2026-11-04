<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Field;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\ReferenceTag;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * The CVE 5.x reference tag vocabulary as a multi-select.
 *
 * @since 4.0.0
 */
class ReferencetagsField extends ListField
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $type = 'Referencetags';

    /**
     * @return array
     *
     * @since 4.0.0
     */
    protected function getOptions(): array
    {
        $options = [];

        foreach (ReferenceTag::cases() as $tag) {
            if ($tag === ReferenceTag::JED) {
                // Added by the generator for a linked listing; not something to pick by hand.
                continue;
            }

            $options[] = HTMLHelper::_('select.option', $tag->value, Text::_($tag->label()));
        }

        return array_merge(parent::getOptions(), $options);
    }
}
