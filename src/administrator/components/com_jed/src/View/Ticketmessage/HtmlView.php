<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Ticketmessage;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Model\TicketmessageModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for a list of Jed.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The model state
     *
     * @var Registry
     *
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
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws \Exception
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user  = $this->getCurrentUser();
        $isNew = ($this->item->id == 0);

        if (isset($this->item->checked_out)) {
            $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->id);
        } else {
            $checkedOut = false;
        }

        $canDo = JedHelper::getActions();

        ToolbarHelper::title(Text::_('COM_JED_TITLE_TICKETMESSAGE'), "generic");

        // If not checked out, can save the item.
        if (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create')))) {
            ToolbarHelper::apply('ticketmessage.apply');
            ToolbarHelper::save('ticketmessage.save');
        }

        if (!$checkedOut && ($canDo->get('core.create'))) {
            ToolbarHelper::custom('ticketmessage.save2new', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
        }

        // If an existing item, can save to a copy.
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::custom('ticketmessage.save2copy', 'save-copy.png', 'save-copy_f2.png', 'JTOOLBAR_SAVE_AS_COPY', false);
        }


        if (empty($this->item->id)) {
            ToolbarHelper::cancel('ticketmessage.cancel');
        } else {
            ToolbarHelper::cancel('ticketmessage.cancel', 'JTOOLBAR_CLOSE');
        }
    }

    /**
     * Display the view
     *
     * @param string $tpl Template name
     *
     * @return void
     *
     * @since  4.0.0
     * @throws \Exception
     */
    public function display($tpl = null): void
    {
        /** @var TicketmessageModel $model */
        $model       = $this->getModel();
        $this->state = $model->getState();
        $this->item  = $model->getItem();
        $this->form  = $model->getForm();

        // Check for errors.
        if (count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }

        $this->addToolbar();
        parent::display($tpl);
    }
}
