<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Controller\UrlcheckController as AdministratorUrlcheckController;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;

/**
 * The site half of layer 2's endpoint.
 *
 * Joomla resolves a controller inside the namespace of the application handling the request, so
 * the developer's form and the JED team's form cannot share one class. Everything that decides
 * *what* happens - the guards, the rate limit, the cache, the validators - is inherited, because
 * a second copy of that would be a second place for a rule to be forgotten.
 *
 * What is genuinely different is one question: who may ask.
 *
 * @since 4.1.0
 */
class UrlcheckController extends AdministratorUrlcheckController
{
    /**
     * Whether this user may have the JED make a request on their behalf.
     *
     * On the site that is the listing's owner or one of its accepted maintainers - never
     * `created_by`, and never an invitation that has not been accepted yet (8.8, `P1-03` item 4).
     * Checked against the named listing, so the endpoint cannot be used to probe one the caller
     * has nothing to do with.
     *
     * The administrator answers the same question with `core.edit` on the component, because a
     * team member with the right to edit any listing has no per-listing narrowing to apply.
     *
     * @param int $extensionId The listing being edited, 0 for one being created.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    protected function mayCheck(int $extensionId): bool
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user === null || $user->guest) {
            return false;
        }

        if ($extensionId > 0) {
            return JedHelper::isOwnerOrMaintainer($extensionId);
        }

        // A listing being created has no id to check against yet.
        return $user->authorise('core.create', 'com_jed');
    }
}
