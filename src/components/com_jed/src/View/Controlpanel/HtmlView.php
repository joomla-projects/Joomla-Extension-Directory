<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Controlpanel;

// No direct access
defined('_JEXEC') or die;

use Exception;
use Jed\Component\Jed\Site\Model\TicketsModel;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\Registry\Registry;
use Joomla\Component\Users\Administrator\Helper\Mfa;
use Joomla\CMS\Pagination\Pagination;

/**
 * View class for a list of Jed.
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public Form $filterForm;
    public array $activeFilters;


    protected $item;
    protected $ticket_items;

    protected $form;

    protected $params;
    /**
     * The pagination object
     *
     * @var Pagination
     *
     * @since 4.0.0
     */
    protected Pagination $pagination;
    /**
     * The model state
     *
     * @var Registry
     *
     * @since 4.0.0
     */
    protected Registry $state;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 5.0.0
     */
    public function display($tpl = null)
    {
        $user         = $this->getCurrentUser();
        $profileModel = Factory::getApplication()->bootComponent('com_users')
                      ->getMVCFactory()->createModel('Profile', 'Site');
        $this->profiledata               = $profileModel->get('Data');
        $this->profileform               = $profileModel->getForm(new CMSObject(['id' => $user->id]));
        $this->profilestate              = $profileModel->get('State');
        $this->profileparams             = $profileModel->state->get('params');
        $this->profilemfaConfigurationUI = Mfa::getConfigurationInterface($user);

        $ticketsModel       = new TicketsModel();
        $this->ticket_items = $ticketsModel->getItems();

        $this->pagination    = $ticketsModel->getPagination();
        $this->filterForm    = $ticketsModel->getFilterForm();
        $this->activeFilters = $ticketsModel->getActiveFilters();

        $this->state  = $this->get('State');
        //$this->item   = $this->get('Item');
        $this->params = Factory::getApplication()->getParams('com_jed');

        if (!empty($this->item)) {
        }

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }



        if ($this->_layout == 'edit') {
            $authorised = $user->authorise('core.create', 'com_jed');

            if ($authorised !== true) {
                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'));
            }
        }

        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 5.0.0
     */
    protected function _prepareDocument()
    {
        $app   = Factory::getApplication();
        $menus = $app->getMenu();
        $title = null;

        // Because the application sets a default page title,
        // We need to get it from the menu item itself
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
        $pathway         = $app->getPathway();
        $breadcrumbTitle = Text::_('COM_JED_TITLE_CONTROLPANEL');

        if (!in_array($breadcrumbTitle, $pathway->getPathwayNames())) {
            $pathway->addItem($breadcrumbTitle);
        }
    }
}
