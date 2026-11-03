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
 * The team's decision about a feed item.
 *
 * @since 4.0.0
 */
enum FeedDecision: string
{
    case PENDING = 'pending';
    case CREATED = 'created';
    case MERGED  = 'merged';
    case IGNORED = 'ignored';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_FEED_DECISION_' . strtoupper($this->value);
    }
}
