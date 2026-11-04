<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\View\Cvepreview;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * The generated CVE record, with its validation result, before it goes anywhere.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public int $id          = 0;
    public string $json     = '';
    public array $problems  = [];

    /**
     * @param string|null $tpl The template name.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $app     = Factory::getApplication();
        $preview = (array) $app->getUserState('com_vel.cve.preview', []);

        $this->id       = (int) ($preview['id'] ?? 0);
        $this->json     = (string) ($preview['json'] ?? '');
        $this->problems = (array) ($preview['problems'] ?? []);

        $app->getInput()->set('hidemainmenu', true);
        ToolbarHelper::title(Text::sprintf('COM_VEL_CVEPREVIEW_TITLE', \sprintf('VEL-%04d', $this->id)), 'shield');
        ToolbarHelper::link('index.php?option=com_vel&task=advisory.edit&id=' . $this->id, 'COM_VEL_BUTTON_BACK_TO_ADVISORY', 'arrow-left');

        parent::display($tpl);
    }
}
