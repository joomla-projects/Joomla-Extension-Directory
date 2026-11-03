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

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;
use Throwable;

/**
 * The advisory list controller.
 *
 * The state buttons do not go through AdminController::publish() and the model's publish():
 * `state` is not Joomla's 0/1/2/-2, and every move has to pass {@see VelService}'s rules.
 *
 * @since 4.0.0
 */
class AdvisoriesController extends AdminController
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
    public function getModel($name = 'Advisory', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Move the selected advisories to a state: tasks `advisories.ready`, `advisories.draft`,
     * `advisories.publish`.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function setstate(): void
    {
        $this->checkToken();

        $task = (string) $this->getTask();
        $next = match ($task) {
            'ready'   => AdvisoryState::READY,
            'draft'   => AdvisoryState::DRAFT,
            'publish' => AdvisoryState::PUBLISHED,
            default   => null,
        };

        $ids = ArrayHelper::toInteger((array) $this->input->get('cid', [], 'array'));
        $ids = array_values(array_filter($ids));

        if ($next === null || $ids === []) {
            $this->setRedirect(Route::_('index.php?option=com_vel&view=advisories', false), Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');

            return;
        }

        $user   = $this->app->getIdentity();
        $action = $next->requiresPublishPermission() ? 'vel.publish' : 'core.edit.state';

        if (!$user->authorise($action, 'com_vel')) {
            $this->setRedirect(Route::_('index.php?option=com_vel&view=advisories', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $service = new VelService(Factory::getContainer()->get(DatabaseInterface::class));
        $done    = 0;
        $errors  = [];

        foreach ($ids as $id) {
            try {
                $service->transition($id, $next, (int) $user->id);
                $done++;
            } catch (Throwable $e) {
                $errors[] = \sprintf('VEL-%04d: %s', $id, $e->getMessage());
            }
        }

        $this->setRedirect(
            Route::_('index.php?option=com_vel&view=advisories', false),
            Text::plural('COM_VEL_N_ADVISORIES_MOVED', $done, Text::_($next->label())) . ($errors ? ' ' . implode(' ', $errors) : ''),
            $errors ? 'warning' : 'message'
        );
    }

    /**
     * The three state tasks all route to setstate().
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function ready(): void
    {
        $this->setstate();
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function draft(): void
    {
        $this->setstate();
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function publish(): void
    {
        $this->setstate();
    }
}
