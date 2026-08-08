<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Privacy;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Retention: the data nobody asked to have erased, that should go anyway.
 *
 * 8.17 lists retention periods as open and P1-18 item 5 says each needs "a number and a pruning
 * job". This is the job. The numbers below are defaults, overridable from the task's parameters,
 * and each one is argued rather than picked - an unexplained retention period is the same failure
 * as an unexplained retention.
 *
 * The raw hit log is **not** here: `P1-12` prunes it from {@see \Jed\Component\Jed\Administrator\Hit\HitAggregator},
 * because there the aggregation and the pruning have to agree about which days are finished. Two
 * jobs deleting from one table on two schedules is how a day gets aggregated after its rows are
 * gone.
 *
 * @since 4.1.0
 */
final class PrivacyRetentionService
{
    /**
     * How long a review keeps the address it was submitted from, in days.
     *
     * The address exists for one purpose - telling a genuine review from a ring of them (`P1-05`)
     * - and that purpose has a natural horizon: a rating fraud investigation is about a burst,
     * not about last year. Six months covers a release cycle and the complaints that follow one.
     * After that the column is a liability with no remaining use.
     *
     * @since 4.1.0
     */
    public const REVIEW_IP_DAYS = 180;

    /**
     * How long the recipient-lookup log is kept, in days.
     *
     * The rate limit itself only ever reads the last 24 hours. The rest of the window exists so
     * that "this account probed forty addresses over a month" stays answerable - the pattern the
     * per-window ceiling cannot see. A quarter is long enough for that pattern and short enough
     * that the log does not become a history of who somebody tried to reach.
     *
     * @since 4.1.0
     */
    public const TRANSFER_LOOKUP_DAYS = 90;

    /**
     * How long a form-time URL check is kept, in days.
     *
     * This table is a cache with a freshness window measured in hours, a per-user rate limit
     * measured in a day, and a moderation record. Only the last of those wants a long life, and
     * the *durable* link state lives in `#__jed_extension_linkchecks` rather than here. A year
     * leaves a submission's check history readable while a moderation case is open.
     *
     * @since 4.1.0
     */
    public const URL_CHECK_DAYS = 365;

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Apply every retention period once.
     *
     * @param int|null $reviewIpDays       Override for {@see self::REVIEW_IP_DAYS}.
     * @param int|null $transferLookupDays Override for {@see self::TRANSFER_LOOKUP_DAYS}.
     * @param int|null $urlCheckDays       Override for {@see self::URL_CHECK_DAYS}.
     *
     * @return array{review_ips: int, transfer_lookups: int, url_checks: int}
     *
     * @since 4.1.0
     */
    public function prune(?int $reviewIpDays = null, ?int $transferLookupDays = null, ?int $urlCheckDays = null): array
    {
        $reviewIpDays ??= self::REVIEW_IP_DAYS;
        $transferLookupDays ??= self::TRANSFER_LOOKUP_DAYS;
        $urlCheckDays ??= self::URL_CHECK_DAYS;

        // Zero or less means "keep", not "delete everything". A parameter left empty in the task
        // form arrives here as 0, and that must not be the input that empties a table.
        return [
            'review_ips'       => $reviewIpDays > 0 ? $this->clearReviewAddresses($reviewIpDays) : 0,
            'transfer_lookups' => $transferLookupDays > 0 ? $this->pruneTransferLookups($transferLookupDays) : 0,
            'url_checks'       => $urlCheckDays > 0 ? $this->pruneUrlChecks($urlCheckDays) : 0,
        ];
    }

    /**
     * Blank the address on reviews older than the window - the review itself stays.
     *
     * @param int $days The window.
     *
     * @return int
     *
     * @since 4.1.0
     */
    private function clearReviewAddresses(int $days): int
    {
        $cutoff = $this->cutoff($days);

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_reviews'))
                ->set($this->db->quoteName('ip_address') . ' = ' . $this->db->quote(''))
                ->where($this->db->quoteName('created_on') . ' < :cutoff')
                ->where($this->db->quoteName('ip_address') . ' <> ' . $this->db->quote(''))
                ->where($this->db->quoteName('ip_address') . ' IS NOT NULL')
                ->bind(':cutoff', $cutoff)
        )->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * Delete lookup log rows past the window.
     *
     * @param int $days The window.
     *
     * @return int
     *
     * @since 4.1.0
     */
    private function pruneTransferLookups(int $days): int
    {
        $cutoff = $this->cutoff($days);

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_transfer_lookups'))
                ->where($this->db->quoteName('created') . ' < :cutoff')
                ->bind(':cutoff', $cutoff)
        )->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * Delete URL check rows past the window.
     *
     * @param int $days The window.
     *
     * @return int
     *
     * @since 4.1.0
     */
    private function pruneUrlChecks(int $days): int
    {
        $cutoff = $this->cutoff($days);

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_url_checks'))
                ->where($this->db->quoteName('checked') . ' < :cutoff')
                ->bind(':cutoff', $cutoff)
        )->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * The timestamp a window of `$days` reaches back to.
     *
     * @param int $days The window, already known to be positive.
     *
     * @return string  SQL datetime.
     *
     * @since 4.1.0
     */
    private function cutoff(int $days): string
    {
        return Factory::getDate('-' . $days . ' days')->toSql();
    }
}
