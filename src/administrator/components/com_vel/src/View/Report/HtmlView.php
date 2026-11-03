<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\View\Report;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Enum\ReportKind;
use Jed\Component\Vel\Administrator\Enum\ReportState;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * One report, with the decisions that can be made about it.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public mixed $item = null;
    public ReportState $state = ReportState::NEW;
    public ReportKind $kind = ReportKind::VULNERABILITY;

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
        $this->item = $this->getModel()->getItem();

        if (!$this->item || empty($this->item->id)) {
            throw new Exception(Text::_('COM_VEL_ERROR_REPORT_NOT_FOUND'), 404);
        }

        $user        = Factory::getApplication()->getIdentity();
        $this->state = ReportState::tryFrom((string) $this->item->state) ?? ReportState::NEW;
        $this->kind  = ReportKind::tryFrom((string) $this->item->kind) ?? ReportKind::VULNERABILITY;

        $this->can = [
            'create'      => (bool) $user->authorise('core.create', 'com_vel'),
            'edit'        => (bool) $user->authorise('core.edit', 'com_vel'),
            'state'       => (bool) $user->authorise('core.edit.state', 'com_vel'),
            'delete'      => (bool) $user->authorise('core.delete', 'com_vel'),
            'communicate' => (bool) $user->authorise('vel.communicate', 'com_vel'),
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
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        ToolbarHelper::title(Text::sprintf('COM_VEL_REPORT_TITLE', \sprintf('VELR-%05d', (int) $this->item->id)), 'shield');
        ToolbarHelper::cancel('report.cancel', 'JTOOLBAR_CLOSE');
    }
}
