<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\View\Feeditems;

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
 * The feed review queue.
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
            'feeds' => (bool) $user->authorise('vel.feeds', 'com_vel'),
            'admin' => (bool) $user->authorise('core.admin', 'com_vel'),
        ];

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    protected function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_VEL_FEEDITEMS_TITLE'), 'shield');

        if ($this->can['feeds']) {
            $toolbar->standardButton('ignore', 'COM_VEL_TOOLBAR_IGNORE', 'feeditems.ignore')->icon('icon-times')->listCheck(true);
            $toolbar->standardButton('run-cna', 'COM_VEL_TOOLBAR_RUN_CNA', 'feeditems.run')->icon('icon-sync')->listCheck(false)->onclick("document.getElementById('feed-source').value='cna'; Joomla.submitbutton('feeditems.run');");
            $toolbar->standardButton('run-nvd', 'COM_VEL_TOOLBAR_RUN_NVD', 'feeditems.run')->icon('icon-sync')->listCheck(false)->onclick("document.getElementById('feed-source').value='nvd'; Joomla.submitbutton('feeditems.run');");
        }

        if ($this->can['admin']) {
            $toolbar->preferences('com_vel');
        }
    }
}
