<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\View\Report;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;

/**
 * The public report form.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $form          = null;
    public ?Registry $params    = null;
    public bool $guestsAllowed  = true;
    public string $contactEmail = '';
    public string $pgpKey       = '';

    /**
     * @param string|null $tpl The template name.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $app          = Factory::getApplication();
        $this->form   = $this->getModel()->getForm();
        $this->params = $app->getParams('com_vel');

        $this->guestsAllowed = (int) $this->params->get('allow_guest_reports', 1) === 1 || (int) $app->getIdentity()->id > 0;
        $this->contactEmail  = (string) $this->params->get('contact_email', '');
        $this->pgpKey        = (string) $this->params->get('pgp_public_key', '');

        $this->getDocument()->setTitle(Text::_('COM_VEL_REPORT_HEADING'));
        $this->getDocument()->setMetaData('robots', 'noindex, follow');

        parent::display($tpl);
    }
}
