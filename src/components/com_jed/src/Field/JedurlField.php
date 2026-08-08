<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Url\UrlFormat;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\UrlField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * The one field type every URL an extension carries is entered through.
 *
 * ```xml
 * <field name="update_url" type="jedurl" validator="updateserver" />
 * ```
 *
 * It does three things a plain `url` field does not:
 *
 *  - **publishes the format rules** as `data-` attributes, so the browser enforces the same rules
 *    the server does. `P1-08` requires this rather than a JavaScript copy of them: if the browser
 *    accepts what the server rejects, the developer looks for the mistake on their side;
 *  - **names a validator by key**, which the AJAX endpoint resolves through the registry. The
 *    endpoint is never in the markup - an endpoint in form XML carries no meaning and can be
 *    rewritten from outside;
 *  - **loads the shared script through the Web Asset Manager**, once, however many URL fields a
 *    form has.
 *
 * A `type="url"` field keeps working without JavaScript, which is the point of the base class:
 * layer 3 runs on save regardless, so the rules are enforced either way (4.9).
 *
 * The site copy of the administrator field of the same name. Joomla resolves a field type inside
 * the namespace of the application it is rendering in, so one class cannot serve both - and the
 * two are identical on purpose: the developer entering a URL on the site and the JED team member
 * correcting it in the backend must get the same rules and the same feedback.
 *
 * @since 4.1.0
 */
class JedurlField extends UrlField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  4.1.0
     */
    protected $type = 'Jedurl';

    /**
     * Which check the AJAX endpoint should run for this field.
     *
     * @var    string
     * @since  4.1.0
     */
    protected string $validator = '';

    /**
     * Which application's endpoint this field talks to.
     *
     * @var    string
     * @since  4.1.0
     */
    protected string $endpointOption = 'com_jed';

    /**
     * Read the extra attributes off the XML.
     *
     * @param \SimpleXMLElement $element   The field's XML.
     * @param mixed             $value     The field value.
     * @param string|null       $group     The field group.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null): bool
    {
        if (!parent::setup($element, $value, $group)) {
            return false;
        }

        $this->validator = (string) ($element['validator'] ?? '');

        // Joomla's `url` layout documents a $validate variable and then never uses it - unlike
        // the `text` layout, it does not turn validate="jedurl" into class="validate-jedurl". So
        // the class is added here, because without it Joomla's own submit-time validation never
        // calls the handler and layer 1 would stop at the field's own input/blur events. It would
        // still be caught on save by layer 3, but the developer would find that out after
        // submitting rather than before.
        if ((string) ($element['validate'] ?? '') !== '' && !str_contains((string) $this->class, 'validate-')) {
            $this->class = trim($this->class . ' validate-' . (string) $element['validate']);
        }

        return true;
    }

    /**
     * The input, with the rules and the validator key attached.
     *
     * @return string
     *
     * @since 4.1.0
     */
    protected function getInput(): string
    {
        $document = Factory::getApplication()->getDocument();
        $wa       = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_jed');
        $wa->useScript('com_jed.jedurl')->useStyle('com_jed.jedurl');

        // The endpoint is built here, not written into the form XML: the field knows which
        // application it is rendering in, and the token is per session.
        $this->dataAttributes['data-jedurl-endpoint'] = Route::_(
            'index.php?option=com_jed&task=urlcheck.check&format=json',
            false
        );
        $this->dataAttributes['data-jedurl-token']     = Session::getFormToken();
        $this->dataAttributes['data-jedurl-validator'] = $this->validator;
        $this->dataAttributes['data-jedurl-required']  = $this->required ? '1' : '0';

        foreach (UrlFormat::toDataAttributes() as $name => $content) {
            $this->dataAttributes[$name] = $content;
        }

        // The status line the script writes into, and the screen reader reads from. It is in the
        // markup from the start rather than created when the first result arrives: a live region
        // inserted at the same moment as its content is not reliably announced.
        //
        // `aria-describedby` is left to the script, which appends this id to whatever the layout
        // already put there. Writing it here would mean emitting the attribute twice on fields
        // that have a description, and the second one is simply ignored.
        $statusId = $this->id . '-jedurl-status';

        $this->dataAttributes['data-jedurl-status'] = $statusId;

        return parent::getInput()
            . '<div class="jedurl-status" id="' . $statusId . '" role="status" aria-live="polite"></div>'
            . '<button type="button" class="btn btn-sm btn-link jedurl-recheck d-none"'
            . ' data-jedurl-for="' . $this->id . '">'
            . Text::_('COM_JED_URLCHECK_RECHECK') . '</button>';
    }
}
