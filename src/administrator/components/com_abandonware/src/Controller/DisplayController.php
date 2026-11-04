<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Helper\ViewAccess;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Default controller for com_abandonware's admin side.
 *
 * @since 4.0.0
 */
class DisplayController extends BaseController
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $default_view = 'cases';

    /**
     * @param bool  $cachable
     * @param array $urlparams
     *
     * @return static
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        ViewAccess::check($this->app->getIdentity(), 'com_abandonware', $this->input->get('layout', 'default', 'string'));

        return parent::display($cachable, $urlparams);
    }
}
