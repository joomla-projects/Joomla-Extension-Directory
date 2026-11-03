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
 * CVE 5.x reference tags, plus the one private tag the generator adds for a JED listing.
 *
 * @since 4.0.0
 */
enum ReferenceTag: string
{
    case BROKEN_LINK           = 'broken-link';
    case CUSTOMER_ENTITLEMENT  = 'customer-entitlement';
    case EXPLOIT               = 'exploit';
    case GOVERNMENT_RESOURCE   = 'government-resource';
    case ISSUE_TRACKING        = 'issue-tracking';
    case MAILING_LIST          = 'mailing-list';
    case MITIGATION            = 'mitigation';
    case NOT_APPLICABLE        = 'not-applicable';
    case PATCH                 = 'patch';
    case PERMISSIONS_REQUIRED  = 'permissions-required';
    case MEDIA_COVERAGE        = 'media-coverage';
    case PRODUCT               = 'product';
    case RELATED               = 'related';
    case RELEASE_NOTES         = 'release-notes';
    case SIGNATURE             = 'signature';
    case TECHNICAL_DESCRIPTION = 'technical-description';
    case THIRD_PARTY_ADVISORY  = 'third-party-advisory';
    case VENDOR_ADVISORY       = 'vendor-advisory';
    case JED                   = 'x_jed';

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_REFERENCE_TAG_' . strtoupper(str_replace('-', '_', $this->value));
    }
}
