<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * The `#__jed_abandonware_cases` row.
 *
 * Deliberately thin. Everything that moves a case through the process lives in
 * {@see \Jed\Component\Abandonware\Administrator\Service\CaseService}, and this table exists for
 * the parts that are ordinary editing - the notes, the assignee, the subject tuple on an unlisted
 * case. The two columns that carry the process's guarantees, `status` and `contact_time`, are
 * stripped in {@see CaseModel::save()} before they ever reach here.
 *
 * @since 4.0.0
 */
class CaseTable extends Table
{
    /**
     * @param DatabaseDriver $db A database connector object.
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_abandonware.case';

        parent::__construct('#__jed_abandonware_cases', 'id', $db);
    }

    /**
     * @return string  The asset name.
     *
     * @since 4.0.0
     */
    protected function _getAssetName(): string
    {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * @param array|object $src    Data to bind.
     * @param array|string $ignore Properties to skip.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function bind($src, $ignore = '')
    {
        $now = Factory::getDate()->toSql();
        $me  = (int) Factory::getApplication()->getIdentity()->id;

        if (\is_array($src)) {
            if (empty($src['id'])) {
                $src['created']    = $src['created'] ?? $now;
                $src['created_by'] = $src['created_by'] ?? $me;
            } else {
                $src['modified']    = $now;
                $src['modified_by'] = $me;
            }

            // An unassigned case is NULL, never 0. The column is a user id and 0 is not one; a
            // LEFT JOIN on it would silently match nothing while looking like it matched something.
            if (isset($src['assigned_to']) && (int) $src['assigned_to'] === 0) {
                $src['assigned_to'] = null;
            }

            // Optional unique-ish text field: NULL, never ''. The invariant from 8.14 - MySQL
            // allows any number of NULLs in a unique index but only one empty string, so the
            // second case saved with a blank key would fail to store.
            if (isset($src['identity_key']) && trim((string) $src['identity_key']) === '') {
                $src['identity_key'] = null;
            }
        }

        return parent::bind($src, $ignore);
    }

    /**
     * @return string  The alias for the history table.
     *
     * @since 4.0.0
     */
    public function getTypeAlias(): string
    {
        return $this->typeAlias;
    }
}
