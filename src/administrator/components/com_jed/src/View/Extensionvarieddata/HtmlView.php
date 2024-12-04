<?php

/**
 * @package    JED
 *
 * @copyright  (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Extensionvarieddata;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;

/**
 * View class for a list of Extensionvarieddata.
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $filterForm;
    public array $activeFilters = [];
    protected array $items;
    protected Pagination $pagination;
    protected CMSObject $state;

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   4.0.0
     * @throws Exception
     * @throws Exception
     */
    protected function addToolbar()
    {
        $canDo = JedHelper::getActions();

        ToolbarHelper::title(Text::_('COM_JED_EXTENSIONVARIEDDATA'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');


        if ($canDo->get('core.create')) {
            $toolbar->addNew('extensionvarieddatum.add');
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
                $childBar->publish('extensionvarieddata.publish')->listCheck(true);
                $childBar->unpublish('extensionvarieddata.unpublish')->listCheck(true);
                $childBar->archive('extensionvarieddata.archive')->listCheck(true);
            } elseif (isset($this->items[0])) {
                // If this component does not use state then show a direct delete button as we can not trash
                $toolbar->delete('extensionvarieddata.delete')
                ->text('JTOOLBAR_EMPTY_TRASH')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
            }

            $childBar->standardButton('duplicate')
                ->text('JTOOLBAR_DUPLICATE')
                ->icon('fas fa-copy')
                ->task('extensionvarieddata.duplicate')
                ->listCheck(true);

            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('extensionvarieddata.checkin')->listCheck(true);
            }

            if (isset($this->items[0]->state)) {
                $childBar->trash('extensionvarieddata.trash')->listCheck(true);
            }
        }



        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {
            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
                $toolbar->delete('extensionvarieddata.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        }

        if ($canDo->get('core.admin')) {
            $toolbar->preferences('com_jed');
        }

        // Set sidebar action
        Sidebar::setAction('index.php?option=com_jed&view=extensionvarieddata');
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields()
    {
        return [
            'a.`id`'                        => Text::_('JGRID_HEADING_ID'),
            'a.`extension_id`'              => Text::_('COM_JED_EXTENSION'),
            'a.`supply_option_id`'          => Text::_('COM_JED_EXTENSION_SUPPLY_OPTION_LABEL'),
            'a.`intro_text`'                => Text::_('COM_JED_EXTENSION_INTRO_TEXT_LABEL'),
            'a.`description`'               => Text::_('COM_JED_EXTENSION_DESCRIPTION_LABEL'),
            'a.`homepage_link`'             => Text::_('COM_JED_EXTENSION_HOMEPAGE_LINK_LABEL'),
            'a.`download_link`'             => Text::_('COM_JED_EXTENSION_DOWNLOAD_LINK_LABEL'),
            'a.`demo_link`'                 => Text::_('COM_JED_EXTENSION_DEMO_LINK_LABEL'),
            'a.`support_link`'              => Text::_('COM_JED_EXTENSION_SUPPORT_LINK_LABEL'),
            'a.`documentation_link`'        => Text::_('COM_JED_EXTENSION_DOCUMENTATION_LINK_LABEL'),
            'a.`license_link`'              => Text::_('COM_JED_EXTENSION_LICENSE_LINK_LABEL'),
            'a.`tags`'                      => Text::_('COM_JED_EXTENSION_TAGS_LABEL'),
            'a.`ordering`'                  => Text::_('JGRID_HEADING_ORDERING'),
            'a.`state`'                     => Text::_('JSTATUS'),
            'a.`created_by`'                => Text::_('COM_JED_GENERAL_CREATED_BY_LABEL '),
            'a.`update_url`'                => Text::_('COM_JED_EXTENSION_UPDATE_URL_LABEL'),
            'a.`update_url_ok`'             => Text::_('COM_JED_EXTENSION_UPDATE_URL_LABEL_OK'),
            'a.`download_integration_type`' => Text::_('COM_JED_EXTENSION_DOWNLOAD_INTEGRATION_TYPE_LABEL'),
            'a.`is_default_data`'           => Text::_('COM_JED_EXTENSION_IS_DEFAULT_DATA_LABEL'),
            'a.`translation_link`'          => Text::_('COM_JED_EXTENSION_TRANSLATION_LINK_LABEL'),
        ];
    }

    /**
     * Check if state is set
     *
     * @param   mixed  $state  State
     *
     * @return bool
     */
    public function getState(mixed $state): bool
    {
        return $this->state->{$state} ?? false;
    }
}
