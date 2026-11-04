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

use Jed\Component\Vel\Administrator\Service\JedBridge;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use SimpleXMLElement;

/**
 * Names a JED listing: the product's `extension_id`.
 *
 * The same control, for the same reason, as com_jed's ListingrefField: a select over ~14,900
 * listings is a list that mostly does not contain the answer, so this takes the listing's URL,
 * alias or id, resolves it server-side on save ({@see \Jed\Component\Vel\Administrator\Model\AdvisoryModel})
 * and shows the alias with the resolved name underneath.
 *
 * @since 4.0.0
 */
class JedlistingField extends TextField
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $type = 'Jedlisting';

    /**
     * @var object|null
     *
     * @since 4.0.0
     */
    protected ?object $linked = null;

    /**
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

        $bridge       = new JedBridge(Factory::getContainer()->get(DatabaseInterface::class));
        $this->linked = $bridge->listing((int) $id);

        // A link whose target has gone keeps showing the id, so the value stays editable rather
        // than being silently blanked - blanking it would drop the relation on the next save.
        $this->value = $this->linked === null ? $id : (string) $this->linked->alias;

        return true;
    }

    /**
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
            'COM_VEL_FIELD_EXTENSION_ID_RESOLVED',
            htmlspecialchars((string) $this->linked->name, ENT_QUOTES, 'UTF-8'),
            (int) $this->linked->id
        );

        return $input . '<div class="form-text">' . $note . '</div>';
    }
}
