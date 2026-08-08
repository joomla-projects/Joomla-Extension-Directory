<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Useraccess;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * Per-user privileges, bans and trusted status.
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
     * Whether the current user may act, rather than only look. The list itself is readable by
     * anyone who can reach the backend; deciding is `jed.user.ban`.
     *
     * @var bool
     *
     * @since 4.0.0
     */
    protected bool $canDecide = false;

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
        $this->canDecide     = (bool) JedHelper::getActions('com_jed')->get('jed.user.ban');

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
        ToolbarHelper::title(Text::_('COM_JED_USERACCESS_TITLE'), 'users');
    }
}
