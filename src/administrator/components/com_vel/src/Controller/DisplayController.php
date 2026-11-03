<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Default controller for com_vel's admin side.
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
    protected $default_view = 'dashboard';

    /**
     * @param bool  $cachable  Whether the view may be cached.
     * @param array $urlparams URL parameters safe for caching.
     *
     * @return static
     *
     * @since 4.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        $view   = $this->input->get('view', $this->default_view);
        $layout = $this->input->get('layout', 'default');
        $id     = $this->input->getInt('id');

        // The edit layouts and the lists they fall back to. Nobody opens an edit layout for a
        // row they did not check out first.
        $lists = ['advisory' => 'advisories', 'report' => 'reports', 'feeditem' => 'feeditems'];

        if (isset($lists[$view]) && $layout === 'edit' && !$this->checkEditId('com_vel.edit.' . $view, $id)) {
            if (!\count($this->app->getMessageQueue())) {
                $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id), 'error');
            }

            $this->setRedirect(Route::_('index.php?option=com_vel&view=' . $lists[$view], false));

            return $this;
        }

        return parent::display($cachable, $urlparams);
    }
}
