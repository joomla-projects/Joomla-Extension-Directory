<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * The `#__vel_reports` row.
 *
 * Everything identifying a reporter lives in this table and in `#__vel_participants`, nowhere
 * else. The public views never read it.
 *
 * @since 4.0.0
 */
class ReportTable extends Table
{
    /**
     * @param DatabaseDriver $db A database connector object.
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_vel.report';

        parent::__construct('#__vel_reports', 'id', $db);
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

            foreach (['advisory_id', 'extension_id', 'legacy_form_id', 'legacy_submission_id'] as $nullable) {
                if (\array_key_exists($nullable, $src) && (int) $src[$nullable] === 0) {
                    $src[$nullable] = null;
                }
            }

            foreach (['consent_time', 'verified_time'] as $date) {
                if (\array_key_exists($date, $src) && (empty($src[$date]) || str_starts_with((string) $src[$date], '0000'))) {
                    $src[$date] = null;
                }
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
