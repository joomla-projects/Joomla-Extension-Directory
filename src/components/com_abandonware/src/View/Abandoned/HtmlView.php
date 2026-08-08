<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Site\View\Abandoned;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Registry\Registry;

/**
 * The public abandoned list.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $filterForm       = null;
    public array $activeFilters    = [];
    public array $items            = [];
    public ?Pagination $pagination = null;
    public ?Registry $state        = null;
    public ?Registry $params       = null;

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

        // From the application, not from the model state. A site ListModel does not put `params`
        // into its state - com_jed's own list view reads it the same way - and taking it from the
        // state yields null, which the template then calls ->get() on.
        $this->params = Factory::getApplication()->getParams('com_abandonware');

        parent::display($tpl);
    }
}
