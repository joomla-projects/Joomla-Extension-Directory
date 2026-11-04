<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Service;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisorySource;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\CveState;
use Jed\Component\Vel\Administrator\Enum\RetiredReason;
use Jed\Component\Vel\Administrator\Event\AdvisoryEvent;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

/**
 * The advisory lifecycle, and the only place it changes (VEL2-PRD §8.2).
 *
 * Everything that moves an advisory between states, sets its public status, touches the three
 * auto-maintained dates or changes its CVE fields goes through here - the backend toolbar, the
 * feed merger, the fix-notice form, the housekeeping task. That is what makes the invariants
 * hold: `date_published` is set exactly once; nothing reaches `ready` incomplete; nothing
 * outside `published` is ever public; a CVE id is unique. An invariant enforced in four places
 * is enforced in three.
 *
 * Modelled on com_abandonware's CaseService, including the private `writeState()` that nothing
 * else calls.
 *
 * @since 4.0.0
 */
class VelService
{
    /**
     * @var AdvisoryRepository
     *
     * @since 4.0.0
     */
    private AdvisoryRepository $repository;

    /**
     * @param DatabaseInterface       $db         The database driver.
     * @param AdvisoryRepository|null $repository The reader; built if not given.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db, ?AdvisoryRepository $repository = null)
    {
        $this->repository = $repository ?? new AdvisoryRepository($db);
    }

    /**
     * @return AdvisoryRepository
     *
     * @since 4.0.0
     */
    public function repository(): AdvisoryRepository
    {
        return $this->repository;
    }

    /**
     * Read an option out of the component configuration.
     *
     * @param string $key      The option name.
     * @param mixed  $fallback What to use when the component has no options saved yet.
     *
     * @return mixed
     *
     * @since 4.0.0
     */
    public function option(string $key, mixed $fallback = null): mixed
    {
        $value = ComponentHelper::getParams('com_vel')->get($key, null);

        return $value === null || $value === '' ? $fallback : $value;
    }

    /**
     * Make com_vel's strings available outside the component (scheduled tasks, other components).
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function loadLanguage(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        Factory::getApplication()->getLanguage()->load('com_vel', JPATH_ADMINISTRATOR . '/components/com_vel');
    }

    /**
     * @param int $advisoryId The advisory.
     *
     * @return object|null  The row, without children.
     *
     * @since 4.0.0
     */
    public function load(int $advisoryId): ?object
    {
        if ($advisoryId <= 0) {
            return null;
        }

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_advisories'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * @param int $advisoryId The advisory.
     *
     * @return object  The row with children.
     *
     * @throws RuntimeException  Not found.
     *
     * @since 4.0.0
     */
    private function require(int $advisoryId): object
    {
        $advisory = $this->repository->find($advisoryId);

        if ($advisory === null) {
            $this->loadLanguage();

            throw new RuntimeException(Text::_('COM_VEL_ERROR_ADVISORY_NOT_FOUND'));
        }

        return $advisory;
    }

    /**
     * What stands between an advisory and `ready` (VEL2-PRD §6.2).
     *
     * @param object $advisory The advisory with children.
     *
     * @return string[]  Human-readable problems; empty when it may be published.
     *
     * @since 4.0.0
     */
    public function validateForPublication(object $advisory): array
    {
        $this->loadLanguage();

        $problems = [];

        if (trim((string) ($advisory->title ?? '')) === '') {
            $problems[] = Text::_('COM_VEL_VALIDATE_TITLE');
        }

        if (trim(strip_tags((string) ($advisory->description ?? ''))) === '') {
            $problems[] = Text::_('COM_VEL_VALIDATE_DESCRIPTION');
        }

        $products = (array) ($advisory->products ?? []);

        if ($products === []) {
            $problems[] = Text::_('COM_VEL_VALIDATE_PRODUCT');
        }

        foreach ($products as $index => $product) {
            if (trim((string) ($product->product ?? '')) === '' || trim((string) ($product->vendor ?? '')) === '') {
                $problems[] = Text::sprintf('COM_VEL_VALIDATE_PRODUCT_NAMES', $index + 1);
            }

            if ((array) ($product->versions ?? []) === []) {
                $problems[] = Text::sprintf('COM_VEL_VALIDATE_PRODUCT_VERSIONS', (string) ($product->product ?? ($index + 1)));
            }
        }

        if ((array) ($advisory->cwes ?? []) === []) {
            $problems[] = Text::_('COM_VEL_VALIDATE_CWE');
        }

        if (AdvisoryStatus::tryFrom((string) ($advisory->status ?? '')) === null) {
            $problems[] = Text::_('COM_VEL_VALIDATE_STATUS');
        }

        $requireCvss = (int) $this->option('require_cvss_to_publish', 1) === 1;
        $hasCvss     = !empty($advisory->cvss_vector) && $advisory->cvss_score !== null;
        $hasManual   = !empty($advisory->severity) && trim((string) ($advisory->severity_reason ?? '')) !== '';

        if ($requireCvss && !$hasCvss && !$hasManual) {
            $problems[] = Text::_('COM_VEL_VALIDATE_CVSS');
        }

        return $problems;
    }

    /**
     * Move an advisory to a new state, refusing moves the process does not allow.
     *
     * @param int           $advisoryId The advisory.
     * @param AdvisoryState $next       Where it is going.
     * @param int           $actorId    Who is moving it.
     * @param array         $options    `force` (publish despite a future date_public), `reason`
     *                                  (RetiredReason value, required for retired), `note`.
     *
     * @return void
     *
     * @throws RuntimeException  The move is not legal, or the advisory is not ready for it.
     *
     * @since 4.0.0
     */
    public function transition(int $advisoryId, AdvisoryState $next, int $actorId, array $options = []): void
    {
        $this->loadLanguage();

        $advisory = $this->require($advisoryId);
        $current  = AdvisoryState::tryFrom((string) $advisory->state) ?? AdvisoryState::DRAFT;

        if ($current === $next) {
            return;
        }

        if (!$current->canMoveTo($next)) {
            throw new RuntimeException(Text::sprintf(
                'COM_VEL_ERROR_ILLEGAL_TRANSITION',
                Text::_($current->label()),
                Text::_($next->label())
            ));
        }

        switch ($next) {
            case AdvisoryState::READY:
            case AdvisoryState::PUBLISHED:
                $problems = $this->validateForPublication($advisory);

                if ($problems !== []) {
                    throw new RuntimeException(Text::_('COM_VEL_ERROR_NOT_READY') . ' ' . implode(' ', $problems));
                }

                if ($next === AdvisoryState::PUBLISHED) {
                    $this->publishNow($advisory, $actorId, (bool) ($options['force'] ?? false));

                    return;
                }

                break;

            case AdvisoryState::RETIRED:
                $reason = RetiredReason::tryFrom((string) ($options['reason'] ?? ''));

                if ($reason === null) {
                    throw new RuntimeException(Text::_('COM_VEL_ERROR_RETIRED_REASON_REQUIRED'));
                }

                $this->retireNow($advisory, $reason, $actorId, (string) ($options['note'] ?? ''));

                return;

            default:
                break;
        }

        $this->writeState($advisoryId, $next, $actorId);

        $this->dispatch('onVelAdvisoryStateChanged', $this->load($advisoryId) ?? $advisory, $actorId, [
            'from' => $current->value,
            'to'   => $next->value,
        ]);
    }

    /**
     * `ready -> published` (and `retired -> published`). Sets `date_published` the first time
     * and never again; sets `date_updated` every time; writes a revision; tells the listeners.
     *
     * @param object $advisory The advisory with children.
     * @param int    $actorId  Who.
     * @param bool   $force    Publish even though `date_public` is still in the future.
     *
     * @return void
     *
     * @throws RuntimeException  Embargo not over and not forced.
     *
     * @since 4.0.0
     */
    private function publishNow(object $advisory, int $actorId, bool $force): void
    {
        $now = Factory::getDate();

        if (!$force && !empty($advisory->date_public) && strtotime((string) $advisory->date_public) > $now->toUnix()) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_EMBARGO_NOT_OVER', (string) $advisory->date_public));
        }

        $id        = (int) $advisory->id;
        $nowSql    = $now->toSql();
        $published = AdvisoryState::PUBLISHED->value;
        $first     = empty($advisory->date_published);

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__vel_advisories'))
            ->set($this->db->quoteName('state') . ' = :state')
            ->set($this->db->quoteName('date_updated') . ' = :now')
            ->set($this->db->quoteName('retired_reason') . ' = NULL')
            ->set($this->db->quoteName('date_retired') . ' = NULL')
            ->set($this->db->quoteName('modified') . ' = :now2')
            ->set($this->db->quoteName('modified_by') . ' = :actor')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':state', $published)
            ->bind(':now', $nowSql)
            ->bind(':now2', $nowSql)
            ->bind(':actor', $actorId, ParameterType::INTEGER)
            ->bind(':id', $id, ParameterType::INTEGER);

        if ($first) {
            // Exactly once. A republish after a retirement keeps the original date: the record
            // was first made public then, and the CVE Program's datePublished means the same.
            $query->set($this->db->quoteName('date_published') . ' = :published')->bind(':published', $nowSql);
        }

        if (empty($advisory->date_public)) {
            $query->set($this->db->quoteName('date_public') . ' = :public')->bind(':public', $nowSql);
        }

        $this->db->setQuery($query)->execute();

        $this->snapshot($id, $actorId);

        Log::add(\sprintf('Advisory %d published by user %d', $id, $actorId), Log::INFO, 'com_vel');

        $fresh = $this->repository->find($id) ?? $advisory;

        $this->dispatch('onVelAdvisoryPublished', $fresh, $actorId, ['first' => $first]);
    }

    /**
     * `published -> retired`.
     *
     * @param object        $advisory The advisory.
     * @param RetiredReason $reason   Why.
     * @param int           $actorId  Who.
     * @param string        $note     Appended to the internal notes.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function retireNow(object $advisory, RetiredReason $reason, int $actorId, string $note): void
    {
        $id      = (int) $advisory->id;
        $now     = Factory::getDate()->toSql();
        $retired = AdvisoryState::RETIRED->value;
        $why     = $reason->value;

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__vel_advisories'))
            ->set($this->db->quoteName('state') . ' = :state')
            ->set($this->db->quoteName('retired_reason') . ' = :reason')
            ->set($this->db->quoteName('date_retired') . ' = :now')
            ->set($this->db->quoteName('date_updated') . ' = :now2')
            ->set($this->db->quoteName('modified') . ' = :now3')
            ->set($this->db->quoteName('modified_by') . ' = :actor')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':state', $retired)
            ->bind(':reason', $why)
            ->bind(':now', $now)
            ->bind(':now2', $now)
            ->bind(':now3', $now)
            ->bind(':actor', $actorId, ParameterType::INTEGER)
            ->bind(':id', $id, ParameterType::INTEGER);

        if ($note !== '') {
            $stamped = "\n[" . $now . '] ' . Text::_($reason->label()) . ': ' . $note;
            $query->set($this->db->quoteName('internal_notes') . ' = CONCAT(IFNULL('
                . $this->db->quoteName('internal_notes') . ", ''), :note)")
                ->bind(':note', $stamped);
        }

        $this->db->setQuery($query)->execute();

        $this->snapshot($id, $actorId);

        Log::add(\sprintf('Advisory %d retired (%s) by user %d', $id, $reason->value, $actorId), Log::WARNING, 'com_vel');

        $this->dispatch('onVelAdvisoryRetired', $this->load($id) ?? $advisory, $actorId, ['reason' => $reason->value]);
    }

    /**
     * Write a state with no further checking. Private on purpose.
     *
     * @param int           $advisoryId The advisory.
     * @param AdvisoryState $state      The new state.
     * @param int           $actorId    Who.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function writeState(int $advisoryId, AdvisoryState $state, int $actorId): void
    {
        $now   = Factory::getDate()->toSql();
        $value = $state->value;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_advisories'))
                ->set($this->db->quoteName('state') . ' = :state')
                ->set($this->db->quoteName('modified') . ' = :now')
                ->set($this->db->quoteName('modified_by') . ' = :actor')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':state', $value)
                ->bind(':now', $now)
                ->bind(':actor', $actorId, ParameterType::INTEGER)
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * After a published advisory has been saved: stamp `date_updated`, write a revision, tell
     * the listeners. Called by the model, never by a form field.
     *
     * @param int $advisoryId The advisory.
     * @param int $actorId    Who.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function touchPublished(int $advisoryId, int $actorId): void
    {
        $advisory = $this->load($advisoryId);

        if ($advisory === null || (string) $advisory->state !== AdvisoryState::PUBLISHED->value) {
            return;
        }

        $now = Factory::getDate()->toSql();

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_advisories'))
                ->set($this->db->quoteName('date_updated') . ' = :now')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':now', $now)
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
        )->execute();

        $this->snapshot($advisoryId, $actorId);

        $this->dispatch('onVelAdvisoryUpdated', $this->repository->find($advisoryId) ?? $advisory, $actorId);
    }

    /**
     * The public status an advisory's data implies (VEL2-PRD §6.1.1).
     *
     * @param object $advisory The advisory with children.
     *
     * @return AdvisoryStatus
     *
     * @since 4.0.0
     */
    public function deriveStatus(object $advisory): AdvisoryStatus
    {
        if (!empty($advisory->abandonware_case_id) && $this->abandonwareConcluded((int) $advisory->abandonware_case_id)) {
            return AdvisoryStatus::ABANDONED;
        }

        $products = (array) ($advisory->products ?? []);

        if ($products === []) {
            return AdvisoryStatus::LIVE;
        }

        foreach ($products as $product) {
            if (trim((string) ($product->fixed_version ?? '')) === '') {
                return AdvisoryStatus::LIVE;
            }
        }

        return AdvisoryStatus::PATCHED;
    }

    /**
     * Recompute and store the status unless an editor pinned it.
     *
     * @param int $advisoryId The advisory.
     * @param int $actorId    Who.
     *
     * @return AdvisoryStatus  The status now stored.
     *
     * @since 4.0.0
     */
    public function refreshStatus(int $advisoryId, int $actorId = 0): AdvisoryStatus
    {
        $advisory = $this->require($advisoryId);
        $current  = AdvisoryStatus::tryFrom((string) $advisory->status) ?? AdvisoryStatus::LIVE;

        if ((int) ($advisory->status_override ?? 0) === 1) {
            return $current;
        }

        $derived = $this->deriveStatus($advisory);

        if ($derived !== $current) {
            $this->writeStatus($advisoryId, $derived, false, $actorId, $current);
        }

        return $derived;
    }

    /**
     * Pin (or unpin) the public status by hand.
     *
     * @param int            $advisoryId The advisory.
     * @param AdvisoryStatus $status     The status.
     * @param bool           $override   Whether it is pinned against derivation.
     * @param int            $actorId    Who.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function setStatus(int $advisoryId, AdvisoryStatus $status, bool $override, int $actorId): void
    {
        $advisory = $this->require($advisoryId);
        $current  = AdvisoryStatus::tryFrom((string) $advisory->status) ?? AdvisoryStatus::LIVE;

        if (!$override) {
            $status = $this->deriveStatus($advisory);
        }

        $this->writeStatus($advisoryId, $status, $override, $actorId, $current);
    }

    /**
     * @param int            $advisoryId The advisory.
     * @param AdvisoryStatus $status     The status.
     * @param bool           $override   Pinned or derived.
     * @param int            $actorId    Who.
     * @param AdvisoryStatus $previous   What it was.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function writeStatus(int $advisoryId, AdvisoryStatus $status, bool $override, int $actorId, AdvisoryStatus $previous): void
    {
        $now      = Factory::getDate()->toSql();
        $value    = $status->value;
        $pinned   = $override ? 1 : 0;
        $advisory = $this->load($advisoryId);

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__vel_advisories'))
            ->set($this->db->quoteName('status') . ' = :status')
            ->set($this->db->quoteName('status_override') . ' = :pinned')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':status', $value)
            ->bind(':pinned', $pinned, ParameterType::INTEGER)
            ->bind(':id', $advisoryId, ParameterType::INTEGER);

        if ($status === AdvisoryStatus::PATCHED && empty($advisory?->date_fixed)) {
            $query->set($this->db->quoteName('date_fixed') . ' = :fixed')->bind(':fixed', $now);
        }

        $this->db->setQuery($query)->execute();

        if ($status !== $previous) {
            $this->touchPublished($advisoryId, $actorId);

            $this->dispatch('onVelAdvisoryStatusChanged', $this->load($advisoryId) ?? $advisory, $actorId, [
                'from' => $previous->value,
                'to'   => $status->value,
            ]);
        }
    }

    /**
     * Record a CVE id and its state. The only writer of the CVE columns and `date_reserved`.
     *
     * @param int         $advisoryId   The advisory.
     * @param string|null $cveId        The id, or null to clear.
     * @param CveState    $state        Its state.
     * @param string      $assigner     CNA short name ("Joomla" for ours).
     * @param string|null $dateReserved When it was reserved, if known; now when moving to reserved.
     * @param int         $actorId      Who.
     *
     * @return void
     *
     * @throws RuntimeException  Malformed id, or already used by another advisory.
     *
     * @since 4.0.0
     */
    public function setCve(int $advisoryId, ?string $cveId, CveState $state, string $assigner, ?string $dateReserved, int $actorId): void
    {
        $this->loadLanguage();

        $advisory = $this->require($advisoryId);
        $cveId    = $cveId === null ? null : strtoupper(trim($cveId));

        if ($cveId === '') {
            $cveId = null;
        }

        if ($cveId !== null) {
            if (!preg_match('/^CVE-\d{4}-\d{4,}$/', $cveId)) {
                throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVE_ID_INVALID', $cveId));
            }

            $other = $this->repository->findByCve($cveId);

            if ($other !== null && (int) $other->id !== $advisoryId) {
                throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_CVE_ID_TAKEN', $cveId, (int) $other->id));
            }
        } else {
            $state = CveState::NONE;
        }

        if ($state === CveState::NONE) {
            $dateReserved = null;
        } elseif ($dateReserved === null) {
            $dateReserved = $advisory->date_reserved ?: Factory::getDate()->toSql();
        }

        $now   = Factory::getDate()->toSql();
        $value = $state->value;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_advisories'))
                ->set($this->db->quoteName('cve_id') . ' = :cve')
                ->set($this->db->quoteName('cve_state') . ' = :state')
                ->set($this->db->quoteName('cve_assigner') . ' = :assigner')
                ->set($this->db->quoteName('date_reserved') . ' = :reserved')
                ->set($this->db->quoteName('modified') . ' = :now')
                ->set($this->db->quoteName('modified_by') . ' = :actor')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':cve', $cveId)
                ->bind(':state', $value)
                ->bind(':assigner', $assigner)
                ->bind(':reserved', $dateReserved)
                ->bind(':now', $now)
                ->bind(':actor', $actorId, ParameterType::INTEGER)
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
        )->execute();

        $this->touchPublished($advisoryId, $actorId);

        $this->dispatch('onVelAdvisoryCveChanged', $this->load($advisoryId) ?? $advisory, $actorId, [
            'cve_id' => $cveId,
            'state'  => $state->value,
        ]);
    }

    /**
     * Store the JSON last generated or accepted for the record.
     *
     * @param int    $advisoryId The advisory.
     * @param string $json       The document.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function storePublishedJson(int $advisoryId, string $json): void
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_advisories'))
                ->set($this->db->quoteName('published_json') . ' = :json')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':json', $json)
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * @param int      $advisoryId The advisory.
     * @param int|null $ticketId   The JED ticket, or null to clear.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function setJedTicket(int $advisoryId, ?int $ticketId): void
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_advisories'))
                ->set($this->db->quoteName('jed_ticket_id') . ' = :ticket')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':ticket', $ticketId, ParameterType::INTEGER)
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * @param int      $advisoryId The advisory.
     * @param int|null $caseId     The abandonware case, or null to clear.
     * @param int      $actorId    Who.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function setAbandonwareCase(int $advisoryId, ?int $caseId, int $actorId = 0): void
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_advisories'))
                ->set($this->db->quoteName('abandonware_case_id') . ' = :case')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':case', $caseId, ParameterType::INTEGER)
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
        )->execute();

        $this->refreshStatus($advisoryId, $actorId);
    }

    /**
     * Append a dated line to the internal notes.
     *
     * @param int    $advisoryId The advisory.
     * @param string $note       The line.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function appendNote(int $advisoryId, string $note): void
    {
        $note = trim($note);

        if ($note === '') {
            return;
        }

        $stamped = "\n[" . Factory::getDate()->toSql() . '] ' . $note;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__vel_advisories'))
                ->set($this->db->quoteName('internal_notes') . ' = CONCAT(IFNULL('
                    . $this->db->quoteName('internal_notes') . ", ''), :note)")
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':note', $stamped)
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Write a revision: the whole advisory with children as JSON.
     *
     * @param int $advisoryId The advisory.
     * @param int $actorId    Who.
     *
     * @return int  The revision number.
     *
     * @since 4.0.0
     */
    public function snapshot(int $advisoryId, int $actorId): int
    {
        $advisory = $this->repository->find($advisoryId);

        if ($advisory === null) {
            return 0;
        }

        // Never in a snapshot: the things that are not the advisory. Notes are internal, the
        // JSON is a derived artefact, check-out is session state.
        $copy = clone $advisory;
        unset($copy->internal_notes, $copy->published_json, $copy->checked_out, $copy->checked_out_time);

        $revision = 1 + (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('MAX(' . $this->db->quoteName('revision') . ')')
                ->from($this->db->quoteName('#__vel_advisory_history'))
                ->where($this->db->quoteName('advisory_id') . ' = :id')
                ->bind(':id', $advisoryId, ParameterType::INTEGER)
        )->loadResult();

        $row = (object) [
            'advisory_id' => $advisoryId,
            'revision'    => $revision,
            'state'       => (string) $advisory->state,
            'snapshot'    => json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created'     => Factory::getDate()->toSql(),
            'created_by'  => $actorId,
        ];

        try {
            $this->db->insertObject('#__vel_advisory_history', $row, 'id');
        } catch (Throwable $e) {
            Log::add(\sprintf('Advisory %d: revision could not be written: %s', $advisoryId, $e->getMessage()), Log::WARNING, 'com_vel');

            return 0;
        }

        return $revision;
    }

    /**
     * Create a bare draft - the starting point for "new advisory from report" and for the feed
     * merger. Children are written by the caller through the model.
     *
     * @param array          $data    Column values; `title` is required.
     * @param AdvisorySource $source  What created it.
     * @param int            $actorId Who.
     *
     * @return int  The new id.
     *
     * @since 4.0.0
     */
    public function createDraft(array $data, AdvisorySource $source, int $actorId): int
    {
        $now = Factory::getDate()->toSql();

        $row = (object) array_merge([
            'title'        => '',
            'alias'        => '',
            'state'        => AdvisoryState::DRAFT->value,
            'status'       => AdvisoryStatus::LIVE->value,
            'description'  => '',
            'exploited'    => 'unknown',
            'discovery'    => 'UNKNOWN',
            'cve_state'    => CveState::NONE->value,
            'cve_assigner' => '',
            'source'       => $source->value,
            'created'      => $now,
            'created_by'   => $actorId,
        ], $data);

        $row->source = $source->value;
        $row->state  = AdvisoryState::DRAFT->value;

        if (trim((string) $row->alias) === '') {
            $row->alias = \Joomla\CMS\Filter\OutputFilter::stringURLSafe((string) $row->title) ?: Factory::getDate()->format('Y-m-d-H-i-s');
        }

        // Same uniqueness rule as AdvisoryTable::check(), for a row written without the table.
        $base    = $row->alias;
        $counter = 1;

        while ($this->repository->findByAlias((string) $row->alias) !== null) {
            $row->alias = $base . '-' . (++$counter);
        }

        if (!empty($row->cve_id)) {
            $row->cve_id = strtoupper(trim((string) $row->cve_id));
        } else {
            $row->cve_id = null;
        }

        $this->db->insertObject('#__vel_advisories', $row, 'id');

        return (int) $row->id;
    }

    /**
     * Whether an abandonware case has concluded "abandoned" (VEL2-PRD §6.9.4). Tolerates the
     * component being absent.
     *
     * @param int $caseId The case.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private function abandonwareConcluded(int $caseId): bool
    {
        try {
            $status = (string) $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('status'))
                    ->from($this->db->quoteName('#__jed_abandonware_cases'))
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':id', $caseId, ParameterType::INTEGER)
            )->loadResult();
        } catch (Throwable $e) {
            return false;
        }

        return $status === 'abandoned';
    }

    /**
     * @param string $name     Event name.
     * @param object $advisory The advisory.
     * @param int    $actorId  Who.
     * @param array  $context  Extra.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function dispatch(string $name, object $advisory, int $actorId, array $context = []): void
    {
        try {
            Factory::getApplication()->getDispatcher()->dispatch($name, new AdvisoryEvent($name, $advisory, $actorId, $context));
        } catch (Throwable $e) {
            // A listener that fails must not roll back the record; it is logged and the team
            // sees the advisory in the state the table says.
            Log::add(\sprintf('Advisory %d: listener for %s failed: %s', (int) $advisory->id, $name, $e->getMessage()), Log::WARNING, 'com_vel');
        }
    }
}
