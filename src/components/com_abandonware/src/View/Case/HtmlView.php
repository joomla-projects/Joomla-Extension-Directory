<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Site\View\Case;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;

/**
 * One public entry.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public mixed $item       = null;
    public ?Registry $params = null;

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
        // The model throws a 404 for anything that is not published and marked, so an id guessed
        // past the list gets the same answer as an id that does not exist.
        $this->item   = $this->getModel()->getItem();
        $this->params = Factory::getApplication()->getParams();

        parent::display($tpl);
    }
}
