<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Field;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Field\ListingrefField as AdminListingrefField;

/**
 * The site half of the `P1-23` link fields.
 *
 * Extends the backend field rather than repeating it. The other fields in this directory are
 * copied side by side, and `JedcategoryField` is the argument against doing that again: two
 * copies of one control are two places for the same rule to be fixed in only one of them. There
 * is nothing site-specific about resolving a JED URL to a listing id.
 *
 * @since 4.0.0
 */
class ListingrefField extends AdminListingrefField
{
}
