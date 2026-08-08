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
 * What raised a case.
 *
 * The three automated values are the "three symptoms of one thing" from 4.9 and 12.3. They are
 * recorded separately not because they lead anywhere different - they all open the same case - but
 * because a case opened by a dead download link and one opened by four years of silence read very
 * differently to whoever picks it up, and the team's first question is always which one it was.
 *
 * @since 4.0.0
 */
enum CaseSource: string
{
    /**
     * A member of the public filled in the form.
     *
     * @since 4.0.0
     */
    case REPORT = 'report';

    /**
     * `P1-09` flagged a link as persistently dead and left unattended - `escalated` on
     * `#__jed_extension_linkchecks`. That plan deliberately opens no team ticket of its own; this
     * is the case it hands the signal to.
     *
     * @since 4.0.0
     */
    case LINKCHECK = 'linkcheck';

    /**
     * `last_update_check_error` has been set for long enough that the update server is not simply
     * having a bad week (5.3).
     *
     * @since 4.0.0
     */
    case UPDATECHECK = 'updatecheck';

    /**
     * Nothing has changed on the listing for a long time. The weakest of the three signals on its
     * own - an extension with no release for three years may simply be finished - which is why it
     * opens a case for a human rather than doing anything.
     *
     * @since 4.0.0
     */
    case INACTIVITY = 'inactivity';

    /**
     * Somebody on the JED team opened it by hand.
     *
     * @since 4.0.0
     */
    case MANUAL = 'manual';

    /**
     * @return string  The language key for the label.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_ABANDONWARE_SOURCE_' . strtoupper($this->value);
    }

    /**
     * @return bool  Whether this source is an automated signal rather than a person.
     *
     * @since 4.0.0
     */
    public function isAutomated(): bool
    {
        return \in_array($this, [self::LINKCHECK, self::UPDATECHECK, self::INACTIVITY], true);
    }
}
