<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Tickets;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Model\TicketsModel;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;
use Joomla\Registry\Registry;

/**
 * View class for a list of JED Tickets.
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
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws \Exception
     */
    protected function addToolbar(): void
    {
        $canDo       = JedHelper::getActions();

        ToolbarHelper::title(Text::_('COM_JED_TITLE_TICKETS'), "generic");

        $toolbar = $this->getDocument()->getToolbar();

        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('fas fa-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if (isset($this->items[0]->state)) {
                $childBar->publish('tickets.publish')->listCheck(true);
                $childBar->unpublish('tickets.unpublish')->listCheck(true);
                $childBar->archive('tickets.archive')->listCheck(true);
            } elseif (isset($this->items[0])) {
                // If this component does not use state then show a direct delete button as we can not trash
                $toolbar->delete('tickets.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }

            $childBar->standardButton('duplicate')
                ->text('JTOOLBAR_DUPLICATE')
                ->icon('fas fa-copy')
                ->task('tickets.duplicate')
                ->listCheck(true);

            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('tickets.checkin')->listCheck(true);
            }

            if (isset($this->items[0]->state)) {
                $childBar->trash('tickets.trash')->listCheck(true);
            }
        }


        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {
            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
                $toolbar->delete('tickets.delete')
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
        Sidebar::setAction('index.php?option=com_jed&view=tickets');
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
        /** @var TicketsModel $model */
        $model               = $this->getModel();
        $this->state         = $model->getState();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        // Check for errors.
        if (count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }

        $this->addToolbar();

        $this->sidebar = Sidebar::render();
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
            'a.`id`'                   => Text::_('JGRID_HEADING_ID'),
            'a.`ticket_origin`'        => Text::_('COM_JED_TICKETS_TICKET_ORIGIN_LABEL'),
            'a.`ticket_category_type`' => Text::_('COM_JED_GENERAL_TYPE_LABEL'),
            'a.`ticket_subject`'       => Text::_('COM_JED_GENERAL_SUBJECT_LABEL'),
            'a.`allocated_group`'      => Text::_('COM_JED_TICKETS_ALLOCATED_GROUP_LABEL'),
            'a.`allocated_to`'         => Text::_('COM_JED_TICKETS_ALLOCATED_TO_LABEL'),
            'a.`linked_item_type`'     => Text::_('COM_JED_TICKETS_LINKED_ITEM_TYPE_LABEL'),
            'a.`linked_item_id`'       => Text::_('COM_JED_TICKETS_LINKED_ITEM_ID_LABEL'),
            'a.`ticket_status`'        => Text::_('JSTATUS'),
            'a.`parent_id`'            => Text::_('COM_JED_TICKETS_PARENT_ID_LABEL'),
            'a.`state`'                => Text::_('JSTATUS'),
            'a.`ordering`'             => Text::_('JGRID_HEADING_ORDERING'),
            'a.`created_by`'           => Text::_('JGLOBAL_FIELD_CREATED_BY_LABEL'),
            'a.`created_on`'           => Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'),
            'a.`modified_by`'          => Text::_('JGLOBAL_FIELD_MODIFIED_BY_LABEL'),
            'a.`modified_on`'          => Text::_('COM_JED_GENERAL_MODIFIED_ON_LABEL'),
        ];
    }
}
