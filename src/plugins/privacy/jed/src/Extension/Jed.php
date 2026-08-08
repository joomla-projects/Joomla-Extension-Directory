<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Privacy.jed
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Privacy\Jed\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Privacy\PrivacyDeterminations;
use Jed\Component\Jed\Administrator\Privacy\PrivacyExportService;
use Jed\Component\Jed\Administrator\Privacy\PrivacyRemovalService;
use Joomla\CMS\Event\Privacy\CollectCapabilitiesEvent;
use Joomla\CMS\Event\Privacy\ExportRequestEvent;
use Joomla\CMS\Event\Privacy\RemoveDataEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * Connects `com_jed` to the Joomla Privacy Suite (8.12, `P1-18`).
 *
 * Thin by design. Everything that needs a determination - which tables hold personal data, what
 * happens to each on an erasure request, and why - lives in `com_jed`'s `Privacy` namespace, where
 * it sits next to the data model it describes and can be tested without a request cycle. This
 * class connects that to three events and does no deciding of its own.
 *
 * The one setting it does own is how reviews are handled, because 8.17 leaves that open and the
 * two answers are both defensible: anonymising keeps a public score honest, deleting is the
 * stricter reading of an erasure claim. Anonymise is the default; either way, the aggregates the
 * JED shows stay correct.
 *
 * @since 4.0.0
 */
final class Jed extends PrivacyPlugin implements SubscriberInterface
{
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return array
     *
     * @since 4.0.0
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
     * State, in the backend, what the JED stores about people and what it does with it.
     *
     * This is the screen 8.12 asks for. It is generated from
     * {@see PrivacyDeterminations} rather than written out here, so it cannot describe a
     * determination the code does not implement - and the tables found to hold nothing are listed
     * too, because "considered and empty" and "never looked at" are different answers.
     *
     * @param CollectCapabilitiesEvent $event The event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onPrivacyCollectAdminCapabilities(CollectCapabilitiesEvent $event): void
    {
        $this->loadLanguage();

        // The determination strings belong to com_jed - they describe com_jed's tables - and the
        // capability screen is the one place outside the component that renders them. Guarded
        // because this screen collects from every plugin in five groups: one plugin throwing here
        // takes the whole declaration down, and a privacy screen that will not render is worse
        // than one missing a line.
        $language = $this->getApplication()->getLanguage();

        if ($language) {
            // Both paths, in core's order: an override installed into administrator/language wins,
            // and the component's own file is the fallback. Loading only the first would leave this
            // screen printing raw language keys, which states nothing while looking like an answer.
            $language->load('com_jed', JPATH_ADMINISTRATOR)
                || $language->load('com_jed', JPATH_ADMINISTRATOR . '/components/com_jed');
        }

        // Deduplicated: two tables can share one determination - the image and file tables are
        // one statement about uploads - and the screen should say it once.
        $reasons = array_unique(array_merge(
            array_column(PrivacyDeterminations::IN_SCOPE, 'reason'),
            array_values(PrivacyDeterminations::OUT_OF_SCOPE)
        ));

        $capabilities = [];

        foreach ($reasons as $reason) {
            $capabilities[] = Text::_($reason);
        }

        $capabilities[] = Text::_('PLG_PRIVACY_JED_CAPABILITY_SOFT_DELETE');
        $capabilities[] = Text::_('PLG_PRIVACY_JED_CAPABILITY_RETENTION');

        $event->addResult([Text::_('PLG_PRIVACY_JED') => $capabilities]);
    }

    /**
     * Hand back everything `com_jed` holds about the requester.
     *
     * @param ExportRequestEvent $event The request event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onPrivacyExportRequest(ExportRequestEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        $domains = [];

        foreach ((new PrivacyExportService($this->getDatabase()))->collect((int) $user->id) as $name => $rows) {
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
     * The tally is written to the log rather than discarded. `com_privacy` records that a removal
     * was requested and approved; what it cannot record is what a third-party plugin actually did,
     * and "we deleted it" is a claim the JED has to be able to substantiate later.
     *
     * @param RemoveDataEvent $event The remove data event.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function onPrivacyRemoveData(RemoveDataEvent $event): void
    {
        $user = $event->getUser();

        // No account behind the address means nothing here is reachable: every data set com_jed
        // holds is keyed by a user id, not by an address.
        if (!$user) {
            return;
        }

        $handling = (string) $this->params->get('review_handling', PrivacyDeterminations::ANONYMISE);
        $actor    = (int) ($this->getApplication()->getIdentity()->id ?? 0);

        $report = (new PrivacyRemovalService($this->getDatabase()))->remove((int) $user->id, $handling, $actor);

        try {
            Log::add(
                sprintf(
                    'plg_privacy_jed: removal request %d for user %d completed: %s',
                    (int) $event->getRequest()->id,
                    (int) $user->id,
                    json_encode($report)
                ),
                Log::INFO,
                'com_jed'
            );
        } catch (Throwable $e) {
            // A logger that is not configured must not turn a completed erasure into a failed one.
        }
    }
}
