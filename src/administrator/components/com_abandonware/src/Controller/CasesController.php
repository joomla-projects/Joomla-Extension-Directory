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

use Jed\Component\Abandonware\Administrator\Service\SignalScanner;
use Jed\Component\Abandonware\Administrator\Service\CaseService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * The case list controller.
 *
 * @since 4.0.0
 */
class CasesController extends AdminController
{
    /**
     * @param string $name   The model name.
     * @param string $prefix The class prefix.
     * @param array  $config Configuration array.
     *
     * @return \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since 4.0.0
     */
    public function getModel($name = 'Case', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Run the signal scan now rather than waiting for the scheduled task.
     *
     * Useful on the day the component is switched on, when the team wants to see what the
     * catalogue actually produces before deciding on the thresholds - which is the recommended way
     * to choose the inactivity number, since 4.10 calls it a policy decision and no policy is
     * improved by being made blind.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function scan(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.edit', 'com_abandonware')) {
            $this->setRedirect(
                Route::_('index.php?option=com_abandonware&view=cases', false),
                Text::_('JERROR_ALERTNOAUTHOR'),
                'error'
            );

            return;
        }

        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $scanner = new SignalScanner($db, new CaseService($db));

        try {
            $result      = $scanner->run((int) $this->input->getInt('batch', 50));
            $transferred = $scanner->closeTransferredCases();
        } catch (Throwable $e) {
            $this->setRedirect(
                Route::_('index.php?option=com_abandonware&view=cases', false),
                $e->getMessage(),
                'error'
            );

            return;
        }

        $this->setRedirect(
            Route::_('index.php?option=com_abandonware&view=cases', false),
            Text::sprintf(
                'COM_ABANDONWARE_MSG_SCAN_DONE',
                $result['linkcheck'],
                $result['updatecheck'],
                $result['inactivity'],
                $result['expired'],
                $transferred
            )
        );
    }
}
