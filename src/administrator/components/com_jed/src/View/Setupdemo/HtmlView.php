<?php

/**
 * @package jed Portal
 *
 * @copyright (C) 2023 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Setupdemo;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for a single Copyjed3data.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The item object
     *
     * @var   object
     * @since 4.0.0
     */
    protected mixed $item;
    /**
     * The model state
     *
     * @var   Registry
     * @since 4.0.0
     */
    protected Registry $state;
    /**
     * Component Parameters
     *
     * @var   Registry
     * @since 4.0.0
     */
    protected Registry $params;
    /**
     * Migration SQL
     *
     * @var   \SimpleXMLElement
     * @since 4.0.0
     */
    protected \SimpleXMLElement $migrate_xml;
    /**
     * Action Task
     *
     * @var   string
     * @since 4.0.0
     */
    protected string $task;
    /**
     * Add the page title and toolbar.
     *
     * @since  4.0.0
     * @throws \Exception
     */
    private function addToolbar(): void
    {
        ToolBarHelper::title('Setup Demo Menu');
        $user = $this->getCurrentUser();

        if (
            $user->authorise('core.admin', 'com_jed')
            || $user->authorise(
                'core.options',
                'com_jed'
            )
        ) {
            ToolbarHelper::preferences('com_jed');
        }
    }

    /**
     * Execute and display a template script.
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws \Exception
     */
    public function display($tpl = null): void
    {
        $model        = $this->getModel();
        $this->state  = $model->getState();
        $this->item   = $model->getItem();
        $this->params = ComponentHelper::getParams('com_jed');
        $app          = Factory::getApplication();
        $input        = $app->input->getInputForRequestMethod();
        $this->task   = $input->get('task', '');
        $this->addToolbar();
        parent::display($tpl);
    }
}
