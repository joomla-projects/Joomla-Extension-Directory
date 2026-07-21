<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Event;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Event\AbstractEvent;

/**
 * Dispatched by {@see \Jed\Component\Jed\Administrator\Service\ScoreCalculationService}
 * so the scoring algorithm can be swapped out by installing a plugin in the `jed`
 * group (e.g. `plugins/jed/score_avg`, or a replacement with a lower `ordering`).
 *
 * Arguments:
 * - `extensionId` (int) The extension being scored.
 * - `scores`      (object[]) Raw, unaveraged published `#__jed_reviews` rows for the extension.
 * - `result`      (array|null) Starts null. The first listener to set it "wins" -
 *                  later listeners should check it is still null before acting.
 *
 * @since 4.1.0
 */
final class CalculateExtensionScoreEvent extends AbstractEvent
{
    /**
     * @param int      $extensionId The extension being scored.
     * @param object[] $scores      Raw, unaveraged published `#__jed_reviews` rows for the extension.
     *
     * @since 4.1.0
     */
    public function __construct(int $extensionId, array $scores)
    {
        parent::__construct('onJedCalculateExtensionScore', [
            'extensionId' => $extensionId,
            'scores'      => $scores,
            'result'      => null,
        ]);
    }
}
