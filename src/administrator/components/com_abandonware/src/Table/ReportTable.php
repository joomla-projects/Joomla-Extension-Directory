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
 * The `#__jed_abandonware_reports` row.
 *
 * @since 4.1.0
 */
class ReportTable extends Table
{
    /**
     * @param DatabaseDriver $db A database connector object.
     *
     * @since 4.1.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_abandonware.report';

        parent::__construct('#__jed_abandonware_reports', 'id', $db);
    }

    /**
     * @return string  The asset name.
     *
     * @since 4.1.0
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
     * @since 4.1.0
     */
    public function bind($src, $ignore = '')
    {
        if (\is_array($src)) {
            $now = Factory::getDate()->toSql();

            if (empty($src['id'])) {
                $src['created'] = $src['created'] ?? $now;
            } else {
                $src['modified']    = $now;
                $src['modified_by'] = (int) Factory::getApplication()->getIdentity()->id;
            }

            // Both halves of the legacy key are NULL together or set together. A row with one of
            // them would collide with every other half-filled row on the unique index.
            if (empty($src['legacy_form_id']) || empty($src['legacy_submission_id'])) {
                $src['legacy_form_id']       = null;
                $src['legacy_submission_id'] = null;
            }
        }

        return parent::bind($src, $ignore);
    }

    /**
     * @return string  The alias for the history table.
     *
     * @since 4.1.0
     */
    public function getTypeAlias(): string
    {
        return $this->typeAlias;
    }
}
