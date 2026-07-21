<?php

/**
 * @package JED
 *
 * @subpackage mod_jed_opentickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Module\OpenTickets\Administrator\Dispatcher;

use Jed\Module\OpenTickets\Administrator\Helper\OpenTicketsHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_jed_opentickets.
 *
 * @since 4.1.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Runs the dispatcher.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function dispatch()
    {
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

        $loader = static function (array $displayData) {
            extract($displayData);

            /**
             * Extracted variables
             * -------------------
             * @var \stdClass $module
             * @var Registry  $params
             * @var array     $tickets
             */

            require ModuleHelper::getLayoutPath('mod_jed_opentickets', $params->get('layout', 'default'));
        };

        $loader($displayData);
    }

    /**
     * Returns the layout data.
     *
     * @return array
     *
     * @since 4.1.0
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();

        /** @var OpenTicketsHelper $helper */
        $helper           = $this->getHelperFactory()->getHelper('OpenTicketsHelper');
        $data['tickets']  = $helper->getTickets($data['params'], $data['app']);

        return $data;
    }
}
