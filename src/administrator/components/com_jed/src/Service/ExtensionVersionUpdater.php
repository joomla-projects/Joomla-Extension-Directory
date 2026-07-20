<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Applies an automatically-detected version bump directly to the live
 * `#__jed_extensions` row.
 *
 * This is a deliberately separate code path from
 * {@see \Jed\Component\Jed\Administrator\Model\ExtensionModel::save()}, which is the
 * manual admin-edit flow and always goes through the review-gated history/active
 * mechanic. This automated path still records an audit-trail row in
 * `#__jed_extensions_history` (inactive, `active = 0`) but writes to the live table
 * immediately, without waiting for a review.
 *
 * @since 4.1.0
 */
class ExtensionVersionUpdater
{
    /**
     * @param DatabaseInterface $db The database connector object.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Overwrite the live extension row with a newly-detected version and, as an
     * audit trail, record the pre-update state as a new (inactive) history row.
     *
     * @param int         $extensionId    The extension id.
     * @param string      $newVersion     The newly-detected version string.
     * @param string|null $newDownloadUrl The newly-detected download URL, if any.
     *
     * @return int The id of the new `#__jed_extensions_history` audit-trail row.
     *
     * @throws RuntimeException If the extension does not exist.
     *
     * @since 4.1.0
     */
    public function applyUpdate(int $extensionId, string $newVersion, ?string $newDownloadUrl = null): int
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $extensionId, ParameterType::INTEGER);
        $live = $db->setQuery($query)->loadObject();

        if ($live === null) {
            throw new RuntimeException(\sprintf('Extension #%d not found.', $extensionId));
        }

        $now = Factory::getDate('now')->toSql();

        // Audit-trail row: the extension's state right before this automated overwrite.
        // #__jed_extensions_history has no entry_version / last_update_check(_error) columns.
        $historyRow = clone $live;
        unset(
            $historyRow->id,
            $historyRow->entry_version,
            $historyRow->last_update_check,
            $historyRow->last_update_check_error
        );
        $historyRow->extension_id      = $extensionId;
        $historyRow->active            = 0;
        $historyRow->extension_version = $newVersion;
        $historyRow->modified          = $now;

        if ($newDownloadUrl !== null) {
            $historyRow->download_url = $newDownloadUrl;
        }

        $db->insertObject('#__jed_extensions_history', $historyRow);
        $historyId = (int) $db->insertid();

        // Direct overwrite of the live row.
        $live->extension_version = $newVersion;
        $live->modified           = $now;
        $live->entry_version       = $historyId;

        if ($newDownloadUrl !== null) {
            $live->download_url = $newDownloadUrl;
        }

        $db->updateObject('#__jed_extensions', $live, 'id');

        return $historyId;
    }
}
