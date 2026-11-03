<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Actionlog.vel
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Actionlog\Vel\Extension;

\defined('_JEXEC') or die;

use Joomla\Component\Actionlogs\Administrator\Plugin\ActionLogPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

/**
 * Writes the VEL's decisions to the User Actions Log (VEL2-PRD §6.9.5): publish, retire,
 * status and CVE changes. Listens to VelService's events directly, so nothing at the call site
 * has to remember to log.
 *
 * Only decisions: a draft being saved is not one, and the log would drown in them.
 *
 * @since 4.0.0
 */
final class Vel extends ActionLogPlugin implements SubscriberInterface
{
    /**
     * @var boolean
     *
     * @since 4.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return array<string, string>
     *
     * @since 4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onVelAdvisoryPublished'     => 'onPublished',
            'onVelAdvisoryRetired'       => 'onRetired',
            'onVelAdvisoryStateChanged'  => 'onStateChanged',
            'onVelAdvisoryStatusChanged' => 'onStatusChanged',
            'onVelAdvisoryCveChanged'    => 'onCveChanged',
        ];
    }

    /**
     * @param EventInterface $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onPublished(EventInterface $event): void
    {
        $this->log($event, 'PLG_ACTIONLOG_VEL_ADVISORY_PUBLISHED');
    }

    /**
     * @param EventInterface $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onRetired(EventInterface $event): void
    {
        $this->log($event, 'PLG_ACTIONLOG_VEL_ADVISORY_RETIRED', ['reason' => (string) ($this->context($event)['reason'] ?? '')]);
    }

    /**
     * @param EventInterface $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onStateChanged(EventInterface $event): void
    {
        $context = $this->context($event);
        $this->log($event, 'PLG_ACTIONLOG_VEL_ADVISORY_STATE', ['from' => (string) ($context['from'] ?? ''), 'to' => (string) ($context['to'] ?? '')]);
    }

    /**
     * @param EventInterface $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onStatusChanged(EventInterface $event): void
    {
        $context = $this->context($event);
        $this->log($event, 'PLG_ACTIONLOG_VEL_ADVISORY_STATUS', ['from' => (string) ($context['from'] ?? ''), 'to' => (string) ($context['to'] ?? '')]);
    }

    /**
     * @param EventInterface $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onCveChanged(EventInterface $event): void
    {
        $context = $this->context($event);
        $this->log($event, 'PLG_ACTIONLOG_VEL_ADVISORY_CVE', ['cve' => (string) ($context['cve_id'] ?? ''), 'state' => (string) ($context['state'] ?? '')]);
    }

    /**
     * @param EventInterface $event The event.
     *
     * @return array
     *
     * @since 4.0.0
     */
    private function context(EventInterface $event): array
    {
        return (array) $event->getArgument('context', []);
    }

    /**
     * @param EventInterface $event The event.
     * @param string         $key   Language key.
     * @param array          $extra Extra scalars for the message.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function log(EventInterface $event, string $key, array $extra = []): void
    {
        $advisory = $event->getArgument('advisory');

        if (!\is_object($advisory) || empty($advisory->id)) {
            return;
        }

        $id      = (int) $advisory->id;
        $message = array_merge([
            'id'        => $id,
            'reference' => \sprintf('VEL-%04d', $id),
            'title'     => (string) ($advisory->title ?? ''),
            'itemlink'  => 'index.php?option=com_vel&task=advisory.edit&id=' . $id,
            'actor'     => (int) $event->getArgument('actorId', 0) === 0 ? 'automation' : '',
        ], $extra);

        $this->addLog([$message], $key, 'com_vel.advisory', (int) $event->getArgument('actorId', 0) ?: null);
    }
}
