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
 * A handler for one #__jed_queue_jobs `type`, registered in {@see JobHandlerRegistry}.
 *
 * @since 4.1.0
 */
interface JobHandlerInterface
{
    /**
     * Process a single queued job.
     *
     * @param object $job The job row (as loaded from #__jed_queue_jobs).
     *
     * @return array Result metadata to store on the job (JSON-encoded into `result_meta`).
     *
     * @throws \Throwable On failure. The caller marks the job failed with the exception message.
     *
     * @since 4.1.0
     */
    public function handle(object $job): array;
}
