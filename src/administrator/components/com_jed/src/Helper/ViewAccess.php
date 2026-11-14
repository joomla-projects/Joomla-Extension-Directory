<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;

/**
 * Who may open the non-list layouts of the administrator views.
 *
 * The edit, history and compare layouts show the whole record, internal fields included. They are
 * normally reached through a task that checks the right to edit, but the layout can also be
 * requested directly, so the dispatcher has to check it too.
 *
 * @since 4.0.0
 */
final class ViewAccess
{
    /**
     * Layouts that only a user who may edit anything in the component may open.
     *
     * @since 4.0.0
     */
    private const MODERATION_LAYOUTS = ['historylist', 'compare', 'deleted'];

    /**
     * Throw when the user may not open the requested layout.
     *
     * @param User   $user      The current user.
     * @param string $component The component, for example `com_jed`.
     * @param string $layout    The requested layout.
     *
     * @return void
     *
     * @throws Exception With code 403.
     *
     * @since 4.0.0
     */
    public static function check(User $user, string $component, string $layout): void
    {
        if ($layout === 'default' || $layout === '') {
            return;
        }

        $canEdit = $user->authorise('core.edit', $component);

        if (!\in_array($layout, self::MODERATION_LAYOUTS, true)) {
            $canEdit = $canEdit || $user->authorise('core.edit.own', $component);
        }

        if (!$canEdit) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
