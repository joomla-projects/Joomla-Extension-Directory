<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Extensionimage;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Model\ExtensionimageModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for a single Extensionimage.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected Registry $state;

    protected mixed $item;

    protected mixed $form;

    /**
     * Display the view
     *
     * @param string $tpl Template name
     *
     * @return void
     *
     * @throws \Exception
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        /** @var ExtensionimageModel $model */
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

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
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

        ToolbarHelper::title(Text::_('COM_JED_EXTENSIONIMAGE'), "generic");

        // If not checked out, can save the item.
        if (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create')))) {
            ToolbarHelper::apply('extensionimage.apply', 'JTOOLBAR_APPLY');
            ToolbarHelper::save('extensionimage.save', 'JTOOLBAR_SAVE');
        }

        if (!$checkedOut && ($canDo->get('core.create'))) {
            ToolbarHelper::custom('extensionimage.save2new', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
        }

        // If an existing item, can save to a copy.
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::custom('extensionimage.save2copy', 'save-copy.png', 'save-copy_f2.png', 'JTOOLBAR_SAVE_AS_COPY', false);
        }

        if (empty($this->item->id)) {
            ToolbarHelper::cancel('extensionimage.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('extensionimage.cancel', 'JTOOLBAR_CLOSE');
        }
    }
}
