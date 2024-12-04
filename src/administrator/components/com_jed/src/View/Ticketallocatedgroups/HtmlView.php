<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Ticketallocatedgroups;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;

/**
 * View class for a list of Allocated Groups
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The form filter
     *
     * @var   Form|null
     * @since 4.0.0
     */
    public ?Form $filterForm;

    /**
     * The active filters
     *
     * @var   array
     * @var   array
     * @since 4.0.0
     */
    public array $activeFilters = [];
    /**
     * List of items
     *
     * @var   array
     * @since 4.0.0
     */
    protected array $items = [];
    /**
     * The pagination object
     *
     * @var   Pagination
     * @since 4.0.0
     */
    protected Pagination $pagination;

    /**
     * The model state
     *
     * @var   Registry
     * @since 4.0.0
     */
    protected Registry $state;

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
        $canDo = JedHelper::getActions();

        ToolbarHelper::title(Text::_('COM_JED_TITLE_ALLOCATEDGROUPS'), "generic");

        $toolbar = Toolbar::getInstance(); //Factory::getContainer()->get(ToolbarFactoryInterface::class)->createToolbar();



        if ($canDo->get('core.create')) {
            $toolbar->addNew('ticketallocatedgroup.add');
        }


        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('fas fa-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if (isset($this->items[0]->state)) {
                $childBar->publish('ticketallocatedgroups.publish')->listCheck(true);
                $childBar->unpublish('ticketallocatedgroups.unpublish')->listCheck(true);
                $childBar->archive('ticketallocatedgroups.archive')->listCheck(true);
            } elseif (isset($this->items[0])) {
                // If this component does not use state then show a direct delete button as we can not trash
                $toolbar->delete('ticketallocatedgroups.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }

            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('ticketallocatedgroups.checkin')->listCheck(true);
            }

            if (isset($this->items[0]->state)) {
                $childBar->trash('ticketallocatedgroups.trash')->listCheck(true);
            }
        }


        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {
            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
                $toolbar->delete('ticketallocatedgroups.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        }

        JedHelper::addConfigToolbar($toolbar);
        if ($canDo->get('core.admin')) {
            $toolbar->preferences('com_jed');
        }
    }

    /**
     * Execute and display a template script.
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

        parent::display($tpl);
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
            'a.`id`'    => Text::_('JGRID_HEADING_ID'),
            'a.`state`' => Text::_('JSTATUS'),
            'a.`name`'  => Text::_('COM_JED_GENERAL_NAME_LABEL'),
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
