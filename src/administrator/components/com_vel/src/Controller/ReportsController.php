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

use Jed\Component\Vel\Administrator\Service\ReportService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;

/**
 * The report queue controller.
 *
 * @since 4.0.0
 */
class ReportsController extends AdminController
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
    public function getModel($name = 'Report', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Delete with everything the reports carried (attachments, orphan messages) - the model's
     * delete would leave files behind.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function delete(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.delete', 'com_vel')) {
            $this->setRedirect(Route::_('index.php?option=com_vel&view=reports', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $ids     = array_values(array_filter(ArrayHelper::toInteger((array) $this->input->get('cid', [], 'array'))));
        $service = new ReportService(Factory::getContainer()->get(DatabaseInterface::class));

        foreach ($ids as $id) {
            $service->delete($id);
        }

        $this->setRedirect(Route::_('index.php?option=com_vel&view=reports', false), Text::plural('COM_VEL_N_REPORTS_DELETED', \count($ids)));
    }
}
