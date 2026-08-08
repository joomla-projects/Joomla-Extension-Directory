<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Service;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Abandonware\Administrator\Enum\CaseSource;
use Jed\Component\Abandonware\Administrator\Enum\Resolution;
use Jed\Component\Jed\Administrator\Transfer\TransferState;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * The automated entry point at step 1 of the workflow.
 *
 * 4.10 is explicit that orphan detection is not a separate feature - it is this. Three signals,
 * one case:
 *
 * | Signal | Source | Where it comes from |
 * | --- | --- | --- |
 * | A link has been dead long enough that the developer was told and did nothing | `P1-09` | `escalated` on `#__jed_extension_linkchecks` |
 * | The update server has been failing for weeks | 5.3 | `last_update_check_error` on `#__jed_extensions` |
 * | Nothing has changed on the listing for years | 12.3 | `modified` / `created` |
 *
 * None of them concludes anything. Each opens - or joins - a case for a person to work, and the
 * person's first obligation is step 3, the contact attempt. That ordering is the whole reason this
 * class is allowed to run unattended over ~15,000 listings: the worst it can do is make work.
 *
 * @since 4.0.0
 */
class SignalScanner
{
    /**
     * @param DatabaseInterface $db    The database driver.
     * @param CaseService       $cases The case object the signals feed.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly CaseService $cases
    ) {
    }

    /**
     * One pass: collect signals, expire grace periods.
     *
     * @param int $batchSize How many listings to consider per signal per pass.
     *
     * @return array{linkcheck: int, updatecheck: int, inactivity: int, expired: int, errors: int}
     *
     * @since 4.0.0
     */
    public function run(int $batchSize = 50): array
    {
        $this->cases->loadLanguage();

        $result = ['linkcheck' => 0, 'updatecheck' => 0, 'inactivity' => 0, 'expired' => 0, 'errors' => 0];

        // collect() is called through collectSafely() rather than directly, so that a source that
        // cannot be read at all - the classic case being a schema that has not caught up with the
        // component - costs its own signals and nothing else. Called plainly, one broken query
        // would take down the other two sources *and* the grace-period expiry below, which is the
        // part of the pass that must keep running: cases would sit in `owner_contacted` for ever
        // and nobody would see why.
        foreach ($this->collectSafely($batchSize, $result) as $signal) {
            try {
                $this->cases->raise(
                    (int) $signal['extension_id'],
                    $signal['source'],
                    $signal['detail']
                );

                $result[$signal['source']->value]++;
            } catch (Throwable $e) {
                $result['errors']++;

                Log::add(
                    \sprintf(
                        'Abandonware signal %s for extension %d failed: %s',
                        $signal['source']->value,
                        (int) $signal['extension_id'],
                        $e->getMessage()
                    ),
                    Log::ERROR,
                    'com_abandonware'
                );
            }
        }

        $result['expired'] = $this->cases->expireGracePeriods();

        return $result;
    }

    /**
     * Every signal due this pass, from all three enabled sources.
     *
     * @param int $batchSize Per source.
     *
     * @return array<int, array{extension_id: int, source: CaseSource, detail: string}>
     *
     * @since 4.0.0
     */
    public function collect(int $batchSize): array
    {
        $signals = [];

        foreach ($this->enabledSources() as [$option, $default, $reader]) {
            if ($this->cases->option($option, $default) === 1) {
                $signals = array_merge($signals, $this->{$reader}($batchSize));
            }
        }

        return $signals;
    }

    /**
     * The same, with each source isolated from the others' failures.
     *
     * @param int                $batchSize Per source.
     * @param array<string, int> $result    The run tally, by reference through the return.
     *
     * @return array<int, array{extension_id: int, source: CaseSource, detail: string}>
     *
     * @since 4.0.0
     */
    private function collectSafely(int $batchSize, array &$result): array
    {
        $signals = [];

        foreach ($this->enabledSources() as [$option, $default, $reader]) {
            if ($this->cases->option($option, $default) !== 1) {
                continue;
            }

            try {
                $signals = array_merge($signals, $this->{$reader}($batchSize));
            } catch (Throwable $e) {
                $result['errors']++;

                Log::add(
                    \sprintf('Abandonware signal source %s could not be read: %s', $reader, $e->getMessage()),
                    Log::ERROR,
                    'com_abandonware'
                );
            }
        }

        return $signals;
    }

    /**
     * The three sources, as option name, default and reader method.
     *
     * @return array<int, array{0: string, 1: int, 2: string}>
     *
     * @since 4.0.0
     */
    private function enabledSources(): array
    {
        return [
            ['signal_linkcheck', 1, 'fromLinkChecks'],
            ['signal_updatecheck', 1, 'fromUpdateChecks'],
            // Off by default: the weakest of the three, and on a catalogue of ~15,000 listings it
            // produces more cases than a team can read until the threshold has been chosen.
            ['signal_inactivity', 0, 'fromInactivity'],
        ];
    }

    /**
     * Listings whose link check was escalated and left unattended.
     *
     * `P1-09` deliberately opens no team ticket at its own threshold M - it sets `escalated` and
     * says in as many words that this is "the signal `P1-19` reads". This is the method that reads
     * it, and the reason that plan could stop where it did.
     *
     * @param int $batchSize How many to take.
     *
     * @return array<int, array{extension_id: int, source: CaseSource, detail: string}>
     *
     * @since 4.0.0
     */
    public function fromLinkChecks(int $batchSize): array
    {
        $rows = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['c.extension_id', 'c.link_type', 'c.url', 'c.status', 'c.fail_count', 'c.first_failed']))
                ->from($this->db->quoteName('#__jed_extension_linkchecks', 'c'))
                ->innerJoin(
                    $this->db->quoteName('#__jed_extensions', 'e'),
                    $this->db->quoteName('e.id') . ' = ' . $this->db->quoteName('c.extension_id')
                )
                ->where($this->db->quoteName('c.escalated') . ' = 1')
                ->where($this->db->quoteName('e.deleted') . ' = 0')
                // A case about a blocked listing is work nobody needs: it is already off the site
                // for a reason somebody recorded.
                ->where($this->db->quoteName('e.blocked') . ' = 0')
                // Only listings with no open case. The unique key would make raise() join the
                // existing one anyway, but filtering here keeps the batch full of new work rather
                // than re-reading the same escalated rows every pass.
                ->where('NOT EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__jed_abandonware_cases', 'a')
                    . ' WHERE ' . $this->db->quoteName('a.open_extension_id') . ' = ' . $this->db->quoteName('c.extension_id') . ')')
                ->order($this->db->quoteName('c.escalated_time') . ' ASC')
                ->setLimit($batchSize)
        )->loadObjectList();

        $signals = [];

        foreach ($rows ?: [] as $row) {
            $signals[] = [
                'extension_id' => (int) $row->extension_id,
                'source'       => CaseSource::LINKCHECK,
                'detail'       => Text::sprintf(
                    'COM_ABANDONWARE_SIGNAL_LINKCHECK_DETAIL',
                    (string) $row->link_type,
                    (string) $row->url,
                    (int) $row->fail_count,
                    (string) ($row->first_failed ?? '')
                ),
            ];
        }

        return $signals;
    }

    /**
     * Listings whose update server has been failing for longer than the configured tolerance.
     *
     * The tolerance matters more than it looks. `last_update_check_error` is set by a single
     * failed poll, and a single failed poll is a hoster having an afternoon. What says something
     * about maintenance is the *duration*, so this compares `last_update_check` - the time of the
     * most recent poll, which is also the time the error was last confirmed - against the
     * threshold, and requires the error to still be standing.
     *
     * @param int $batchSize How many to take.
     *
     * @return array<int, array{extension_id: int, source: CaseSource, detail: string}>
     *
     * @since 4.0.0
     */
    public function fromUpdateChecks(int $batchSize): array
    {
        $days   = $this->cases->option('updatecheck_days', 60);
        $cutoff = Factory::getDate('now -' . $days . ' days')->toSql();

        $rows = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['e.id', 'e.last_update_check_error', 'e.update_url', 'e.last_update_check']))
                ->from($this->db->quoteName('#__jed_extensions', 'e'))
                ->where($this->db->quoteName('e.last_update_check_error') . ' IS NOT NULL')
                ->where($this->db->quoteName('e.last_update_check_error') . " <> ''")
                ->where($this->db->quoteName('e.uses_updater') . ' = 1')
                ->where($this->db->quoteName('e.deleted') . ' = 0')
                ->where($this->db->quoteName('e.blocked') . ' = 0')
                ->where($this->db->quoteName('e.approved') . ' = 1')
                // The error has been standing since at least the cutoff. `modified` moves whenever
                // the developer touches the listing, so a developer who has been in to fix
                // something is not silently accumulating a case.
                ->where($this->db->quoteName('e.modified') . ' < :cutoff')
                ->where('NOT EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__jed_abandonware_cases', 'a')
                    . ' WHERE ' . $this->db->quoteName('a.open_extension_id') . ' = ' . $this->db->quoteName('e.id') . ')')
                ->bind(':cutoff', $cutoff)
                ->order($this->db->quoteName('e.last_update_check') . ' ASC')
                ->setLimit($batchSize)
        )->loadObjectList();

        $signals = [];

        foreach ($rows ?: [] as $row) {
            $signals[] = [
                'extension_id' => (int) $row->id,
                'source'       => CaseSource::UPDATECHECK,
                'detail'       => Text::sprintf(
                    'COM_ABANDONWARE_SIGNAL_UPDATECHECK_DETAIL',
                    (string) $row->update_url,
                    (string) $row->last_update_check_error,
                    (string) ($row->last_update_check ?? '')
                ),
            ];
        }

        return $signals;
    }

    /**
     * Listings nothing has happened to for a very long time.
     *
     * Off by default, and the weakest of the three by design. An extension with no release for
     * three years may simply be finished - 4.10 says so, and 13.4.5 records the same failure mode
     * for the recency factor in scoring. On its own this signal proves nothing; what makes it
     * worth collecting is that it lands in the same case as the other two, where a dead download
     * link plus four years of silence is a different picture from either alone.
     *
     * @param int $batchSize How many to take.
     *
     * @return array<int, array{extension_id: int, source: CaseSource, detail: string}>
     *
     * @since 4.0.0
     */
    public function fromInactivity(int $batchSize): array
    {
        $days   = $this->cases->option('inactivity_days', 1095);
        $cutoff = Factory::getDate('now -' . $days . ' days')->toSql();

        $rows = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['e.id', 'e.name', 'e.extension_version']))
                ->select('COALESCE(' . $this->db->quoteName('e.modified') . ', ' . $this->db->quoteName('e.created') . ') AS ' . $this->db->quoteName('touched'))
                ->from($this->db->quoteName('#__jed_extensions', 'e'))
                ->where('COALESCE(' . $this->db->quoteName('e.modified') . ', ' . $this->db->quoteName('e.created') . ') < :cutoff')
                ->where($this->db->quoteName('e.deleted') . ' = 0')
                ->where($this->db->quoteName('e.blocked') . ' = 0')
                ->where($this->db->quoteName('e.approved') . ' = 1')
                ->where($this->db->quoteName('e.state') . ' = 1')
                ->where('NOT EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__jed_abandonware_cases', 'a')
                    . ' WHERE ' . $this->db->quoteName('a.open_extension_id') . ' = ' . $this->db->quoteName('e.id') . ')')
                // Never re-opened for the same listing: a case closed as "not abandoned" means a
                // person looked and said no, and an inactivity rule that re-raises it every pass
                // would overrule them on a schedule.
                ->where('NOT EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__jed_abandonware_cases', 'h')
                    . ' WHERE ' . $this->db->quoteName('h.extension_id') . ' = ' . $this->db->quoteName('e.id') . ')')
                ->bind(':cutoff', $cutoff)
                ->order($this->db->quoteName('touched') . ' ASC')
                ->setLimit($batchSize)
        )->loadObjectList();

        $signals = [];

        foreach ($rows ?: [] as $row) {
            $signals[] = [
                'extension_id' => (int) $row->id,
                'source'       => CaseSource::INACTIVITY,
                'detail'       => Text::sprintf(
                    'COM_ABANDONWARE_SIGNAL_INACTIVITY_DETAIL',
                    (string) $row->touched,
                    $days
                ),
            ];
        }

        return $signals;
    }

    /**
     * Close cases whose extension has changed hands since the case was opened.
     *
     * Step 5 of the workflow: a new maintainer takes over and the case is resolved. `P1-04`'s
     * forced transfer is the mechanism, and rather than making that plan know about this one, this
     * looks for the completed transfer afterwards. The coupling is one-directional and the JED
     * team never has to remember to close a case they already solved by transferring the listing.
     *
     * @return int  How many were closed.
     *
     * @since 4.0.0
     */
    public function closeTransferredCases(): int
    {
        // Both terminal success states of `P1-04`. `forced` is the one step 5 names, but a case can
        // just as well end because the owner arranged an ordinary dual-confirmed handover after
        // hearing from the JED team - which is the best outcome the process has, and it would be
        // odd to leave that case open because it resolved itself the polite way.
        $done = [TransferState::COMPLETED->value, TransferState::FORCED->value];

        $rows = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['a.id']))
                ->from($this->db->quoteName('#__jed_abandonware_cases', 'a'))
                ->innerJoin(
                    $this->db->quoteName('#__jed_extension_transfers', 't'),
                    $this->db->quoteName('t.extension_id') . ' = ' . $this->db->quoteName('a.open_extension_id')
                )
                ->whereIn($this->db->quoteName('t.state'), $done, ParameterType::STRING)
                ->where($this->db->quoteName('t.completed_time') . ' IS NOT NULL')
                ->where($this->db->quoteName('t.completed_time') . ' > ' . $this->db->quoteName('a.created'))
                ->group($this->db->quoteName('a.id'))
        )->loadColumn();

        $closed = 0;

        foreach ($rows ?: [] as $id) {
            try {
                $this->cases->resolve(
                    (int) $id,
                    Resolution::TRANSFERRED,
                    0,
                    Text::_('COM_ABANDONWARE_NOTE_CLOSED_BY_TRANSFER')
                );
                $closed++;
            } catch (Throwable $e) {
                Log::add(
                    \sprintf('Abandonware case %d could not be closed after transfer: %s', (int) $id, $e->getMessage()),
                    Log::WARNING,
                    'com_abandonware'
                );
            }
        }

        return $closed;
    }
}
