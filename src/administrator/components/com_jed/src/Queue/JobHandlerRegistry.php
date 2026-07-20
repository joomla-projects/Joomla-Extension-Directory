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

/**
 * Maps a #__jed_queue_jobs `type` string to the JobHandlerInterface that processes it.
 *
 * New job types are added here (and to the `task/jed` plugin's provider.php wiring)
 * without needing a new task plugin routine or schema change.
 *
 * @since 4.1.0
 */
class JobHandlerRegistry
{
    /**
     * @var JobHandlerInterface[]
     *
     * @since 4.1.0
     */
    private array $handlers = [];

    /**
     * Register a handler for a job type.
     *
     * @param string             $type    The job type.
     * @param JobHandlerInterface $handler The handler.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function register(string $type, JobHandlerInterface $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    /**
     * Get the handler registered for a job type.
     *
     * @param string $type The job type.
     *
     * @return JobHandlerInterface
     *
     * @throws \RuntimeException If no handler is registered for the given type.
     *
     * @since 4.1.0
     */
    public function get(string $type): JobHandlerInterface
    {
        if (!isset($this->handlers[$type])) {
            throw new \RuntimeException(\sprintf('No job handler registered for queue job type "%s".', $type));
        }

        return $this->handlers[$type];
    }
}
