<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Event\CalculateExtensionScoreEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Recalculates one extension's score columns from its published
 * `#__jed_reviews` rows.
 *
 * Triggered manually by an admin action, and automatically whenever a
 * review's published state changes (see {@see \Jed\Component\Jed\Administrator\Model\ReviewModel::publish()}
 * and {@see \Jed\Component\Jed\Administrator\Table\ReviewTable::store()}) -
 * never a scheduled scan of the whole dataset (see
 * {@see \Jed\Component\Jed\Administrator\Queue\ScoreRecalcJobHandler}). The actual
 * averaging algorithm lives in a `jed`-group plugin
 * ({@see \Jed\Component\Jed\Administrator\Event\CalculateExtensionScoreEvent}), so
 * it can be swapped out without touching this service.
 *
 * @since 4.1.0
 */
class ScoreCalculationService
{
    /**
     * @param DatabaseInterface $db The database connector object.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Recalculate and persist the score columns for one extension.
     *
     * @param int $extensionId The extension id.
     *
     * @return array{
     *     score_overall: string, score_functionality: string, score_ease_of_use: string,
     *     score_support: string, score_documentation: string, score_value_for_money: string,
     *     score_count: int
     * }
     *
     * @throws RuntimeException If no `jed`-group plugin produced a score result.
     *
     * @since 4.1.0
     */
    public function recalculateFor(int $extensionId): array
    {
        $scores = $this->loadScores($extensionId);

        $event = new CalculateExtensionScoreEvent($extensionId, $scores);

        PluginHelper::importPlugin('jed');
        Factory::getApplication()->getDispatcher()->dispatch($event->getName(), $event);

        $result = $event->getArgument('result');

        if (!\is_array($result)) {
            throw new RuntimeException(\sprintf(
                'No jed-group plugin produced a score result for extension #%d.',
                $extensionId
            ));
        }

        $fields = $this->normaliseResult($result);
        $obj = (object) $fields;
        $obj->id = $extensionId;

        $this->db->updateObject('#__jed_extensions', $obj, 'id');

        return $fields;
    }

    /**
     * @param int $extensionId The extension id.
     *
     * @return object[] The published (`published = 1`) `#__jed_reviews` rows for this
     *                   extension, with their score-dimension columns aliased to the
     *                   `*_score` names the `onJedCalculateExtensionScore` contract expects.
     *
     * @since 4.1.0
     */
    private function loadScores(int $extensionId): array
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select('*')
            ->select(
                [
                    $db->quoteName('functionality', 'functionality_score'),
                    $db->quoteName('ease_of_use', 'ease_of_use_score'),
                    $db->quoteName('support', 'support_score'),
                    $db->quoteName('documentation', 'documentation_score'),
                    $db->quoteName('value_for_money', 'value_for_money_score'),
                ]
            )
            ->from($db->quoteName('#__jed_reviews'))
            ->where($db->quoteName('extension_id') . ' = :id')
            ->where($db->quoteName('published') . ' = 1')
            ->bind(':id', $extensionId, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObjectList();
    }

    /**
     * Coerce a plugin's raw event result into the exact field/format the
     * `#__jed_extensions` and `#__jed_extensions_history` decimal(3,2) columns need.
     *
     * @param array $result The raw `result` argument set by a `jed`-group plugin.
     *
     * @return array{
     *     score_overall: string, score_functionality: string, score_ease_of_use: string,
     *     score_support: string, score_documentation: string, score_value_for_money: string,
     *     score_count: int
     * }
     *
     * @since 4.1.0
     */
    private function normaliseResult(array $result): array
    {
        $decimalColumns = [
            'score_overall',
            'score_functionality',
            'score_ease_of_use',
            'score_support',
            'score_documentation',
            'score_value_for_money',
        ];

        $fields = [];

        foreach ($decimalColumns as $column) {
            // Clamp to what the decimal(3,2) score_* columns can actually hold, in case an
            // upstream plugin's scale doesn't match (defensive - avoids a hard SQL error).
            $value = max(0.0, min(5.0, (float) ($result[$column] ?? 0)));
            $fields[$column] = number_format($value, 2, '.', '');
        }

        $fields['score_count'] = (int) ($result['score_count'] ?? 0);

        return $fields;
    }
}
