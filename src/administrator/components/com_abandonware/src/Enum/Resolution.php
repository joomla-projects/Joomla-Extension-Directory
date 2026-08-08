<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Enum;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * How a case ended.
 *
 * Kept as an enum rather than free text because these are the numbers that say whether the process
 * is working. "How many contacted developers answered" and "how many reports turned out to be
 * wrong" are both questions about the health of an automation that can put a public marker on
 * somebody's product, and neither is answerable from a notes field.
 *
 * @since 4.1.0
 */
enum Resolution: string
{
    /**
     * Step 5: a new maintainer took it over through the forced transfer in `P1-04`.
     *
     * @since 4.1.0
     */
    case TRANSFERRED = 'transferred';

    /**
     * The owner replied inside the grace period. The single most valuable outcome to count: it is
     * the measure of how often the automation would have been wrong had step 3 been skipped.
     *
     * @since 4.1.0
     */
    case DEVELOPER_RESPONDED = 'developer_responded';

    /**
     * Looked at and found to be maintained after all. Distinct from the one above - here nobody
     * had to answer, the assessment was simply wrong.
     *
     * @since 4.1.0
     */
    case NOT_ABANDONED = 'not_abandoned';

    /**
     * The same subject as another case.
     *
     * @since 4.1.0
     */
    case DUPLICATE = 'duplicate';

    /**
     * The listing was removed, so there is nothing left to mark.
     *
     * @since 4.1.0
     */
    case NO_LONGER_LISTED = 'no_longer_listed';

    /**
     * The report was abuse. 4.10 names an abandonware report against a competitor as a plausible
     * abuse case, and `P1-05`'s `report` privilege is what gets withdrawn when this piles up
     * against one reporter - which requires the outcome to be recorded as its own value.
     *
     * @since 4.1.0
     */
    case ABUSE = 'abuse';

    /**
     * @return string  The language key for the label.
     *
     * @since 4.1.0
     */
    public function label(): string
    {
        return 'COM_ABANDONWARE_RESOLUTION_' . strtoupper($this->value);
    }

    /**
     * Which status a case with this resolution ends in.
     *
     * A duplicate and an abusive report are not outcomes of the process, they are reasons the case
     * should not have existed - so they close as `DISMISSED` and stay out of the counts that
     * describe how the process performed.
     *
     * @return CaseStatus
     *
     * @since 4.1.0
     */
    public function closingStatus(): CaseStatus
    {
        return match ($this) {
            self::DUPLICATE, self::ABUSE => CaseStatus::DISMISSED,
            default                      => CaseStatus::RESOLVED,
        };
    }
}
