<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;

/**
 * Controlpanel model.
 *
 * @since 4.0.0
 */
class ControlpanelModel extends ItemModel
{
    /**
     * populateState
     *
     * @since  4.0
     * @throws \Exception
     */
    protected function populateState(): void
    {
        $app          = Factory::getApplication();
        $params       = $app->getParams('com_jed');
        $this->setState('params', $params);
    }


    /**
     * getItem
     *
     * Comment
     *
     * @param null $pk
     *
     * @return array
     * @since  4.0
     */
    public function getItem($pk = null): array
    {
        return [];
    }
}
