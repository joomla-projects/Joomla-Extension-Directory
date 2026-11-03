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

use Jed\Component\Vel\Administrator\Enum\FeedDecision;
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Jed\Component\Vel\Administrator\Feed\FeedRunner;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Throwable;

/**
 * The feed queue: run a feed now, ignore items in bulk.
 *
 * @since 4.0.0
 */
class FeeditemsController extends AdminController
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
    public function getModel($name = 'Feeditem', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Run a feed synchronously with a short budget (VEL2-PRD §6.6.3).
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function run(): void
    {
        $this->checkToken();

        $return = Route::_('index.php?option=com_vel&view=feedruns', false);

        if (!$this->app->getIdentity()->authorise('vel.feeds', 'com_vel')) {
            $this->setRedirect($return, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $source = FeedSource::tryFrom((string) $this->input->getCmd('source', ''));

        if ($source === null) {
            $this->setRedirect($return, Text::_('COM_VEL_ERROR_FEED_SOURCE'), 'error');

            return;
        }

        try {
            $db  = Factory::getContainer()->get(DatabaseInterface::class);
            $run = (new FeedRunner($db, new VelService($db)))->run($source, 60);
        } catch (Throwable $e) {
            $this->setRedirect($return, $e->getMessage(), 'error');

            return;
        }

        $this->setRedirect(
            $return,
            Text::sprintf('COM_VEL_MSG_FEED_RUN', Text::_($source->label()), Text::_('COM_VEL_FEED_RUN_STATUS_' . strtoupper((string) $run->status)), (int) $run->items_seen, (int) $run->items_new, (int) $run->items_updated)
            . ((string) $run->message !== '' ? ' ' . $run->message : ''),
            (string) $run->status === 'failed' ? 'error' : ((string) $run->status === 'partial' ? 'warning' : 'message')
        );
    }

    /**
     * Ignore the selected items.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function ignore(): void
    {
        $this->checkToken();

        $return = Route::_('index.php?option=com_vel&view=feeditems', false);

        if (!$this->app->getIdentity()->authorise('vel.feeds', 'com_vel')) {
            $this->setRedirect($return, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $ids = array_values(array_filter(ArrayHelper::toInteger((array) $this->input->get('cid', [], 'array'))));

        if ($ids !== []) {
            $db      = Factory::getContainer()->get(DatabaseInterface::class);
            $ignored = FeedDecision::IGNORED->value;
            $me      = (int) $this->app->getIdentity()->id;
            $now     = Factory::getDate()->toSql();

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__vel_feed_items'))
                    ->set($db->quoteName('decision') . ' = :decision')
                    ->set($db->quoteName('decided_by') . ' = :me')
                    ->set($db->quoteName('decided_time') . ' = :now')
                    ->whereIn($db->quoteName('id'), $ids)
                    ->where($db->quoteName('decision') . ' <> ' . $db->quote(FeedDecision::CREATED->value))
                    ->bind(':decision', $ignored)
                    ->bind(':me', $me, ParameterType::INTEGER)
                    ->bind(':now', $now)
            )->execute();
        }

        $this->setRedirect($return, Text::plural('COM_VEL_N_FEED_ITEMS_IGNORED', \count($ids)));
    }
}
