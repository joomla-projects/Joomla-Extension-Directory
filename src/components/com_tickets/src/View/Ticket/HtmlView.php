<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Site\View\Ticket;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Tickets\Site\Model\TicketModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
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
     * @var Registry
     *
     * @since 4.0.0
     */
    protected Registry $params;

    /**
     * Prepares the document
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws \Exception
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
            $this->params->def('page_heading', Text::_('COM_TICKETS_DEFAULT_PAGE_TITLE'));
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
     * @throws \Exception
     */
    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = $this->getCurrentUser();

        /** @var TicketModel $model */
        $model = $this->getModel();
        $model->setUseExceptions(true);
        $this->state    = $model->getState();
        $this->item     = $model->getItem();
        $this->messages = $model->getMessages();
        $this->form     = $model->getForm();
        $this->params   = $app->getParams('com_jed');

        if (!$user->id) {
            $return                = base64_encode(Uri::getInstance());
            $login_url_with_return = Route::_('index.php?option=com_users&view=login&return=' . $return);
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'notice');
            $app->redirect($login_url_with_return, 403);
        }

        if (
            !$user->authorise('core.manage', 'com_tickets.ticket')
            && $this->item->created_by != $user->id
        ) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'));
        }

        $this->prepareDocument();

        parent::display($tpl);
    }
}
