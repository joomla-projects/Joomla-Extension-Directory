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
 * What the classifier made of an external record (VEL2-PRD §6.6.1).
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

    case UNRELATED = 'unrelated';
    case UNKNOWN   = 'unknown';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_FEED_CLASS_' . strtoupper($this->value);
    }
}
