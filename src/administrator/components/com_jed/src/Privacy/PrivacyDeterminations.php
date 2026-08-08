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

/**
 * What the JED does with each of its tables when somebody asks for their data back, or asks for
 * it to be erased.
 *
 * 8.12 requires a determination **per data set**, and the backend capability declarations exist
 * to state it. Prose in a planning document cannot do that job: it drifts from the code, and
 * nobody notices when a new table arrives without an answer. So the determinations live here, as
 * data - {@see PrivacyExportService} and {@see PrivacyRemovalService} implement them, the privacy
 * plugin renders them into the capability screen, and a test asserts that every `#__jed_` table
 * in the install schema appears in exactly one of the two lists below.
 *
 * That test is the point of this class. Adding a table without deciding what happens to it on a
 * deletion request becomes a build failure rather than a blind spot found years later.
 *
 * @since 4.1.0
 */
final class PrivacyDeterminations
{
    /**
     * The row survives, the personal fields in it do not.
     *
     * @since 4.1.0
     */
    public const ANONYMISE = 'anonymise';

    /**
     * The rows go.
     *
     * @since 4.1.0
     */
    public const DELETE = 'delete';

    /**
     * The row stays as it is, because a retention interest outweighs the erasure claim. Every use
     * of this carries the interest in its reason string - an unexplained retention is the thing
     * the capability screen exists to prevent.
     *
     * @since 4.1.0
     */
    public const RETAIN = 'retain';

    /**
     * The outcome depends on the row, or on how the JED team has configured the plugin. The
     * reason string says on what.
     *
     * @since 4.1.0
     */
    public const CONDITIONAL = 'conditional';

    /**
     * Tables holding data about an identifiable person, with the determination for each.
     *
     * `export` says whether the data set is handed back on an export request. It is false only
     * where the row is about somebody else - the transfer lookup log records hashes of *other*
     * people's addresses - or where returning it would hand back a credential.
     *
     * @var array<string, array{export: bool, handling: string, reason: string}>
     *
     * @since 4.1.0
     */
    public const IN_SCOPE = [
        '#__jed_extensions' => [
            'export'   => true,
            'handling' => self::ANONYMISE,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_EXTENSIONS',
        ],
        '#__jed_extensions_history' => [
            'export'   => true,
            'handling' => self::ANONYMISE,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_EXTENSIONS_HISTORY',
        ],
        '#__jed_extensions_maintainers' => [
            'export'   => true,
            'handling' => self::DELETE,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_MAINTAINERS',
        ],
        '#__jed_extensions_images' => [
            'export'   => true,
            'handling' => self::ANONYMISE,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_MEDIA',
        ],
        '#__jed_extensions_files' => [
            'export'   => true,
            'handling' => self::ANONYMISE,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_MEDIA',
        ],
        '#__jed_reviews' => [
            'export'   => true,
            'handling' => self::CONDITIONAL,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_REVIEWS',
        ],
        '#__jed_favorites' => [
            'export'   => true,
            'handling' => self::DELETE,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_FAVORITES',
        ],
        '#__jed_user_access' => [
            'export'   => true,
            'handling' => self::CONDITIONAL,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_USER_ACCESS',
        ],
        '#__jed_user_review_bans' => [
            'export'   => true,
            'handling' => self::RETAIN,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_REVIEW_BANS',
        ],
        '#__jed_extension_transfers' => [
            'export'   => true,
            'handling' => self::RETAIN,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_TRANSFERS',
        ],
        '#__jed_transfer_lookups' => [
            'export'   => false,
            'handling' => self::DELETE,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_TRANSFER_LOOKUPS',
        ],
        '#__jed_url_checks' => [
            'export'   => true,
            'handling' => self::ANONYMISE,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_URL_CHECKS',
        ],
        '#__jed_queue_jobs' => [
            'export'   => false,
            'handling' => self::ANONYMISE,
            'reason'   => 'COM_JED_PRIVACY_DETERMINATION_QUEUE_JOBS',
        ],
    ];

    /**
     * Tables that hold no data about an identifiable person, with the reason each is out of
     * scope. They are listed rather than omitted so that "not covered" and "considered and found
     * to hold nothing" stay distinguishable.
     *
     * @var array<string, string>
     *
     * @since 4.1.0
     */
    public const OUT_OF_SCOPE = [
        '#__jed_extensions_category_map' => 'COM_JED_PRIVACY_OUTOFSCOPE_CATEGORY_MAP',
        '#__jed_block_reasons'           => 'COM_JED_PRIVACY_OUTOFSCOPE_BLOCK_REASONS',
        '#__jed_joomla_versions'         => 'COM_JED_PRIVACY_OUTOFSCOPE_JOOMLA_VERSIONS',
        '#__jed_extension_linkchecks'    => 'COM_JED_PRIVACY_OUTOFSCOPE_LINKCHECKS',
        '#__jed_suspect_ip_ranges'       => 'COM_JED_PRIVACY_OUTOFSCOPE_SUSPECT_IP_RANGES',
        '#__jed_hit_log'                 => 'COM_JED_PRIVACY_OUTOFSCOPE_HIT_LOG',
        '#__jed_hit_stats'               => 'COM_JED_PRIVACY_OUTOFSCOPE_HIT_STATS',
    ];

    /**
     * Every table this component ships, whether or not it holds personal data.
     *
     * @return string[]
     *
     * @since 4.1.0
     */
    public static function allTables(): array
    {
        return array_merge(array_keys(self::IN_SCOPE), array_keys(self::OUT_OF_SCOPE));
    }
}
