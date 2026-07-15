<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Newextension;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;

/**
 * View class for the "create a new extension" wizard.
 *
 * Layout "default": the source picker (upload/git/manual) - no form data needed.
 * Layout "form": forms/extensionform.xml, pre-filled from whatever step 1 detected.
 *
 * @since 1.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected Registry $params;

    protected mixed $form = null;

    protected bool $canSave = false;

    /**
     * Display the view
     *
     * @param string $tpl Template name
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 1.0.0
     */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        if (!JedHelper::isLoggedIn()) {
            $app->enqueueMessage(Text::_('COM_JED_EXTENSION_NO_ACCESS_LABEL'), 'warning');
            $app->redirect(JedHelper::getLoginlink());

            return;
        }

        $this->params  = $app->getParams('com_jed');
        $this->canSave = JedHelper::canSave();

        if ($this->getLayout() === 'form') {
            $model = $this->getModel();
            $model->setUseExceptions(true);
            $this->form = $model->getForm();
        }

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 1.0.0
     */
    protected function prepareDocument(): void
    {
        $app   = Factory::getApplication();
        $menus = $app->getMenu();
        $menu  = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_JED_NEWEXTENSION_PAGE_TITLE'));
        }

        $title = $this->params->get('page_title', '');

        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->getDocument()->setTitle($title);

        $pathway        = $app->getPathway();
        $breadcrumbList = Text::_('COM_JED_EXTENSIONS');

        if (!in_array($breadcrumbList, $pathway->getPathwayNames())) {
            $pathway->addItem($breadcrumbList, 'index.php?option=com_jed&view=extensions');
        }

        $breadcrumbTitle = Text::_('COM_JED_NEWEXTENSION_PAGE_TITLE');

        if (!in_array($breadcrumbTitle, $pathway->getPathwayNames())) {
            $pathway->addItem($breadcrumbTitle);
        }
    }
}
