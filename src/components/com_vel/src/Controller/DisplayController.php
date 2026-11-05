<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Default controller for com_vel's site side.
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
    protected $default_view = 'advisories';

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
        // The public list and detail are safe to cache on these parameters. The forms, the case
        // page and the API are not (the API does its own HTTP caching).
        $view = $this->input->get('view', $this->default_view);

        if (\in_array($view, ['advisories', 'advisory'], true) && $this->input->getMethod() === 'GET') {
            $cachable  = true;
            $urlparams = [
                'id'         => 'INT',
                'status'     => 'CMD',
                'severity'   => 'STRING',
                'search'     => 'STRING',
                'cvss_min'   => 'FLOAT',
                'cvss_max'   => 'FLOAT',
                'vendor'     => 'STRING',
                'type'       => 'CMD',
                'from'       => 'STRING',
                'to'         => 'STRING',
                'sort'       => 'CMD',
                'dir'        => 'CMD',
                'limit'      => 'UINT',
                'limitstart' => 'UINT',
                'start'      => 'UINT',
                'format'     => 'CMD',
                'Itemid'     => 'INT',
                'lang'       => 'CMD',
            ];
        }

        return parent::display($cachable, $urlparams);
    }
}
