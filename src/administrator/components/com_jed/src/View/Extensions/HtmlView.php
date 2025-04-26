<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Extensions;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\Registry\Registry;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;

/**
 * View class for a list of Extensions.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $filterForm;
    public array $activeFilters = [];
    public string $sidebar;
    protected array $items = [];
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
     * @since  4.0.0
     * @throws Exception
     */
    public function display($tpl = null): void
    {
        $this->state         = $this->get('State');
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors));
        }

        $this->addToolbar();

        $this->sidebar = Sidebar::render();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    protected function addToolbar(): void
    {
        $this->state = $this->get('State');
        $canDo       = JedHelper::getActions();

        ToolbarHelper::title(Text::_('COM_JED_EXTENSIONS'), "generic");

        $toolbar = Toolbar::getInstance(); //$toolbar = Toolbar::getInstance(); //Factory::getContainer()->get(ToolbarFactoryInterface::class)->createToolbar();



        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('fas fa-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if (isset($this->items[0]->state)) {
                $childBar->publish('extensions.publish')->listCheck(true);
                $childBar->unpublish('extensions.unpublish')->listCheck(true);
                $childBar->archive('extensions.archive')->listCheck(true);
            } elseif (isset($this->items[0])) {
                // If this component does not use state then show a direct delete button as we can not trash
                $toolbar->delete('extensions.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }

            $childBar->standardButton('duplicate')
                ->text('JTOOLBAR_DUPLICATE')
                ->icon('fas fa-copy')
                ->task('extensions.duplicate')
                ->listCheck(true);

            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('extensions.checkin')->listCheck(true);
            }

            if (isset($this->items[0]->state)) {
                $childBar->trash('extensions.trash')->listCheck(true);
            }
        }


        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {
            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
                $toolbar->delete('extensions.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        }
        JedHelper::addConfigToolbar($toolbar);
        if ($canDo->get('core.admin')) {
            $toolbar->preferences('com_jed');
        }
        // Set sidebar action
        Sidebar::setAction('index.php?option=com_jed&view=extensions');
    }

    /**
     * Method to order fields
     *
     * @return array
     *
     * @since 4.0.0
     */
    protected function getSortFields(): array
    {
        return [
            'a.`id`'                    => Text::_('JGRID_HEADING_ID'),
            'a.`title`'                 => Text::_('COM_JED_GENERAL_TITLE_LABEL'),
            'a.`alias`'                 => Text::_('COM_JED_GENERAL_ALIAS_LABEL'),
            'a.`published`'             => Text::_('JPUBLISHED'),
            'a.`joomla_versions`'       => Text::_('COM_JED_EXTENSION_JOOMLA_VERSIONS_LABEL'),
            'a.`popular`'               => Text::_('COM_JED_EXTENSION_POPULAR_LABEL'),
            'a.`requires_registration`' => Text::_('COM_JED_EXTENSION_REQUIRES_REGISTRATION_LABEL'),
            'a.`gpl_license_type`'      => Text::_('COM_JED_EXTENSION_GPL_LICENSE_TYPE_LABEL'),
            'a.`can_update`'            => Text::_('COM_JED_EXTENSION_CAN_UPDATE_LABEL'),
            'a.`includes`'              => Text::_('COM_JED_GENERAL_INCLUDES_LABEL'),
            'a.`approved`'              => Text::_('COM_JED_EXTENSION_APPROVED_LABEL'),
            'a.`approved_time`'         => Text::_('COM_JED_EXTENSION_APPROVED_TIME_LABEL'),
            'a.`primary_category_id`'   => Text::_('COM_JED_GENERAL_CATEGORY_ID_LABEL_LABEL'),
            'a.`state`'                 => Text::_('JSTATUS'),
        ];
    }

    /**
     * Check if state is set
     *
     * @param mixed $state State
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function getState(mixed $state): bool
    {
        return $this->state->{$state} ?? false;
    }
}
