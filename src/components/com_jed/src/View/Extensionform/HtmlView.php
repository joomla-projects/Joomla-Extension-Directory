<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Extensionform;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Model\ExtensionvarieddatumModel;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/**
 * View class Extension Form.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected Registry $state;

    protected mixed $item;

    protected mixed $form;


    protected bool $canSave;

    protected array $supply_types;

    protected mixed $supply_forms;


    /**
     * Display the view
     *
     * @param string $tpl Template name
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();

        $this->state        = $this->get('State');
        $this->item         = $this->get('variedItem');
        $this->params       = $app->getParams('com_jed');
        $this->canSave      = JedHelper::canSave();
        $this->form         = $this->get('Form');
        $this->supply_types = $this->get('SupplyTypes');

        $extensionvarieddatum                   = new ExtensionvarieddatumModel();


        //echo "<pre>";print_r($this->item);echo "</pre>";exit();
        foreach ($this->supply_types as $st) {
            $this->supply_forms[$st->supply_id] = $extensionvarieddatum->getForm(
                $this->item->varied[$st->supply_id],
                false,
                'jf_varieddata_form_' . $st->supply_id
            );
            $this->supply_forms[$st->supply_id]->bind($this->item->varied[$st->supply_id]);
        }




        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors));
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
     * @since 4.0.0
     */
    protected function prepareDocument(): void
    {
        $app   = Factory::getApplication();
        $menus = $app->getMenu();

        // Because the application sets a default page title,
        // we need to get it from the menu item itself
        $menu = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_JED_DEFAULT_PAGE_TITLE'));
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

        if ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->getDocument()->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->getDocument()->setMetadata('robots', $this->params->get('robots'));
        }


        // Add Breadcrumbs
        $pathway        = $app->getPathway();
        $breadcrumbList = Text::_('COM_JED_EXTENSIONS');
        if (!in_array($breadcrumbList, $pathway->getPathwayNames())) {
            $pathway->addItem($breadcrumbList, "index.php?option=com_jed&view=extensions");
        }
        $breadcrumbTitle = isset($this->item->id) ? Text::_("JGLOBAL_EDIT") : Text::_("JGLOBAL_FIELD_ADD");

        if (!in_array($breadcrumbTitle, $pathway->getPathwayNames())) {
            $pathway->addItem($breadcrumbTitle);
        }
    }
}
