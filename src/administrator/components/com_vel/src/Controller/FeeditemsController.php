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
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Throwable;

/**
 * The feed queue: run a feed now (in steps, see {@see self::step()}), ignore items in bulk.
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
     * Run the CVE Program (CNA) feed now. Toolbar task: feeditems.runCna
     *
     * The feed is chosen by the task name, not by a form field, because Joomla's toolbar
     * buttons never render a custom onclick when a task is set.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function runCna(): void
    {
        $this->begin(FeedSource::CNA, FeedRunner::MODE_DELTA);
    }

    /**
     * Run the NVD feed now. Toolbar task: feeditems.runNvd
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function runNvd(): void
    {
        $this->begin(FeedSource::NVD, FeedRunner::MODE_DELTA);
    }

    /**
     * Run a feed named by the posted "source" value (kept for any caller that posts it).
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function run(): void
    {

        $source = FeedSource::tryFrom((string) $this->input->getCmd('source', ''));

        if ($source === null) {
            $this->checkToken();
            $this->setRedirect(Route::_('index.php?option=com_vel&view=feedruns', false), Text::_('COM_VEL_ERROR_FEED_SOURCE'), 'error');

            return;
        }

        $this->begin($source, FeedRunner::MODE_DELTA);
    }

    /**
     * Seed the CNA feed: build the back catalogue of our own CVE IDs once, before delta polling
     * takes over (feeds PRD §2.3). Manual only - the task plugin never seeds, because a seed
     * that silently re-runs every night would hammer the NVD for nothing. Repeat until the
     * message says complete.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function seed(): void
    {
        $this->begin(FeedSource::CNA, FeedRunner::MODE_SEED);
    }

    /**
     * AJAX: do one step of a run (a few seconds of work) and report where it is. Called in a
     * loop by media/com_vel/js/feedrun.js until the report says `done`.
     *
     * POST index.php?option=com_vel&task=feeditems.step&format=json, with `id` and the form token.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function step(): void
    {
        $this->respond(static fn (FeedRunner $runner, int $id): array => $runner->step($id, FeedRunner::STEP_SECONDS));
    }

    /**
     * AJAX: stop a run where it is, as partial; the next run of the feed resumes from there.
     *
     * POST index.php?option=com_vel&task=feeditems.stop&format=json, with `id` and the form token.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function stop(): void
    {
        $this->respond(static fn (FeedRunner $runner, int $id): array => $runner->stop($id, 'stopped'));
    }

    /**
     * Start a run and send the browser to the page that steps it and shows its progress. The
     * work itself happens in {@see self::step()}: a single request doing all of it outlasts the
     * web server's gateway timeout and shows nothing until it fails.
     *
     * @param FeedSource $source Which feed.
     * @param string     $mode   {@see FeedRunner::MODE_DELTA} or {@see FeedRunner::MODE_SEED}.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function begin(FeedSource $source, string $mode): void
    {
        $this->checkToken();

        $return = Route::_('index.php?option=com_vel&view=feedruns', false);

        if (!$this->app->getIdentity()->authorise('vel.feeds', 'com_vel')) {
            $this->setRedirect($return, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        try {
            $run = $this->runner()->start($source, $mode);
        } catch (Throwable $e) {
            $this->setRedirect($return, $e->getMessage(), 'error');

            return;
        }

        if ((string) $run->status !== 'running') {
            // The feed is disabled: nothing to step.
            $this->setRedirect($return, (string) $run->message, 'warning');

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_vel&view=feedruns&run=' . (int) $run->id, false));
    }

    /**
     * The JSON side of step/stop: token, permission, one call, one JsonResponse.
     *
     * @param callable(FeedRunner, int): array $action The work.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function respond(callable $action): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);

        try {
            if (!$this->checkToken('post', false)) {
                throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
            }

            if (!$this->app->getIdentity()->authorise('vel.feeds', 'com_vel')) {
                throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            // A step that is cut off half way wastes its work; let it finish and save even when
            // the browser stops waiting for it.
            if (\function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }

            $response = new JsonResponse($action($this->runner(), $this->input->post->getInt('id', 0)));
        } catch (Throwable $e) {
            $this->app->setHeader('Status', (string) (\in_array($e->getCode(), [403, 404], true) ? $e->getCode() : 500), true);
            $response = new JsonResponse($e);
        }

        $this->app->sendHeaders();

        echo $response;

        $this->app->close();
    }

    /**
     * @return FeedRunner  A runner whose requests time out well inside a gateway timeout.
     *
     * @since 4.0.0
     */
    private function runner(): FeedRunner
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return new FeedRunner($db, new VelService($db), FeedRunner::STEP_HTTP_TIMEOUT);
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
