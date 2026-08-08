<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Field;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Listing\LinkedExtensions;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use SimpleXMLElement;

/**
 * Names one other listing: the `P1-23` parent and variant fields.
 *
 * A plain text box rather than a picker, which is a deliberate choice and not a shortcut. The
 * obvious control is a select, and `JedextensionsField` is one - but it caps at 500 options
 * against a catalogue of ~14,900 listings, so for this purpose it is a list that mostly does not
 * contain the answer. Rendering all 14,900 into a searchable select instead puts about a
 * megabyte of markup on the page for one optional field. A modal picker would be the right
 * control and is a bigger piece of work than the relation it serves.
 *
 * What a developer actually has to hand is the other listing's page. So this takes the JED URL,
 * the alias out of it, or the bare id, and resolves all three server-side in
 * {@see LinkedExtensions::resolve()} - which is where the check has to live anyway (4.9), so the
 * control costs no validation that was not already required. It shows the alias rather than the
 * stored id, because an id in a text box tells nobody whether it is the right listing, and
 * repeats the resolved name underneath so the answer is visible before saving.
 *
 * @since 4.0.0
 */
class ListingrefField extends TextField
{
    /**
     * The form field type.
     *
     * @var   string
     * @since 4.0.0
     */
    protected $type = 'Listingref';

    /**
     * The listing the stored value names, or null.
     *
     * @var   object|null
     * @since 4.0.0
     */
    protected ?object $linked = null;

    /**
     * Turn the stored id into something a person can read before the input is rendered.
     *
     * @param SimpleXMLElement $element The `<field>` element.
     * @param mixed            $value   The stored value - an extension id, or empty.
     * @param string|null      $group   The form group.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function setup(SimpleXMLElement $element, $value, $group = null): bool
    {
        if (!parent::setup($element, $value, $group)) {
            return false;
        }

        $id = is_scalar($value) ? trim((string) $value) : '';

        if ($id === '' || !ctype_digit($id) || (int) $id === 0) {
            return true;
        }

        $db          = Factory::getContainer()->get(DatabaseInterface::class);
        $extensionId = (int) $id;

        $this->linked = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['id', 'name', 'alias', 'deleted']))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->loadObject();

        // A link whose target has gone keeps showing the id, so the value is still editable
        // rather than silently blanked - blanking it would delete the relation on the next save
        // without anybody deciding to.
        $this->value = $this->linked === null ? $id : $this->linked->alias;

        return true;
    }

    /**
     * The text input, followed by whichever listing the current value names.
     *
     * @return string
     *
     * @since 4.0.0
     */
    protected function getInput(): string
    {
        $input = parent::getInput();

        if ($this->linked === null) {
            return $input;
        }

        $note = Text::sprintf(
            (int) $this->linked->deleted === 1
                ? 'COM_JED_EXTENSION_LINK_RESOLVED_DELETED'
                : 'COM_JED_EXTENSION_LINK_RESOLVED',
            htmlspecialchars($this->linked->name, ENT_QUOTES, 'UTF-8'),
            (int) $this->linked->id
        );

        return $input . '<div class="form-text">' . $note . '</div>';
    }
}
