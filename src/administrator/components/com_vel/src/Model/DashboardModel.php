<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\FeedDecision;
use Jed\Component\Vel\Administrator\Enum\ReportState;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * The counters behind the dashboard.
 *
 * @since 4.0.0
 */
class DashboardModel extends BaseDatabaseModel
{
    /**
     * @return array<string, int>
     *
     * @since 4.0.0
     */
    public function getCounters(): array
    {
        $db  = $this->getDatabase();
        $now = Factory::getDate();

        $count = function (string $table, array $where, array $binds = []) use ($db): int {
            try {
                $query = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName($table));

                foreach ($where as $clause) {
                    $query->where($clause);
                }

                // Bound by reference: each value needs its own variable, not the loop's.
                $params = [];

                foreach ($binds as $key => $pair) {
                    $params[$key] = $pair[0];
                    $query->bind($key, $params[$key], $pair[1]);
                }

                return (int) $db->setQuery($query)->loadResult();
            } catch (Throwable $e) {
                return 0;
            }
        };

        $soon   = Factory::getDate('now +7 days')->toSql();
        $today  = $now->toSql();
        $window = Factory::getDate('now -' . (int) (new VelService($db))->option('vendor_first_response_days', 14) . ' days')->toSql();

        $new       = ReportState::NEW->value;
        $verifying = ReportState::VERIFYING->value;
        $draft     = AdvisoryState::DRAFT->value;
        $ready     = AdvisoryState::READY->value;
        $published = AdvisoryState::PUBLISHED->value;
        $live      = AdvisoryStatus::LIVE->value;
        $pending   = FeedDecision::PENDING->value;

        return [
            'reports_new'       => $count('#__vel_reports', [$db->quoteName('state') . ' = :s'], [':s' => [$new, ParameterType::STRING]]),
            'reports_verifying' => $count('#__vel_reports', [$db->quoteName('state') . ' = :s'], [':s' => [$verifying, ParameterType::STRING]]),
            'drafts'            => $count('#__vel_advisories', [$db->quoteName('state') . ' = :s'], [':s' => [$draft, ParameterType::STRING]]),
            'ready'             => $count('#__vel_advisories', [$db->quoteName('state') . ' = :s'], [':s' => [$ready, ParameterType::STRING]]),
            'published_live'    => $count('#__vel_advisories', [$db->quoteName('state') . ' = :s', $db->quoteName('status') . ' = :st'], [':s' => [$published, ParameterType::STRING], ':st' => [$live, ParameterType::STRING]]),
            'published_total'   => $count('#__vel_advisories', [$db->quoteName('state') . ' = :s'], [':s' => [$published, ParameterType::STRING]]),
            'embargo_due'       => $count('#__vel_advisories', [$db->quoteName('state') . ' IN (:a, :b)', $db->quoteName('date_public') . ' IS NOT NULL', $db->quoteName('date_public') . ' <= :soon'], [':a' => [$draft, ParameterType::STRING], ':b' => [$ready, ParameterType::STRING], ':soon' => [$soon, ParameterType::STRING]]),
            'embargo_passed'    => $count('#__vel_advisories', [$db->quoteName('state') . ' IN (:a, :b)', $db->quoteName('date_public') . ' IS NOT NULL', $db->quoteName('date_public') . ' < :today'], [':a' => [$draft, ParameterType::STRING], ':b' => [$ready, ParameterType::STRING], ':today' => [$today, ParameterType::STRING]]),
            'vendor_overdue'    => $count('#__vel_advisories', [$db->quoteName('state') . ' IN (:a, :b)', $db->quoteName('status') . ' = :st', $db->quoteName('date_vendor_notified') . ' IS NOT NULL', $db->quoteName('date_vendor_notified') . ' < :window', $db->quoteName('date_fixed') . ' IS NULL'], [':a' => [$draft, ParameterType::STRING], ':b' => [$ready, ParameterType::STRING], ':st' => [$live, ParameterType::STRING], ':window' => [$window, ParameterType::STRING]]),
            'feed_pending'      => $count('#__vel_feed_items', [$db->quoteName('decision') . ' = :d'], [':d' => [$pending, ParameterType::STRING]]),
        ];
    }

    /**
     * @return object[]  The latest run per source.
     *
     * @since 4.0.0
     */
    public function getLastFeedRuns(): array
    {
        $db = $this->getDatabase();

        try {
            return $db->setQuery(
                $db->getQuery(true)
                    ->select('r.*')
                    ->from($db->quoteName('#__vel_feed_runs', 'r'))
                    ->where($db->quoteName('r.id') . ' IN (SELECT MAX(' . $db->quoteName('id') . ') FROM ' . $db->quoteName('#__vel_feed_runs') . ' GROUP BY ' . $db->quoteName('source') . ')')
                    ->order($db->quoteName('r.source') . ' ASC')
            )->loadObjectList() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Advisories that need a decision: embargo passed and still not published, or vendor
     * silent past the response window.
     *
     * @return object[]
     *
     * @since 4.0.0
     */
    public function getOverdue(): array
    {
        $db     = $this->getDatabase();
        $today  = Factory::getDate()->toSql();
        $window = Factory::getDate('now -' . (int) (new VelService($db))->option('vendor_first_response_days', 14) . ' days')->toSql();
        $draft  = AdvisoryState::DRAFT->value;
        $ready  = AdvisoryState::READY->value;

        try {
            return $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'title', 'state', 'status', 'date_public', 'date_vendor_notified', 'date_fixed']))
                    ->from($db->quoteName('#__vel_advisories'))
                    ->where($db->quoteName('state') . ' IN (:a, :b)')
                    ->where('((' . $db->quoteName('date_public') . ' IS NOT NULL AND ' . $db->quoteName('date_public') . ' < :today) OR ('
                        . $db->quoteName('date_vendor_notified') . ' IS NOT NULL AND ' . $db->quoteName('date_vendor_notified') . ' < :window AND '
                        . $db->quoteName('date_fixed') . ' IS NULL))')
                    ->bind(':a', $draft)
                    ->bind(':b', $ready)
                    ->bind(':today', $today)
                    ->bind(':window', $window)
                    ->order($db->quoteName('date_public') . ' ASC'),
                0,
                20
            )->loadObjectList() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
