<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Hit;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The two things the JED can honestly count.
 *
 * The naming is a constraint, not a preference (13.4.4). `DOWNLOAD_CLICK` is called that because
 * that is all it is: the file almost always lives on the developer's own server, so the JED knows
 * somebody pressed the button and nothing at all about whether a download followed. Calling it
 * "downloads" would be a number the directory cannot substantiate, which 13.8 forbids outright.
 *
 * The figure that is *not* here is active installations. Joomla does not report installations to
 * the directory and update servers are run by developers, so it is not knowable without new,
 * voluntary telemetry - a data-protection discussion of its own. It must not appear in the UI.
 *
 * @since 4.0.0
 */
enum HitType: string
{
    case VIEW           = 'view';
    case DOWNLOAD_CLICK = 'download_click';

    /**
     * The `#__jed_hit_stats` column this type aggregates into.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function statsColumn(): string
    {
        return $this === self::VIEW ? 'views' : 'download_clicks';
    }
}
