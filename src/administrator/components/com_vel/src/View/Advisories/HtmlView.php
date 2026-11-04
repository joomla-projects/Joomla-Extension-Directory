<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\View\Advisories;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * The advisory list.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $filterForm    = null;
    public array $activeFilters = [];
    protected array $items      = [];
    protected Pagination $pagination;
    protected Registry $state;

    /**
     * @var array<string, bool>
     *
     * @since 4.0.0
     */
    public array $can = [];

    /**
     * @param string|null $tpl The template name.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $model = $this->getModel();

        $this->items         = (array) $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        $user      = Factory::getApplication()->getIdentity();
        $this->can = [
            'create'  => (bool) $user->authorise('core.create', 'com_vel'),
            'edit'    => (bool) $user->authorise('core.edit', 'com_vel'),
            'state'   => (bool) $user->authorise('core.edit.state', 'com_vel'),
            'publish' => (bool) $user->authorise('vel.publish', 'com_vel'),
            'delete'  => (bool) $user->authorise('core.delete', 'com_vel'),
            'admin'   => (bool) $user->authorise('core.admin', 'com_vel'),
        ];

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_VEL_ADVISORIES_TITLE'), 'shield');

        if ($this->can['create']) {
            $toolbar->addNew('advisory.add');
        }

        if ($this->can['state'] || $this->can['publish'] || $this->can['delete']) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $child = $dropdown->getChildToolbar();

            if ($this->can['state']) {
                $child->standardButton('ready', 'COM_VEL_TOOLBAR_READY', 'advisories.ready')->icon('icon-checkbox')->listCheck(true);
                $child->standardButton('draft', 'COM_VEL_TOOLBAR_DRAFT', 'advisories.draft')->icon('icon-pencil')->listCheck(true);
            }

            if ($this->can['publish']) {
                $child->standardButton('publish', 'COM_VEL_TOOLBAR_PUBLISH', 'advisories.publish')->icon('icon-publish')->listCheck(true);
            }

            if ($this->can['delete']) {
                $child->delete('advisories.delete')->text('JTOOLBAR_DELETE')->message('COM_VEL_CONFIRM_DELETE')->listCheck(true);
            }
        }

        if ($this->can['admin']) {
            $toolbar->preferences('com_vel');
        }
    }
}
