<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Extension;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Model\ExtensionModel;
use Jed\Component\Jed\Administrator\Model\ExtensionvarieddatumModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * View class for a single Extension.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public mixed $extensionvarieddata;
    protected Registry $state;
    protected mixed $item;
    protected Form $form;
    protected mixed $forms;
    protected mixed $extension;
    protected mixed $extensionimages;
    protected mixed $extensionscores;
    protected mixed $extensionimage;
    protected mixed $extensionscore;
    protected mixed $extensionvarieddatum_form;
    protected mixed $extensionform;
    protected mixed $varied_forms;



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
        $this->state      = $this->get('State');
        $this->item       = $this->get('Item');
        $this->form       = $this->get('Form');

        //echo "<pre>";print_r($this->item);echo "</pre>";exit();
        /*    $this->extensionimages  = $this->get('Item', 'Extensionimages');

            $this->extensionscores            = $this->get('Item', 'Extensionscores');
            $this->extensionvarieddata        = $this->get('Form', 'Extensionvarieddata');
            $this->extensionimage             = $this->get('Item', 'Extensionimage');
            $this->extensionscore             = $this->get('Item', 'Extensionscore'); */
        //$this->extensionvarieddatum_form  = $this->get('FormTemplate', 'Extensionvarieddatum');

        /*
         * $extensionimagesModel = $this->getModel('Extensionimages');
                $extensionscoresModel = $this->getModel('Extensionscores');
                $extensionvarieddataModel = $this->getModel('Extensionvarieddata');
                $extensionimageModel = $this->getModel('Extensionimage');
                $extensionscoreModel = $this->getModel('Extensionscore');
                $extensionvarieddatumModel = $this->getModel('Extensionvarieddatum');
         */
        //$this->form  = $this->get('Form');
        //echo "<pre>";print_r($this->item);echo "</pre>";exit();

        //$this->varied_forms = $this->get('VariedDataForms');

        //echo "<pre>";print_r($this->varied_forms);echo "</pre>";exit();
        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors));
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user  = Factory::getApplication()->getIdentity();
        $isNew = ($this->item->id == 0);

        if (isset($this->item->checked_out)) {
            $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->id);
        } else {
            $checkedOut = false;
        }

        $canDo = JedHelper::getActions();

        ToolbarHelper::title(Text::_('COM_JED_TITLE_EXTENSION'), "generic");

        // If not checked out, can save the item.
        if (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create')))) {
            ToolbarHelper::apply('extension.apply', 'JTOOLBAR_APPLY');
            ToolbarHelper::save('extension.save', 'JTOOLBAR_SAVE');
        }

        if (!$checkedOut && ($canDo->get('core.create'))) {
            ToolbarHelper::custom('extension.save2new', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
        }

        // If an existing item, can save to a copy.
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::custom('extension.save2copy', 'save-copy.png', 'save-copy_f2.png', 'JTOOLBAR_SAVE_AS_COPY', false);
        }

        if (empty($this->item->id)) {
            ToolbarHelper::cancel('extension.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('extension.cancel', 'JTOOLBAR_CLOSE');
        }
    }
}
