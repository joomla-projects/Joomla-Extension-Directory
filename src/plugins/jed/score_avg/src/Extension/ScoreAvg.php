<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Jed.score_avg
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Jed\ScoreAvg\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Event\CalculateExtensionScoreEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

/**
 * Default extension scoring algorithm: an unweighted mean of
 * `#__jed_extension_scores` rows.
 *
 * Deliberately a placeholder - a "secret" replacement algorithm can be installed
 * as another `jed`-group plugin with a lower `ordering` (System > Plugins, filtered
 * to the `jed` folder) that unconditionally sets the event's `result` argument.
 * This plugin cooperates by checking `result` is still null before acting, so it
 * becomes a silent no-op once a higher-priority plugin has already produced one.
 *
 * @since 4.1.0
 */
final class ScoreAvg extends CMSPlugin implements SubscriberInterface
{
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
            'onJedCalculateExtensionScore' => 'calculateAverage',
        ];
    }

    /**
     * Compute the default score.
     *
     * @param CalculateExtensionScoreEvent $event The event.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function calculateAverage(CalculateExtensionScoreEvent $event): void
    {
        if ($event->getArgument('result') !== null) {
            return;
        }

        $scores = $event->getArgument('scores', []);

        if ($scores === []) {
            $event->setArgument('result', [
                'score_overall'         => 0,
                'score_functionality'   => 0,
                'score_ease_of_use'     => 0,
                'score_support'         => 0,
                'score_documentation'   => 0,
                'score_value_for_money' => 0,
                'score_count'           => 0,
            ]);

            return;
        }

        $dimensions = [
            'score_functionality'   => 'functionality_score',
            'score_ease_of_use'     => 'ease_of_use_score',
            'score_support'         => 'support_score',
            'score_documentation'   => 'documentation_score',
            'score_value_for_money' => 'value_for_money_score',
        ];

        $result = [];

        foreach ($dimensions as $resultKey => $column) {
            $sum = 0.0;

            foreach ($scores as $row) {
                $sum += (float) $row->$column;
            }

            $result[$resultKey] = $sum / \count($scores);
        }

        $reviewCount = 0;

        foreach ($scores as $row) {
            $reviewCount += (int) $row->number_of_reviews;
        }

        $result['score_overall'] = array_sum($result) / \count($dimensions);
        $result['score_count']   = $reviewCount;

        $event->setArgument('result', $result);
    }
}
