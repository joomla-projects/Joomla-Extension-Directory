<?php

/**
 * @package VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

/**
 * VEL site display controller.
 *
 * @since 4.0.0
 */
class DisplayController extends BaseController
{
    public function __construct($config = [], MVCFactoryInterface $factory = null, $app = null, $input = null)
    {
        parent::__construct($config, $factory, $app, $input);
    }

    /**
     * @param bool $cachable
     * @param bool $urlparams
     *
     * @return BaseController
     * @throws Exception
     */
    public function display($cachable = false, $urlparams = []): BaseController
    {
        $view = $this->input->getCmd('view', 'liveitems');
        $this->input->set('view', $view);

        parent::display($cachable, $urlparams);

        return $this;
    }
}
