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
 * Recalculates one extension's score columns from `#__jed_extension_scores`.
 *
 * Always called for a single extension, triggered manually by an admin action
 * (never a scheduled scan of the whole dataset - see
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

        $this->updateRow('#__jed_extensions', $extensionId, $fields);
        $this->updateActiveHistoryRow($extensionId, $fields);

        return $fields;
    }

    /**
     * @param int $extensionId The extension id.
     *
     * @return object[] The published (`state = 1`) `#__jed_extension_scores` rows.
     *
     * @since 4.1.0
     */
    private function loadScores(int $extensionId): array
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_extension_scores'))
            ->where($db->quoteName('extension_id') . ' = :id')
            ->where($db->quoteName('state') . ' = 1')
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
            $fields[$column] = number_format((float) ($result[$column] ?? 0), 2, '.', '');
        }

        $fields['score_count'] = (int) ($result['score_count'] ?? 0);

        return $fields;
    }

    /**
     * @param int   $extensionId The extension id.
     * @param array $fields      The normalised score fields.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function updateActiveHistoryRow(int $extensionId, array $fields): void
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jed_extensions_history'))
            ->where($db->quoteName('extension_id') . ' = :id')
            ->where($db->quoteName('active') . ' = 1')
            ->bind(':id', $extensionId, ParameterType::INTEGER);
        $historyId = $db->setQuery($query)->loadResult();

        if ($historyId) {
            $this->updateRow('#__jed_extensions_history', (int) $historyId, $fields);
        }
    }

    /**
     * @param string $table  The table name (with #__ prefix placeholder).
     * @param int    $id     The row id.
     * @param array  $fields Column => value pairs to write.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function updateRow(string $table, int $id, array $fields): void
    {
        $db = $this->db;

        $bindValues = [];

        foreach ($fields as $column => $value) {
            $bindValues[$column] = (string) $value;
        }

        $query = $db->getQuery(true)->update($db->quoteName($table));

        foreach ($bindValues as $column => $value) {
            $query->set($db->quoteName($column) . ' = :' . $column)
                ->bind(':' . $column, $bindValues[$column], ParameterType::STRING);
        }

        $query->where($db->quoteName('id') . ' = :rowId')
            ->bind(':rowId', $id, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }
}
