<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.jed
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\Jed\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Queue\JobHandlerRegistry;
use Jed\Component\Jed\Administrator\Queue\QueueService;
use Jed\Component\Jed\Administrator\Service\UpdateCheckService;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * Task plugin offering the two com_jed background routines:
 * - `jed.updatecheck`: polls extensions' update-server URLs and applies detected
 *   version bumps directly to the live row, enqueueing an `extension.audit` job.
 * - `jed.queueworker`: drains `#__jed_queue_jobs` of any type each run - this is
 *   what lets the queue run "arbitrary tasks": a new job type only needs a new
 *   registered {@see \Jed\Component\Jed\Administrator\Queue\JobHandlerInterface},
 *   never a new task plugin routine.
 *
 * There is deliberately no scheduled score-recalculation routine here - that job
 * type is only ever enqueued manually, per extension (see
 * {@see \Jed\Component\Jed\Administrator\Queue\ScoreRecalcJobHandler}).
 *
 * @since 4.1.0
 */
final class Jed extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var array<string, array<string, string>>
     *
     * @since 4.1.0
     */
    protected const TASKS_MAP = [
        'jed.updatecheck' => [
            'langConstPrefix' => 'PLG_TASK_JED_UPDATECHECK',
            'form'            => 'updatecheck',
            'method'          => 'checkForUpdates',
        ],
        'jed.queueworker' => [
            'langConstPrefix' => 'PLG_TASK_JED_QUEUEWORKER',
            'form'            => 'queueworker',
            'method'          => 'drainQueue',
        ],
    ];

    /**
     * @var boolean
     *
     * @since 4.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * @param array               $config             An optional associative array of configuration settings.
     * @param UpdateCheckService  $updateCheckService  Checks extensions' update servers.
     * @param QueueService        $queueService        Drains and completes/fails jobs from #__jed_queue_jobs.
     * @param JobHandlerRegistry  $jobHandlerRegistry  Maps a job's `type` to the handler that processes it.
     *
     * @since 4.1.0
     */
    public function __construct(
        array $config,
        private readonly UpdateCheckService $updateCheckService,
        private readonly QueueService $queueService,
        private readonly JobHandlerRegistry $jobHandlerRegistry
    ) {
        parent::__construct($config);
    }

    /**
     * @inheritDoc
     *
     * @return array<string, string>
     *
     * @since 4.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * `jed.updatecheck` routine: poll update servers and apply any newer versions.
     *
     * @param ExecuteTaskEvent $event The `onExecuteTask` event.
     *
     * @return int The routine exit code.
     *
     * @since 4.1.0
     */
    protected function checkForUpdates(ExecuteTaskEvent $event): int
    {
        $params    = $event->getArgument('params');
        $batchSize = max(1, (int) ($params->batch_size ?? 50));
        $force     = !empty($params->force);

        try {
            $result = $this->updateCheckService->run($batchSize, $force);
        } catch (Throwable $e) {
            $this->logTask('jed.updatecheck failed: ' . $e->getMessage(), 'error');

            return TaskStatus::KNOCKOUT;
        }

        $this->logTask(\sprintf(
            'jed.updatecheck: checked %d, updated %d, errors %d.',
            $result['checked'],
            $result['updated'],
            $result['errors']
        ));

        if ($result['checked'] > 0 && $result['errors'] === $result['checked']) {
            return TaskStatus::KNOCKOUT;
        }

        return TaskStatus::OK;
    }

    /**
     * `jed.queueworker` routine: drain a batch of `#__jed_queue_jobs` of any type.
     *
     * @param ExecuteTaskEvent $event The `onExecuteTask` event.
     *
     * @return int The routine exit code.
     *
     * @since 4.1.0
     */
    protected function drainQueue(ExecuteTaskEvent $event): int
    {
        $params     = $event->getArgument('params');
        $batchSize  = max(1, (int) ($params->batch_size ?? 2));
        $jobTimeout = max(30, (int) ($params->job_timeout ?? 900));

        $jobs = $this->queueService->claimBatch($batchSize, $jobTimeout);

        if ($jobs === []) {
            return TaskStatus::OK;
        }

        $failures = 0;

        foreach ($jobs as $job) {
            try {
                $handler = $this->jobHandlerRegistry->get($job->type);
                $result  = $handler->handle($job);
                $this->queueService->markCompleted((int) $job->id, $result);
            } catch (Throwable $e) {
                $failures++;
                $this->queueService->markFailed((int) $job->id, $e->getMessage());
                $this->logTask(\sprintf('Queue job #%d (%s) failed: %s', $job->id, $job->type, $e->getMessage()), 'error');
            }
        }

        $this->logTask(\sprintf('jed.queueworker: processed %d, failed %d.', \count($jobs), $failures));

        return $failures > 0 && $failures === \count($jobs) ? TaskStatus::KNOCKOUT : TaskStatus::OK;
    }
}
