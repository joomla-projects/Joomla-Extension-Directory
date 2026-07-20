<?php

/**
 * @package VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * VEL master display controller.
 *
 * @since 4.0.0
 */
class DisplayController extends BaseController
{
    protected $default_view = 'vulnerableitems';

    /**
     * @param bool  $cachable
     * @param array $urlparams
     *
     * @return DisplayController
     * @throws Exception
     */
    public function display($cachable = false, $urlparams = []): DisplayController
    {
        $document = $this->app->getDocument();
        $vName    = $this->input->get('view', $this->default_view);
        $vFormat  = $document->getType();
        $lName    = $this->input->get('layout', 'default', 'string');

        if ($view = $this->getView($vName, $vFormat)) {
            $model = $this->getModel($vName);
            $view->setModel($model, true);
            $view->setLayout($lName);
            $view->document = $document;
            $view->display();
        }

        return $this;
    }
}
