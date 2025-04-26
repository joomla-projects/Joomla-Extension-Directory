<?php

/**
 * @package       JED
 *
 * @subpackage    TICKETS
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Ticketform;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;

/**
 * View class for a Ticket Form
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
     * Does user have permission to save form
     *
     * @var   bool
     * @since 4.0.0
     */
    protected bool $canSave;

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
        // we need to get it from the menu item itself
        /*  $menu = $menus->getActive();

            if ($menu)
            {
                $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
            }
            else
            {
                $this->params->def('page_heading', Text::_('COM_JED_DEFAULT_PAGE_TITLE'));
            }

            $title = $this->params->get('page_title', '');

            if (empty($title))
            {
                $title = $app->get('sitename');
            }
            elseif ($app->get('sitename_pagetitles', 0) == 1)
            {
                $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
            }
            elseif ($app->get('sitename_pagetitles', 0) == 2)
            {
                $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
            }

            $this->getDocument()->setTitle($title);

            if ($this->params->get('menu-meta_description'))
            {
                $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
            }

            if ($this->params->get('menu-meta_keywords'))
            {
                $this->getDocument()->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
            }

            if ($this->params->get('robots'))
            {
                $this->getDocument()->setMetadata('robots', $this->params->get('robots'));
            }*/
    }

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        $this->state = $this->get('State');
        $this->item  = $this->get('Item');

        $this->params  = $app->getParams('com_jed');
        $this->canSave = JedHelper::canSave();
        $this->form    = $this->get('Form');
        $input         = $app->input;
        $linked_id     = $input->get('lid', -1, 'int');
        $linked_item   = $input->get('litem', -1, 'int');
        $vr            = $input->get('vr', -1, 'int');

        $this->item->ticket_title = "Submit Ticket";
        if ($linked_id <> -1) {
            $this->item->linked_item_type = $linked_item;
            $this->item->linked_item_id   = $linked_id;
            $this->item->vr               = $vr;
            if ($linked_item == 2) {
                $ticket_type = "Extension";
            }

            if ($linked_item == 3) {
                $ticket_type = "Review";
            }
            $this->item->extension_title = JedHelper::getExtensionTitle($vr);
            $this->item->ticket_title    = "Reporting " . $ticket_type . ' - ' . $this->item->extension_title;
        }


        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors));
        }


        $this->prepareDocument();

        parent::display($tpl);
    }
}
