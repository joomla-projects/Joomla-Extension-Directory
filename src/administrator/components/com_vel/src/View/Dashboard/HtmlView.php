<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\View\Dashboard;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * The morning's questions, as counters with links (VEL2-PRD §6.3.1).
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, int>
     *
     * @since 4.0.0
     */
    public array $counters = [];

    /**
     * @var object[]
     *
     * @since 4.0.0
     */
    public array $feedRuns = [];

    /**
     * @var object[]
     *
     * @since 4.0.0
     */
    public array $overdue = [];

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

        $this->counters = $model->getCounters();
        $this->feedRuns = $model->getLastFeedRuns();
        $this->overdue  = $model->getOverdue();

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

        ToolbarHelper::title(Text::_('COM_VEL_DASHBOARD_TITLE'), 'shield');

        if (Factory::getApplication()->getIdentity()->authorise('core.create', 'com_vel')) {
            $toolbar->addNew('advisory.add', 'COM_VEL_TOOLBAR_NEW_ADVISORY');
        }

        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_vel')) {
            $toolbar->preferences('com_vel');
        }
    }
}
