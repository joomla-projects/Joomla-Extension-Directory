<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Rule;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Url\UrlFormat;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use SimpleXMLElement;
use UnexpectedValueException;

/**
 * Layer 3 — the only place a URL rule is actually enforced.
 *
 * CLAUDE.md states it as an invariant and `P1-08` repeats it: client-side validation never
 * replaces server-side. Layer 1 is a courtesy to somebody typing. It can be switched off, worked
 * around, or skipped entirely by posting the form directly, and none of those is exotic - the
 * JED's own Cypress suite does the last one routinely.
 *
 * The rule it enforces is {@see UrlFormat::check()}, the same function the field hands to the
 * browser as `data-` attributes. Not "the same rule": literally the same code, which is what
 * stops the two from drifting.
 *
 * Reachability is deliberately not checked here. A save that waits on five outbound HTTP requests
 * is a save that times out, and a save refused because somebody else's WAF returned 403 is a
 * developer locked out of their own listing (13.4 point 5).
 *
 * @since 4.1.0
 */
class JedurlRule extends FormRule
{
    /**
     * Test a field's value.
     *
     * @param SimpleXMLElement $element The field's XML.
     * @param mixed            $value   The value to test.
     * @param string|null      $group   The field group.
     * @param Registry|null    $input   The full submitted data.
     * @param Form|null        $form    The form.
     *
     * @return bool
     *
     * @throws UnexpectedValueException  With the message the form shows, so the developer is told
     *                                   which rule they broke rather than "invalid field".
     *
     * @since 4.1.0
     */
    public function test(SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null): bool
    {
        $required = ((string) $element['required'] === 'true' || (string) $element['required'] === 'required');
        $errors   = UrlFormat::check(\is_string($value) ? $value : null, $required);

        if ($errors === []) {
            return true;
        }

        $label = Text::_((string) ($element['label'] ?? ''));

        throw new UnexpectedValueException(
            Text::sprintf(
                'COM_JED_URLCHECK_FORMAT_' . strtoupper($errors[0]) . '_FIELD',
                $label !== '' ? $label : (string) $element['name']
            )
        );
    }
}
