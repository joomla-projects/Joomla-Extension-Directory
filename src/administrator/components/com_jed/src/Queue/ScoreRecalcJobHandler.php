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

use Jed\Component\Jed\Administrator\Service\ScoreCalculationService;

/**
 * Handles `extension.score_recalc` queue jobs: recomputes one extension's score
 * columns. Always enqueued for a single extension, never as a dataset-wide scan.
 * Enqueued both by the manual "Recalculate Score" admin action and automatically
 * whenever a review's published state changes (see
 * {@see \Jed\Component\Jed\Administrator\Model\ReviewModel::publish()} and
 * {@see \Jed\Component\Jed\Administrator\Table\ReviewTable::store()}).
 *
 * @since 4.1.0
 */
class ScoreRecalcJobHandler implements JobHandlerInterface
{
    /**
     * @param ScoreCalculationService $scoreCalculationService The score calculation service.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly ScoreCalculationService $scoreCalculationService)
    {
    }

    /**
     * @inheritDoc
     *
     * @since 4.1.0
     */
    public function handle(object $job): array
    {
        $extensionId = (int) $job->extension_id;

        return $this->scoreCalculationService->recalculateFor($extensionId);
    }
}
