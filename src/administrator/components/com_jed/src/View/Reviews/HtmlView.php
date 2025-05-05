<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Reviews;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Model\ReviewsModel;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;

/**
 * View class for a list of Reviews.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected array $items;

    protected Pagination $pagination;

    protected Registry $state;

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
        /** @var ReviewsModel $model */
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
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws \Exception
     */
    protected function addToolbar(): void
    {
        $canDo = JedHelper::getActions();

        ToolbarHelper::title(Text::_('COM_JED_TITLE_REVIEWS'), "generic");

        $toolbar = $this->getDocument()->getToolbar();

        // Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Reviews';

        if (file_exists($formPath)) {
            if ($canDo->get('core.create')) {
                $toolbar->addNew('review.add');
            }
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
                $childBar->publish('reviews.publish')->listCheck(true);
                $childBar->unpublish('reviews.unpublish')->listCheck(true);
                $childBar->archive('reviews.archive')->listCheck(true);
            } elseif (isset($this->items[0])) {
                // If this component does not use state then show a direct delete button as we can not trash
                $toolbar->delete('reviews.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }

            $childBar->standardButton('duplicate')
                ->text('JTOOLBAR_DUPLICATE')
                ->icon('fas fa-copy')
                ->task('reviews.duplicate')
                ->listCheck(true);

            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('reviews.checkin')->listCheck(true);
            }

            if (isset($this->items[0]->state)) {
                $childBar->trash('reviews.trash')->listCheck(true);
            }
        }



        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {
            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
                $toolbar->delete('reviews.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        }

        if ($canDo->get('core.admin')) {
            $toolbar->preferences('com_jed');
        }

        // Set sidebar action
        Sidebar::setAction('index.php?option=com_jed&view=reviews');
    }

    /**
     * Method to order fields
     *
     * @return array
     */
    protected function getSortFields()
    {
        return [
            'a.`id`'                      => Text::_('JGRID_HEADING_ID'),
            'a.`extension_id`'            => Text::_('COM_JED_EXTENSION'),
            'a.`supply_option_id`'        => Text::_('COM_JED_EXTENSION_SUPPLY_OPTION_ID_LABEL'),
            'a.`title`'                   => Text::_('COM_JED_GENERAL_TITLE_LABEL'),
            'a.`alias`'                   => Text::_('JALIAS'),
            'a.`body`'                    => Text::_('COM_JED_REVIEWS_BODY_LABEL'),
            'a.`functionality`'           => Text::_('COM_JED_REVIEWS_FUNCTIONALITY_LABEL'),
            'a.`ease_of_use`'             => Text::_('COM_JED_REVIEWS_EASE_OF_USE_LABEL'),
            'a.`support`'                 => Text::_('COM_JED_GENERAL_SUPPORT_LABEL'),
            'a.`support_comment`'         => Text::_('COM_JED_GENERAL_SUPPORT_LABEL_COMMENT'),
            'a.`documentation`'           => Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL'),
            'a.`documentation_comment`'   => Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL_COMMENT'),
            'a.`value_for_money`'         => Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL'),
            'a.`value_for_money_comment`' => Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL_COMMENT'),
            'a.`overall_score`'           => Text::_('COM_JED_REVIEWS_OVERALL_SCORE_LABEL'),
            'a.`used_for`'                => Text::_('COM_JED_REVIEWS_USED_FOR_LABEL'),
            'a.`flagged`'                 => Text::_('COM_JED_REVIEWS_FLAGGED_LABEL'),
            'a.`ip_address`'              => Text::_('COM_JED_GENERAL_IPADDRESS_LABEL'),
            'a.`published`'               => Text::_('JPUBLISHED'),
            'a.`created_on`'              => Text::_('COM_JED_GENERAL_CREATED_ON_LABEL'),
            'a.`created_by`'              => Text::_('JGLOBAL_FIELD_CREATED_BY_LABEL'),
            'a.`ordering`'                => Text::_('JGRID_HEADING_ORDERING'),
        ];
    }
}
