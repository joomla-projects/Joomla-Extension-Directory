<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.vel
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\Vel\Extension;

\defined('_JEXEC') or die;

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Jed\Component\Vel\Administrator\Feed\FeedRunner;
use Jed\Component\Vel\Administrator\Mail\ImapFetcher;
use Jed\Component\Vel\Administrator\Service\ReportService;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * The VEL's background routines (VEL2-PRD §6.9.5), owned by the VEL team rather than added to
 * plg_task_jed:
 *
 *  - `vel.feed_cna`     pull the CVE Program feed into the review queue
 *  - `vel.feed_nvd`     pull the NVD feed and enrich our own records
 *  - `vel.mailfetch`    poll the team mailbox over IMAP
 *  - `vel.housekeeping` purge unverified reports, publish on embargo (if allowed), reconcile
 *                       the abandonware status, drop stale caches
 *
 * @since 4.0.0
 */
final class Vel extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var array<string, array<string, string>>
     *
     * @since 4.0.0
     */
    protected const TASKS_MAP = [
        'vel.feed_cna' => [
            'langConstPrefix' => 'PLG_TASK_VEL_FEED_CNA',
            'form'            => 'feed',
            'method'          => 'feedCna',
        ],
        'vel.feed_nvd' => [
            'langConstPrefix' => 'PLG_TASK_VEL_FEED_NVD',
            'form'            => 'feed',
            'method'          => 'feedNvd',
        ],
        'vel.mailfetch' => [
            'langConstPrefix' => 'PLG_TASK_VEL_MAILFETCH',
            'form'            => 'mailfetch',
            'method'          => 'mailFetch',
        ],
        'vel.housekeeping' => [
            'langConstPrefix' => 'PLG_TASK_VEL_HOUSEKEEPING',
            'form'            => 'housekeeping',
            'method'          => 'housekeeping',
        ],
    ];

    /**
     * @var boolean
     *
     * @since 4.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @param array             $config An optional associative array of configuration settings.
     * @param DatabaseInterface $db     The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(array $config, private readonly DatabaseInterface $db)
    {
        parent::__construct($config);
    }

    /**
     * @return array<string, string>
     *
     * @since 4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * @param ExecuteTaskEvent $event The event.
     *
     * @return int
     *
     * @since 4.0.0
     */
    protected function feedCna(ExecuteTaskEvent $event): int
    {
        return $this->feed(FeedSource::CNA, $event);
    }

    /**
     * @param ExecuteTaskEvent $event The event.
     *
     * @return int
     *
     * @since 4.0.0
     */
    protected function feedNvd(ExecuteTaskEvent $event): int
    {
        return $this->feed(FeedSource::NVD, $event);
    }

    /**
     * @param FeedSource       $source Which feed.
     * @param ExecuteTaskEvent $event  The event.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function feed(FeedSource $source, ExecuteTaskEvent $event): int
    {
        $params = $event->getArgument('params');
        $budget = max(30, (int) ($params->budget_seconds ?? 240));

        if (!ComponentHelper::isEnabled('com_vel')) {
            $this->logTask('com_vel is not enabled.', 'warning');

            return TaskStatus::KNOCKOUT;
        }

        try {
            $this->bootComponent();

            $run = (new FeedRunner($this->db, new VelService($this->db)))->run($source, $budget);
        } catch (Throwable $e) {
            $this->logTask('vel.feed_' . $source->value . ' failed: ' . $e->getMessage(), 'error');

            return TaskStatus::KNOCKOUT;
        }

        $this->logTask(\sprintf(
            'vel.feed_%s: %s - seen %d, new %d, updated %d. %s',
            $source->value,
            (string) $run->status,
            (int) $run->items_seen,
            (int) $run->items_new,
            (int) $run->items_updated,
            (string) $run->message
        ), (string) $run->status === 'failed' ? 'error' : 'info');

        return (string) $run->status === 'failed' ? TaskStatus::KNOCKOUT : TaskStatus::OK;
    }

    /**
     * @param ExecuteTaskEvent $event The event.
     *
     * @return int
     *
     * @since 4.0.0
     */
    protected function mailFetch(ExecuteTaskEvent $event): int
    {
        $params = $event->getArgument('params');
        $limit  = max(1, (int) ($params->batch_size ?? 50));

        if ((string) ComponentHelper::getParams('com_vel')->get('inbound_mode', 'off') !== 'imap') {
            $this->logTask('vel.mailfetch: inbound mode is not IMAP; nothing to do.');

            return TaskStatus::OK;
        }

        try {
            $this->bootComponent();

            $stats = (new ImapFetcher($this->db))->run($limit);
        } catch (Throwable $e) {
            $this->logTask('vel.mailfetch failed: ' . $e->getMessage(), 'error');

            return TaskStatus::KNOCKOUT;
        }

        $this->logTask(\sprintf(
            'vel.mailfetch: fetched %d - replies %d, new reports %d, duplicates %d, skipped %d.',
            $stats['fetched'],
            $stats['replies'],
            $stats['reports'],
            $stats['duplicates'],
            $stats['skipped']
        ));

        return TaskStatus::OK;
    }

    /**
     * @param ExecuteTaskEvent $event The event.
     *
     * @return int
     *
     * @since 4.0.0
     */
    protected function housekeeping(ExecuteTaskEvent $event): int
    {
        try {
            $this->bootComponent();

            $service = new VelService($this->db);
            $purged  = (new ReportService($this->db))->purgeUnverified();
            $auto    = $this->publishOnEmbargo($service);
            $synced  = $this->reconcileAbandonware($service);
        } catch (Throwable $e) {
            $this->logTask('vel.housekeeping failed: ' . $e->getMessage(), 'error');

            return TaskStatus::KNOCKOUT;
        }

        $this->logTask(\sprintf('vel.housekeeping: purged %d unverified reports, published %d on embargo, reconciled %d abandonware statuses.', $purged, $auto, $synced));

        return TaskStatus::OK;
    }

    /**
     * Publish advisories in Ready whose disclosure date has passed - only when the option says
     * so, which by default it does not.
     *
     * @param VelService $service The service.
     *
     * @return int  How many.
     *
     * @since 4.0.0
     */
    private function publishOnEmbargo(VelService $service): int
    {
        if ((int) $service->option('auto_publish_on_embargo', 0) !== 1) {
            return 0;
        }

        $ready = AdvisoryState::READY->value;
        $now   = Factory::getDate()->toSql();

        $ids = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__vel_advisories'))
                ->where($this->db->quoteName('state') . ' = :ready')
                ->where($this->db->quoteName('date_public') . ' IS NOT NULL')
                ->where($this->db->quoteName('date_public') . ' <= :now')
                ->bind(':ready', $ready)
                ->bind(':now', $now)
        )->loadColumn() ?: [];

        $done = 0;

        foreach ($ids as $id) {
            try {
                $service->transition((int) $id, AdvisoryState::PUBLISHED, 0);
                $done++;
            } catch (Throwable $e) {
                $this->logTask('vel.housekeeping: advisory ' . $id . ' not published: ' . $e->getMessage(), 'warning');
            }
        }

        return $done;
    }

    /**
     * Advisories linked to an abandonware case follow the case (VEL2-PRD §6.9.4).
     *
     * @param VelService $service The service.
     *
     * @return int  How many statuses changed.
     *
     * @since 4.0.0
     */
    private function reconcileAbandonware(VelService $service): int
    {
        $ids = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__vel_advisories'))
                ->where($this->db->quoteName('abandonware_case_id') . ' IS NOT NULL')
                ->where($this->db->quoteName('status_override') . ' = 0')
        )->loadColumn() ?: [];

        $changed = 0;

        foreach ($ids as $id) {
            $before = (string) ($service->load((int) $id)?->status ?? '');
            $after  = $service->refreshStatus((int) $id, 0)->value;

            if ($before !== $after) {
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Boot com_vel so its listeners (notifications, cache drops) hear what the routines do.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function bootComponent(): void
    {
        try {
            Factory::getApplication()->bootComponent('com_vel');
        } catch (Throwable $e) {
            // Listeners are a convenience; the routine does not depend on them.
        }
    }
}
