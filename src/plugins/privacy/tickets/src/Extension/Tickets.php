<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Privacy.tickets
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Privacy\Tickets\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Tickets\Administrator\Privacy\TicketPrivacyService;
use Joomla\CMS\Event\Privacy\CollectCapabilitiesEvent;
use Joomla\CMS\Event\Privacy\ExportRequestEvent;
use Joomla\CMS\Event\Privacy\RemoveDataEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * Connects `com_tickets` to the Joomla Privacy Suite (8.12, `P1-18`).
 *
 * A second plugin rather than a second branch in `plg_privacy_jed`, because the ticket system is a
 * separate component with a separate install: a JED without `com_tickets` must not end up with a
 * privacy plugin querying tables that are not there.
 *
 * @since 4.1.0
 */
final class Tickets extends PrivacyPlugin implements SubscriberInterface
{
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return array
     *
     * @since 4.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPrivacyCollectAdminCapabilities' => 'onPrivacyCollectAdminCapabilities',
            'onPrivacyExportRequest'            => 'onPrivacyExportRequest',
            'onPrivacyRemoveData'               => 'onPrivacyRemoveData',
        ];
    }

    /**
     * State what the ticket system stores and what happens to it.
     *
     * @param CollectCapabilitiesEvent $event The event.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function onPrivacyCollectAdminCapabilities(CollectCapabilitiesEvent $event): void
    {
        $this->loadLanguage();

        // Guarded for the same reason as in plg_privacy_jed: this screen collects from every
        // plugin in five groups, and one that throws takes the whole declaration with it.
        $language = $this->getApplication()->getLanguage();

        if ($language) {
            // Both paths, in core's order - see the note in plg_privacy_jed.
            $language->load('com_tickets', JPATH_ADMINISTRATOR)
                || $language->load('com_tickets', JPATH_ADMINISTRATOR . '/components/com_tickets');
        }

        $capabilities = [];

        foreach (TicketPrivacyService::IN_SCOPE as $determination) {
            $capabilities[] = Text::_($determination['reason']);
        }

        foreach (array_unique(TicketPrivacyService::OUT_OF_SCOPE) as $reason) {
            $capabilities[] = Text::_($reason);
        }

        $event->addResult([Text::_('PLG_PRIVACY_TICKETS') => $capabilities]);
    }

    /**
     * Hand back the requester's tickets and messages.
     *
     * @param ExportRequestEvent $event The request event.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function onPrivacyExportRequest(ExportRequestEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        $domains = [];

        foreach ((new TicketPrivacyService($this->getDatabase()))->collect((int) $user->id) as $name => $rows) {
            $domain = $this->createDomain($name, $name . '_data');

            foreach ($rows as $row) {
                $domain->addItem($this->createItemFromArray($row, isset($row['id']) ? (int) $row['id'] : null));
            }

            $domains[] = $domain;
        }

        $event->addResult($domains);
    }

    /**
     * Carry the erasure out.
     *
     * @param RemoveDataEvent $event The remove data event.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function onPrivacyRemoveData(RemoveDataEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        $handling = (string) $this->params->get('ticket_handling', TicketPrivacyService::DELETE);

        $report = (new TicketPrivacyService($this->getDatabase()))->remove((int) $user->id, $handling);

        try {
            Log::add(
                sprintf(
                    'plg_privacy_tickets: removal request %d for user %d completed: %s',
                    (int) $event->getRequest()->id,
                    (int) $user->id,
                    json_encode($report)
                ),
                Log::INFO,
                'com_tickets'
            );
        } catch (Throwable $e) {
            // A logger that is not configured must not turn a completed erasure into a failed one.
        }
    }
}
