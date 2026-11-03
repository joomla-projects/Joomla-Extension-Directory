<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Listener;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\ParticipantRole;
use Jed\Component\Vel\Administrator\Event\AdvisoryEvent;
use Jed\Component\Vel\Administrator\Service\JedBridge;
use Jed\Component\Vel\Administrator\Service\MessageService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

/**
 * What happens around an advisory after VelService has moved it: participants are told about a
 * publication, the JED ticket is annotated when the status changes, the public dataset cache
 * is dropped. Registered by VelComponent::boot(), so it listens wherever the component runs.
 *
 * @since 4.0.0
 */
final class AdvisoryListener implements SubscriberInterface
{
    /**
     * @return array<string, string>
     *
     * @since 4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onVelAdvisoryPublished'     => 'onPublished',
            'onVelAdvisoryRetired'       => 'onChanged',
            'onVelAdvisoryUpdated'       => 'onChanged',
            'onVelAdvisoryStatusChanged' => 'onStatusChanged',
        ];
    }

    /**
     * @param AdvisoryEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onPublished(AdvisoryEvent $event): void
    {
        $advisory = $event->getAdvisory();
        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $messages = new MessageService($db);

        Factory::getApplication()->getLanguage()->load('com_vel', JPATH_ADMINISTRATOR . '/components/com_vel');

        $path = rtrim((string) ComponentHelper::getParams('com_vel')->get('public_list_path', '/vulnerable-extensions'), '/');
        $link = rtrim(Uri::root(), '/') . $path . '/' . (int) $advisory->id . '-' . (string) $advisory->alias;

        // Only on the first publication: a republish after a retirement is not news to the
        // people in the case, and the team can write to them if it is.
        if (!(bool) ($event->getContext()['first'] ?? true)) {
            $this->dropCaches();

            return;
        }

        foreach ($messages->participants((int) $advisory->id, null) as $participant) {
            $role = ParticipantRole::tryFrom((string) $participant->role) ?? ParticipantRole::OTHER;

            if ($role === ParticipantRole::TEAM || (int) $participant->notify !== 1) {
                continue;
            }

            $messages->sendTemplate('com_vel.advisory_published', (string) $participant->email, (string) $participant->name, [
                'REFERENCE'    => \sprintf('VEL-%04d', (int) $advisory->id),
                'TITLE'        => (string) $advisory->title,
                'ADVISORYLINK' => $link,
                'CVEID'        => (string) ($advisory->cve_id ?? ''),
            ], \sprintf('VEL-%04d', (int) $advisory->id));
        }

        $this->dropCaches();
    }

    /**
     * @param AdvisoryEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onChanged(AdvisoryEvent $event): void
    {
        $advisory = $event->getAdvisory();

        if ($event->getName() === 'onVelAdvisoryRetired' && (int) ($advisory->jed_ticket_id ?? 0) > 0) {
            Factory::getApplication()->getLanguage()->load('com_vel', JPATH_ADMINISTRATOR . '/components/com_vel');

            (new JedBridge(Factory::getContainer()->get(DatabaseInterface::class)))
                ->annotateTicket((int) $advisory->jed_ticket_id, Text::sprintf('COM_VEL_JED_TICKET_RETIRED', \sprintf('VEL-%04d', (int) $advisory->id)), true);
        }

        $this->dropCaches();
    }

    /**
     * @param AdvisoryEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onStatusChanged(AdvisoryEvent $event): void
    {
        $advisory = $event->getAdvisory();
        $ticketId = (int) ($advisory->jed_ticket_id ?? 0);

        if ($ticketId > 0) {
            Factory::getApplication()->getLanguage()->load('com_vel', JPATH_ADMINISTRATOR . '/components/com_vel');

            $status   = AdvisoryStatus::tryFrom((string) ($event->getContext()['to'] ?? '')) ?? AdvisoryStatus::LIVE;
            $resolved = $status === AdvisoryStatus::PATCHED;

            (new JedBridge(Factory::getContainer()->get(DatabaseInterface::class)))->annotateTicket(
                $ticketId,
                Text::sprintf('COM_VEL_JED_TICKET_STATUS_CHANGED', \sprintf('VEL-%04d', (int) $advisory->id), Text::_($status->label())),
                $resolved
            );
        }

        $this->dropCaches();
    }

    /**
     * The dataset and the public pages are cached; anything published changing is the moment
     * to drop them.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function dropCaches(): void
    {
        try {
            $factory = Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class);
            $factory->createCacheController('callback', ['defaultgroup' => 'com_vel'])->clean('com_vel');
            $factory->createCacheController('page', ['defaultgroup' => 'page'])->clean('page');
        } catch (\Throwable $e) {
            // Caches expire on their own.
        }
    }
}
