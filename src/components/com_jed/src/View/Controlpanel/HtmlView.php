<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Controlpanel;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Model\ExtensionsModel;
use Jed\Component\Jed\Site\Model\TicketsModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;
use Joomla\Component\Users\Administrator\Helper\Mfa;
use Joomla\CMS\Pagination\Pagination;

/**
 * View class for a list of Jed.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public Form $filterForm;
    public array $activeFilters;
    protected array $ticket_items;
    protected array $extension_items;

    protected $form;
    protected User $profiledata;
    protected Form $profileform;
    protected Registry $profilestate;
    protected Registry $profileparams;
    protected string $profilemfaConfigurationUI;


    protected Registry $params;
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
        $user         = $this->getCurrentUser();
        $model        = $this->getModel();
        $model->setUseExceptions(true);
        try {
            $profileModel = Factory::getApplication()->bootComponent('com_users')
                ->getMVCFactory()->createModel('Profile', 'Site');
            $this->profiledata               = $profileModel->getData();
            $this->profileform               = $profileModel->getForm();
            $this->profilestate              = $profileModel->getState();
            $this->profileparams             = ComponentHelper::getParams('com_users');
            $this->profilemfaConfigurationUI = Mfa::getConfigurationInterface($user);

            $ticketsModel       = new TicketsModel();
            $this->ticket_items = $ticketsModel->getItems();

            $extensionModel        = new ExtensionsModel();
            $this->extension_items = $extensionModel->getMyItems();

            $this->pagination    = $ticketsModel->getPagination();
            $this->filterForm    = $ticketsModel->getFilterForm();
            $this->activeFilters = $ticketsModel->getActiveFilters();

            $this->state  = $model->getState();
            $this->params = Factory::getApplication()->getParams();
        } catch (\Exception $e) {
            throw new GenericDataException($e->getMessage(), 500, $e);
        }



        if ($this->_layout == 'edit') {
            $authorised = $user->authorise('core.create', 'com_jed');

            if ($authorised !== true) {
                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'));
            }
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
