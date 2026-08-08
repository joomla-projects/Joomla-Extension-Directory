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
use Jed\Component\Jed\Administrator\Access\JedAccessHelper;
use Jed\Component\Jed\Administrator\Access\Privilege;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Filing a public abandonware report, and what has to be true before one is accepted.
 *
 * Three things happen here that did not happen in the legacy form, and each is a decision rather
 * than an improvement for its own sake:
 *
 *  - **The reporter is an account.** 4.10 asks for the same abuse protection as reviews, and work
 *    item 9 names the mechanism: `P1-05`'s `report` privilege and its bans. Both are per-account,
 *    so an anonymous form cannot carry them. An abandonware report against a competitor is a
 *    plausible abuse case and the marker at the end of the process is public and commercial, which
 *    is what makes an account proportionate here where it would not be for, say, a search.
 *  - **The report joins a case.** The legacy table was a list of submissions with a `state`; a
 *    report here is the opening move of a process that somebody works.
 *  - **The address is a hash.** Same call as `P1-12`'s hit log: enough to spot a flood, not
 *    personal data for `P1-18` to export and erase.
 *
 * @since 4.0.0
 */
class ReportService
{
    /**
     * How many reports one account may file inside {@see RATE_WINDOW_HOURS}.
     *
     * A report is a slow, considered thing - somebody noticed an extension has gone quiet. Nobody
     * legitimately files five in an afternoon, and somebody working through a competitor's
     * catalogue would.
     *
     * @since 4.0.0
     */
    public const RATE_LIMIT         = 5;
    public const RATE_WINDOW_HOURS  = 24;

    /**
     * @param DatabaseInterface $db      The database driver.
     * @param CaseService       $cases   The case object a report feeds.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly CaseService $cases
    ) {
    }

    /**
     * Refuse the submission unless this account may report and is inside the rate limit.
     *
     * @param User $user The would-be reporter.
     *
     * @return void
     *
     * @throws RuntimeException  Not logged in, not permitted, banned, or over the limit.
     *
     * @since 4.0.0
     */
    public function assertMayReport(User $user): void
    {
        $userId = (int) $user->id;

        if ($userId <= 0) {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_LOGIN_REQUIRED'));
        }

        // Throws with the reason the person is refused - a withdrawn privilege and a ban read
        // differently to whoever is being turned away, and P1-05 already distinguishes them.
        JedAccessHelper::assertMay($userId, Privilege::REPORT);

        $since = Factory::getDate('now -' . self::RATE_WINDOW_HOURS . ' hours')->toSql();

        $recent = (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__jed_abandonware_reports'))
                ->where($this->db->quoteName('reporter_user_id') . ' = :uid')
                ->where($this->db->quoteName('created') . ' >= :since')
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':since', $since)
        )->loadResult();

        if ($recent >= self::RATE_LIMIT) {
            throw new RuntimeException(Text::sprintf('COM_ABANDONWARE_ERROR_RATE_LIMIT', self::RATE_LIMIT, self::RATE_WINDOW_HOURS));
        }
    }

    /**
     * Has this account already reported this extension while the case is still open?
     *
     * Not a hard refusal in itself - the caller decides - but a second report from the same person
     * about the same thing adds nothing to the case and would only inflate the report count the
     * team reads as corroboration.
     *
     * @param int $userId      The reporter.
     * @param int $extensionId The listing, or 0.
     * @param int $caseId      The open case, or 0.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function hasReported(int $userId, int $extensionId, int $caseId): bool
    {
        if ($userId <= 0 || ($extensionId <= 0 && $caseId <= 0)) {
            return false;
        }

        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__jed_abandonware_reports'))
            ->where($this->db->quoteName('reporter_user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->setLimit(1);

        if ($caseId > 0) {
            $query->where($this->db->quoteName('case_id') . ' = :cid')
                ->bind(':cid', $caseId, ParameterType::INTEGER);
        } else {
            $query->where($this->db->quoteName('extension_id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER);
        }

        return (bool) $this->db->setQuery($query)->loadResult();
    }

    /**
     * File a report, and open or join the case it belongs to.
     *
     * @param array<string, mixed> $data The submitted form data.
     * @param User                 $user The reporter.
     *
     * @return object  ['report_id' => int, 'case_id' => int, 'case' => object].
     *
     * @throws RuntimeException  Consent withheld, or the reporter may not report.
     *
     * @since 4.0.0
     */
    public function submit(array $data, User $user): object
    {
        $this->assertMayReport($user);

        // 4.6 lists recording consent as a P1 item for abandonware reporters. Withheld consent is
        // not a validation error to be worked around - it means the submission must not be stored,
        // so this refuses rather than storing with the flag off.
        if ((int) ($data['consent_to_process'] ?? 0) !== 1) {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_CONSENT_REQUIRED'));
        }

        $extensionId = (int) ($data['extension_id'] ?? 0);
        $extensionId = $extensionId > 0 && $this->listingExists($extensionId) ? $extensionId : 0;

        $tuple = [
            'name'           => trim((string) ($data['extension_name'] ?? '')),
            'version'        => trim((string) ($data['extension_version'] ?? '')),
            'url'            => trim((string) ($data['extension_url'] ?? '')),
            'developer_name' => trim((string) ($data['developer_name'] ?? '')),
        ];

        if ($extensionId <= 0 && $tuple['name'] === '') {
            throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_NO_SUBJECT'));
        }

        $detail = Text::sprintf('COM_ABANDONWARE_SIGNAL_REPORT_DETAIL', $user->username);

        if ($extensionId > 0) {
            $open = $this->cases->findOpenCase($extensionId);

            if ($open !== null && $this->hasReported((int) $user->id, $extensionId, (int) $open->id)) {
                throw new RuntimeException(Text::_('COM_ABANDONWARE_ERROR_ALREADY_REPORTED'));
            }

            $case = $this->cases->raise($extensionId, CaseSource::REPORT, $detail, (int) $user->id);
        } else {
            $case = $this->cases->raiseUnlisted($tuple, CaseSource::REPORT, $detail, (int) $user->id);
        }

        $now = Factory::getDate()->toSql();

        $report = (object) [
            'case_id'               => (int) $case->id,
            'extension_id'          => $extensionId > 0 ? $extensionId : null,
            'extension_name'        => mb_substr($tuple['name'], 0, 255),
            'extension_version'     => mb_substr($tuple['version'], 0, 100),
            'extension_url'         => mb_substr($tuple['url'], 0, 255),
            'developer_name'        => mb_substr($tuple['developer_name'], 0, 255),
            'reason'                => (string) ($data['reason'] ?? ''),
            'reporter_user_id'      => (int) $user->id,
            'reporter_name'         => mb_substr((string) $user->name, 0, 255),
            'reporter_email'        => mb_substr((string) $user->email, 0, 255),
            'reporter_organisation' => mb_substr(trim((string) ($data['reporter_organisation'] ?? '')), 0, 255),
            'consent_to_process'    => 1,
            'consent_time'          => $now,
            'reporter_ip_hash'      => $this->hashAddress((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
            'state'                 => 1,
            'created'               => $now,
            'created_by'            => (int) $user->id,
        ];

        $this->db->insertObject('#__jed_abandonware_reports', $report, 'id');

        return (object) [
            'report_id' => (int) $report->id,
            'case_id'   => (int) $case->id,
            'case'      => $case,
        ];
    }

    /**
     * @param int $extensionId The listing.
     *
     * @return bool  Whether it exists and has not been soft-deleted.
     *
     * @since 4.0.0
     */
    private function listingExists(int $extensionId): bool
    {
        return (bool) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('1')
                ->from($this->db->quoteName('#__jed_extensions'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->where($this->db->quoteName('deleted') . ' = 0')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
                ->setLimit(1)
        )->loadResult();
    }

    /**
     * Hash an address with the site secret.
     *
     * The same construction `P1-12`'s {@see \Jed\Component\Jed\Administrator\Hit\HitRecorder} uses,
     * down to the HMAC and the site secret, and for the same reason: it still deduplicates and
     * still supports the abuse work, while not being an address. Salted, because an unsalted hash
     * of an IPv4 address is an IPv4 address with extra steps - the whole space is enumerable.
     *
     * @param string $ip The address.
     *
     * @return string|null  The raw hash, or null if there was no address.
     *
     * @since 4.0.0
     */
    private function hashAddress(string $ip): ?string
    {
        if (trim($ip) === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) Factory::getApplication()->get('secret'), true);
    }
}
