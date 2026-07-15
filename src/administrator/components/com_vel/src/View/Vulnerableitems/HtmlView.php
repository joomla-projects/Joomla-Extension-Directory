<?php

/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\View\Vulnerableitems;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;

/**
 * View class for a list of JED VEL Vulnerable Items
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
    public ?Form $filterForm = null;

    public string $sidebar;
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
        $canDo       = JedHelper::getActions('com_vel', 'vulnerableitem');

        ToolbarHelper::title(Text::_('COM_VEL_TITLE_VULNERABLEITEMS'), 'generic');

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
                $childBar->publish('vulnerableitems.publish')->listCheck(true);
                $childBar->unpublish('vulnerableitems.unpublish')->listCheck(true);
                $childBar->archive('vulnerableitems.archive')->listCheck(true);
            } elseif (isset($this->items[0])) {
                // If this component does not use state then show a direct delete button as we can not trash
                $toolbar->delete('vulnerableitems.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }

            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('vulnerableitems.checkin')->listCheck(true);
            }

            if (isset($this->items[0]->state)) {
                $childBar->trash('vulnerableitems.trash')->listCheck(true);
            }
        }


        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {
            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
                $toolbar->delete('vulnerableitems.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        }
        JedHelper::addConfigToolbar($toolbar);

        if ($canDo->get('core.admin')) {
            $toolbar->preferences('com_vel');
        }

        // Set sidebar action - New in 3.0
        Sidebar::setAction('index.php?option=com_vel&view=vulnerableitems');
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
        $model               = $this->getModel();
        $model->setUseExceptions(true);
        try {
            $this->state         = $model->getState();
            $this->items         = $model->getItems();
            $this->pagination    = $model->getPagination();
            $this->filterForm    = $model->getFilterForm();
            $this->activeFilters = $model->getActiveFilters();
        } catch (\Exception $e) {
            throw new GenericDataException($e->getMessage(), 500, $e);
        }
        $this->addToolbar();

        $this->sidebar = Sidebar::render();
        parent::display($tpl);
    }
}
