<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\User\UserFactoryInterface;
use Jed\Component\Jed\Site\Helper\JedHelper;

/**
 * Jed model.
 *
 * @since  4.0.0
 */
class ControlpanelModel extends ItemModel
{
    protected function populateState()
    {
        $app          = Factory::getApplication('com_jed');
        $params       = $app->getParams();
        $params_array = $params->toArray();
        $this->setState('params', $params);
    }

    public function getItem($id = null)
    {
    }
}
