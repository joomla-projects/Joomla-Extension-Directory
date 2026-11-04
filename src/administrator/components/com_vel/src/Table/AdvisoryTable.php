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
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * The `#__vel_advisories` row.
 *
 * Deliberately thin, as CaseTable is. The columns that carry the process's guarantees - `state`,
 * the three auto dates, the CVE fields, `published_json` - are written only by
 * {@see \Jed\Component\Vel\Administrator\Service\VelService}; {@see \Jed\Component\Vel\Administrator\Model\AdvisoryModel::save()}
 * strips them from a form post before they reach here. What this table owns is the ordinary
 * editing: title, alias, description, classification, scores, notes.
 *
 * @since 4.0.0
 */
class AdvisoryTable extends Table
{
    /**
     * @param DatabaseDriver $db A database connector object.
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_vel.advisory';

        parent::__construct('#__vel_advisories', 'id', $db);
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
                $src['created_by'] = !empty($src['created_by']) ? $src['created_by'] : $me;
            } else {
                $src['modified']    = $now;
                $src['modified_by'] = $me;
            }

            // A nullable id column is NULL, never 0: 0 is not a user, a ticket or a case, and a
            // LEFT JOIN on it would silently match nothing while looking like it matched.
            foreach (['assigned_to', 'jed_ticket_id', 'abandonware_case_id'] as $nullable) {
                if (\array_key_exists($nullable, $src) && (int) $src[$nullable] === 0) {
                    $src[$nullable] = null;
                }
            }

            // Optional unique text: NULL, never '' (8.14). MySQL allows any number of NULLs in a
            // unique index and exactly one empty string.
            if (\array_key_exists('cve_id', $src)) {
                $cve           = strtoupper(trim((string) $src['cve_id']));
                $src['cve_id'] = $cve === '' ? null : $cve;
            }

            foreach (['cvss_vector', 'cvss_version', 'severity', 'retired_reason'] as $nullable) {
                if (\array_key_exists($nullable, $src) && trim((string) $src[$nullable]) === '') {
                    $src[$nullable] = null;
                }
            }

            if (\array_key_exists('cvss_score', $src) && ($src['cvss_score'] === '' || $src['cvss_score'] === null)) {
                $src['cvss_score'] = null;
            }

            foreach (['date_reserved', 'date_published', 'date_updated', 'date_public', 'date_reported', 'date_vendor_notified', 'date_fixed', 'date_retired'] as $date) {
                if (\array_key_exists($date, $src) && (empty($src[$date]) || str_starts_with((string) $src[$date], '0000'))) {
                    $src[$date] = null;
                }
            }
        }

        return parent::bind($src, $ignore);
    }

    /**
     * @return bool
     *
     * @since 4.0.0
     */
    public function check(): bool
    {
        $this->title = trim((string) $this->title);

        if ($this->title === '') {
            $this->setError(Text::_('COM_VEL_ERROR_TITLE_REQUIRED'));

            return false;
        }

        if ($this->cve_id !== null && !preg_match('/^CVE-\d{4}-\d{4,}$/', (string) $this->cve_id)) {
            $this->setError(Text::sprintf('COM_VEL_ERROR_CVE_ID_INVALID', (string) $this->cve_id));

            return false;
        }

        $this->alias = trim((string) $this->alias);

        if ($this->alias === '') {
            $this->alias = $this->title;
        }

        $this->alias = OutputFilter::stringURLSafe($this->alias);

        if ($this->alias === '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        // Unique, by appending a counter rather than refusing: the alias is a URL fragment and
        // two advisories about "XSS in Foo" are ordinary.
        $base    = $this->alias;
        $counter = 1;
        $db      = $this->getDatabase();

        while (true) {
            $id    = (int) $this->id;
            $alias = $this->alias;
            $clash = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName($this->_tbl))
                    ->where($db->quoteName('alias') . ' = :alias')
                    ->where($db->quoteName('id') . ' <> :id')
                    ->bind(':alias', $alias)
                    ->bind(':id', $id, ParameterType::INTEGER)
            )->loadResult();

            if ($clash === 0) {
                break;
            }

            $this->alias = $base . '-' . (++$counter);
        }

        return parent::check();
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
