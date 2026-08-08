<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Access;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The per-user privileges of `#__jed_user_access`.
 *
 * An enum rather than loose strings so a typo at a call site is a fatal error rather than a
 * silently open gate - `may($id, 'reveiw')` returning true would be exactly the failure this
 * layer exists to prevent, and it would look like working code.
 *
 * The values are the column names, because that is what they are.
 *
 * @since 4.0.0
 */
enum Privilege: string
{
    /**
     * Submit a new listing.
     *
     * @since 4.0.0
     */
    case CREATE_LISTING = 'create_listing';

    /**
     * Edit an existing listing.
     *
     * @since 4.0.0
     */
    case EDIT_LISTING = 'edit_listing';

    /**
     * Have an update-server XML read on the listing's behalf.
     *
     * @since 4.0.0
     */
    case UPDATE_XML = 'update_xml';

    /**
     * Write a review.
     *
     * @since 4.0.0
     */
    case REVIEW = 'review';

    /**
     * Report a listing or a review.
     *
     * @since 4.0.0
     */
    case REPORT = 'report';

    /**
     * The language key explaining a refusal of this privilege.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function deniedMessage(): string
    {
        return 'COM_JED_ACCESS_DENIED_' . strtoupper($this->value);
    }

    /**
     * The language key naming this privilege - the same wording the list column uses.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_JED_USERACCESS_PRIVILEGE_' . strtoupper($this->value);
    }
}
