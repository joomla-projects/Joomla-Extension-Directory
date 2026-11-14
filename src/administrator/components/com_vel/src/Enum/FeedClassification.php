<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Enum;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What the classifier made of an external record (feeds PRD §5.1: a ten-rule cascade, the
 * rule that fired recorded alongside as `classification_reason`).
 *
 * @since 4.0.0
 */
enum FeedClassification: string
{
    /**
     * Assigned by the Joomla CNA and about an extension: ours, auto-created as a draft when the
     * option says so.
     *
     * @since 4.0.0
     */
    case JOOMLA_CNA = 'joomla_cna';

    /**
     * Another CNA's record that names a Joomla extension.
     *
     * @since 4.0.0
     */
    case JOOMLA_EXTENSION_OTHER_CNA = 'joomla_extension_other_cna';

    /**
     * Joomla core. The JSST's domain; listed for the record, never turned into an advisory.
     *
     * @since 4.0.0
     */
    case JOOMLA_CORE = 'joomla_core';

    /**
     * A rejected or withdrawn CVE. Stored, never an advisory; loud if it matches one we have.
     *
     * @since 4.0.0
     */
    case REJECTED = 'rejected';

    /**
     * Mentions Joomla and nothing more decisive. Queued for a person, never discarded.
     *
     * @since 4.0.0
     */
    case UNCLASSIFIED = 'unclassified';

    /**
     * Does not concern Joomla at all. Never stored; the value exists so a classifier verdict
     * can say so without resorting to null.
     *
     * @since 4.0.0
     */
    case UNRELATED = 'unrelated';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_FEED_CLASS_' . strtoupper($this->value);
    }

    /**
     * Bootstrap badge colour. Rejected records are shown in red so a withdrawn CVE that still
     * matches a live advisory cannot hide in a list.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function badge(): string
    {
        return match ($this) {
            self::JOOMLA_CNA                 => 'primary',
            self::JOOMLA_EXTENSION_OTHER_CNA => 'info',
            self::JOOMLA_CORE                => 'secondary',
            self::REJECTED                   => 'danger',
            default                          => 'light text-dark',
        };
    }
}
