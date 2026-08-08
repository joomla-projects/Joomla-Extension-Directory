<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\View\Cases;

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
 * The case queue.
 *
 * @since 4.1.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $filterForm    = null;
    public array $activeFilters = [];
    protected array $items      = [];
    protected Pagination $pagination;
    protected Registry $state;

    /**
     * @var bool
     *
     * @since 4.1.0
     */
    protected bool $canEdit = false;

    /**
     * @param string|null $tpl The template name.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.1.0
     */
    public function display($tpl = null): void
    {
        $model = $this->getModel();

        $this->items         = (array) $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->canEdit       = (bool) Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_abandonware');

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * @return void
     *
     * @throws Exception
     *
     * @since 4.1.0
     */
    protected function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_ABANDONWARE_CASES_TITLE'), 'unpublish');

        if ($this->canEdit) {
            $toolbar->standardButton('refresh', 'COM_ABANDONWARE_TOOLBAR_SCAN', 'cases.scan')
                ->icon('icon-sync')
                ->listCheck(false);
        }

        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_abandonware')) {
            $toolbar->preferences('com_abandonware');
        }
    }
}
