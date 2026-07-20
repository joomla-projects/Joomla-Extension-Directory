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

use Jed\Component\Jed\Administrator\Audit\AuditPipeline;

/**
 * Handles `extension.audit` queue jobs: runs the Docker/phpstan/Claude audit
 * pipeline for one extension version and emails the developer.
 *
 * @since 4.1.0
 */
class AuditJobHandler implements JobHandlerInterface
{
    /**
     * @param AuditPipeline $pipeline The audit pipeline orchestrator.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly AuditPipeline $pipeline)
    {
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function handle(object $job): array
    {
        return $this->pipeline->run($job);
    }
}
