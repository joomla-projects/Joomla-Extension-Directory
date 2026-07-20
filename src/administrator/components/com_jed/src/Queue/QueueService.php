<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Queue;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Generic, DB-backed job queue for arbitrary com_jed background tasks.
 *
 * Jobs are produced by callers (e.g. the update-check task, or a manual admin
 * action) and consumed in batches by the `jed.queueworker` scheduled task, which
 * dispatches each job to a registered {@see JobHandlerInterface} by `type`.
 *
 * @since 4.1.0
 */
class QueueService
{
    /**
     * Number of failed processing attempts after which a stuck job is given up on.
     *
     * @since 4.1.0
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * @param DatabaseInterface $db The database connector object.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Enqueue a new job.
     *
     * @param string   $type        The job type, dispatched to a registered JobHandlerInterface.
     * @param int|null $extensionId The related extension id, if any.
     * @param int|null $historyId   The related extension history id, if any.
     * @param array    $payload     Arbitrary job-type-specific input data.
     * @param int      $createdBy   The user id that triggered this job, or 0 for system-enqueued jobs.
     *
     * @return int The new job id.
     *
     * @since 4.1.0
     */
    public function enqueue(string $type, ?int $extensionId = null, ?int $historyId = null, array $payload = [], int $createdBy = 0): int
    {
        $row = (object) [
            'type'         => $type,
            'extension_id' => $extensionId,
            'history_id'   => $historyId,
            'payload'      => $payload === [] ? null : json_encode($payload),
            'status'       => 'pending',
            'attempts'     => 0,
            'created'      => Factory::getDate('now')->toSql(),
            'created_by'   => $createdBy,
        ];

        $this->db->insertObject('#__jed_queue_jobs', $row);

        return (int) $this->db->insertid();
    }

    /**
     * Reclaim jobs stuck 'running' past the timeout, then atomically claim up to
     * $limit pending jobs so overlapping scheduler ticks cannot double-process a job.
     *
     * @param int $limit             Maximum number of jobs to claim.
     * @param int $jobTimeoutSeconds Seconds after which a 'running' job is considered stuck.
     *
     * @return object[] The claimed job rows.
     *
     * @since 4.1.0
     */
    public function claimBatch(int $limit, int $jobTimeoutSeconds): array
    {
        $this->reclaimStuckJobs($jobTimeoutSeconds);

        $db = $this->db;

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jed_queue_jobs'))
            ->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
            ->order($db->quoteName('created') . ' ASC')
            ->setLimit($limit);
        $ids = $db->setQuery($query)->loadColumn();

        $claimed = [];

        foreach ($ids as $id) {
            $id      = (int) $id;
            $now     = Factory::getDate('now')->toSql();
            $running = 'running';
            $pending = 'pending';

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__jed_queue_jobs'))
                ->set($db->quoteName('status') . ' = :running')
                ->set($db->quoteName('started_time') . ' = :now')
                ->where($db->quoteName('id') . ' = :id')
                ->where($db->quoteName('status') . ' = :pending')
                ->bind(':running', $running, ParameterType::STRING)
                ->bind(':now', $now, ParameterType::STRING)
                ->bind(':id', $id, ParameterType::INTEGER)
                ->bind(':pending', $pending, ParameterType::STRING);

            $db->setQuery($update)->execute();

            if ($db->getAffectedRows() === 1) {
                $claimed[] = $this->loadJob($id);
            }
        }

        return $claimed;
    }

    /**
     * Mark a job as completed and store its result metadata.
     *
     * @param int   $jobId      The job id.
     * @param array $resultMeta Arbitrary job-type-specific output data.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function markCompleted(int $jobId, array $resultMeta = []): void
    {
        $db     = $this->db;
        $now    = Factory::getDate('now')->toSql();
        $status = 'completed';
        $meta   = $resultMeta === [] ? null : json_encode($resultMeta);

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__jed_queue_jobs'))
            ->set($db->quoteName('status') . ' = :status')
            ->set($db->quoteName('result_meta') . ' = :meta')
            ->set($db->quoteName('finished_time') . ' = :finished')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':status', $status, ParameterType::STRING)
            ->bind(':meta', $meta, ParameterType::STRING)
            ->bind(':finished', $now, ParameterType::STRING)
            ->bind(':id', $jobId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }

    /**
     * Mark a job as failed.
     *
     * @param int    $jobId The job id.
     * @param string $error The error message.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function markFailed(int $jobId, string $error): void
    {
        $db     = $this->db;
        $now    = Factory::getDate('now')->toSql();
        $status = 'failed';

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__jed_queue_jobs'))
            ->set($db->quoteName('status') . ' = :status')
            ->set($db->quoteName('last_error') . ' = :error')
            ->set($db->quoteName('finished_time') . ' = :finished')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':status', $status, ParameterType::STRING)
            ->bind(':error', $error, ParameterType::STRING)
            ->bind(':finished', $now, ParameterType::STRING)
            ->bind(':id', $jobId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }

    /**
     * Reclaim jobs stuck in 'running' past the timeout: back to 'pending' for a
     * retry, or 'failed' once the attempt budget is exhausted.
     *
     * @param int $jobTimeoutSeconds Seconds after which a 'running' job is considered stuck.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function reclaimStuckJobs(int $jobTimeoutSeconds): void
    {
        $db      = $this->db;
        $running = 'running';
        $cutoff  = Factory::getDate('now')->sub(new \DateInterval('PT' . max(1, $jobTimeoutSeconds) . 'S'))->toSql();

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'attempts']))
            ->from($db->quoteName('#__jed_queue_jobs'))
            ->where($db->quoteName('status') . ' = :running')
            ->where($db->quoteName('started_time') . ' < :cutoff')
            ->bind(':running', $running, ParameterType::STRING)
            ->bind(':cutoff', $cutoff, ParameterType::STRING);
        $stuck = $db->setQuery($query)->loadObjectList();

        foreach ($stuck as $row) {
            $id         = (int) $row->id;
            $newAttempts = ((int) $row->attempts) + 1;
            $newStatus   = $newAttempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__jed_queue_jobs'))
                ->set($db->quoteName('status') . ' = :status')
                ->set($db->quoteName('attempts') . ' = :attempts')
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':status', $newStatus, ParameterType::STRING)
                ->bind(':attempts', $newAttempts, ParameterType::INTEGER)
                ->bind(':id', $id, ParameterType::INTEGER);

            $db->setQuery($update)->execute();
        }
    }

    /**
     * Load a single job row by id.
     *
     * @param int $id The job id.
     *
     * @return object
     *
     * @since 4.1.0
     */
    private function loadJob(int $id): object
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_queue_jobs'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject();
    }
}
