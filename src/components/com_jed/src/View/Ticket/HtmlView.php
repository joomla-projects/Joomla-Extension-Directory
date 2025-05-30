<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Ticket;

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
 * View class for a single ticket.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The model state
     *
     * @var   Registry
     * @since 4.0.0
     */
    protected Registry $state;

    /**
     * The item object
     *
     * @var   object
     * @since 4.0.0
     */
    protected mixed $item;

    /**
     * The Form object
     *
     * @var Form
     *
     * @since 4.0.0
     */
    protected mixed $form;

    /**
     * The components parameters
     *
     * @var object
     *
     * @since 4.0.0
     */

    /**
     * Prepares the document
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
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
    }

    /**
     * Display the view
     *
     * @param string $tpl Template name
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = Factory::getApplication()->getIdentity();

        $this->state    = $this->get('State');
        $this->item     = $this->get('Item');
        $this->messages = $this->get('Messages');
        $this->params   = $app->getParams('com_jed');

        if (!empty($this->item)) {
            $this->form = $this->get('Form');
        }

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors));
        }


        if ($this->_layout == 'edit') {
            $authorised = $user->authorise('core.create', 'com_jed');

            if ($authorised !== true) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'));
            }
        }
        if ($this->_layout == 'viewticket') {
            $authorised = $user->authorise('core.create', 'com_jed');

            if ($authorised !== true) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'));
            }
        }
        $this->prepareDocument();

        parent::display($tpl);
    }
}
