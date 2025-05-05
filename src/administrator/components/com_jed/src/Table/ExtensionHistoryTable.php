<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * ExtensionHistory table
 *
 * @since 4.0.0
 */
class ExtensionHistoryTable extends Table
{
    /**
     * Constructor
     *
     * @param DatabaseDriver $db A database connector object
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_jed.extensionhistory';
        parent::__construct('#__jed_extensions_history', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see Table::_getAssetName
     *
     * @since 4.0.0
     */
    protected function _getAssetName(): string
    {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array|object  $src     An associative array or object to bind to the Table instance.
     * @param   array|string  $ignore  An optional array or space separated list of properties to ignore while binding.
     *
     * @return  boolean  True on success.
     *
     * @see    Table:bind
     * @throws Exception
     * @since  4.0.0
     */
    public function bind($src, $ignore = '')
    {
        $date = Factory::getDate();
        $app  = Factory::getApplication();
        $task = $app->input->get('task');


        // Support for alias field: alias
        if (empty($src['alias'])) {
            if (empty($src['title'])) {
                $src['alias'] = OutputFilter::stringURLSafe(date('Y-m-d H:i:s'));
            } else {
                if ($app->get('unicodeslugs') == 1) {
                    $src['alias'] = OutputFilter::stringURLUnicodeSlug(trim($src['title']));
                } else {
                    $src['alias'] = OutputFilter::stringURLSafe(trim($src['title']));
                }
            }
        }


        // Support for checkbox field: published
        if (!isset($src['published'])) {
            $src['published'] = 0;
        }

        // Support for checkbox field: checked_out
        if (!isset($src['checked_out'])) {
            $src['checked_out'] = 0;
        }

        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($src['id'] == 0 && empty($src['modified_by'])) {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($src['id'] == 0) {
            $src['created_on'] = $date->toSql();
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified_on'] = $date->toSql();
        }

        // Support for checkbox field: popular
        if (!isset($src['popular'])) {
            $src['popular'] = 0;
        }

        // Support for checkbox field: requires_registration
        if (!isset($src['requires_registration'])) {
            $src['requires_registration'] = 0;
        }

        // Support for checkbox field: can_update
        if (!isset($src['can_update'])) {
            $src['can_update'] = 0;
        }

        // Support for multiple field: uses_updater
        if (isset($src['uses_updater']) && $src['uses_updater']) {
            if (is_array($src['uses_updater'])) {
                $src['uses_updater'] = implode(',', $src['uses_updater']);
            }
        } else {
            $src['uses_updater'] = '';
        }

        // Support for checkbox field: approved
        if (!isset($src['approved'])) {
            $src['approved'] = 0;
        }

        // Support for checkbox field: jed_checked
        if (!isset($src['jed_checked'])) {
            $src['jed_checked'] = 0;
        }

        // Support for checkbox field: uses_third_party
        if (!isset($src['uses_third_party'])) {
            $src['uses_third_party'] = 0;
        }

        // Support for multiple field: primary_category_id
        if (isset($src['primary_category_id']) && $src['primary_category_id']) {
            if (is_array($src['primary_category_id'])) {
                $src['primary_category_id'] = implode(',', $src['primary_category_id']);
            }
        } else {
            $src['primary_category_id'] = '';
        }

        return parent::bind($src, $ignore);
    }

    /**
     * Overloaded check function
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function check(): bool
    {
        // If there is an ordering column and this is a new row then get the next ordering value
        if (property_exists($this, 'ordering') && $this->id == 0) {
            $this->ordering = self::getNextOrder();
        }

        // Check if alias is unique
        if (!$this->isUnique('alias')) {
            $count        = 0;
            $currentAlias = $this->alias;
            while (!$this->isUnique($this->alias)) {
                $this->alias = $currentAlias . '-' . $count++;
            }
        }


        return parent::check();
    }

    /**
     * Delete a record by id
     *
     * @param mixed $pk Primary key value to delete. Optional
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function delete($pk = null): bool
    {
        $this->load($pk);
        return parent::delete($pk);
    }

    /**
     * Get the type alias for the history table
     *
     * @return string  The alias as described above
     *
     * @since 4.0.0
     */
    public function getTypeAlias(): string
    {
        return $this->typeAlias;
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * @param bool $updateNulls True to update fields even if they are null.
     *
     * @return bool  True on success.
     *
     * @since 4.0.0
     */
    public function store($updateNulls = true): bool
    {
        return parent::store($updateNulls);
    }

    /**
     * Check if a field is unique
     *
     * @param string $field Name of the field
     *
     * @return bool True if unique
     * @since  4.0.0
     */
    private function isUnique($field): bool
    {
        $db    = $this->getDbo();
        $query = $db->getQuery(true);

        $categories        = explode(',', $this->primary_category_id);
        $andWhereCondition = [];
        foreach ($categories as $categoryid) {
            $andWhereCondition[] = $db->quoteName('primary_category_id') . ' like "%' . $categoryid . '%"';
        }


        $query
            ->select($db->quoteName($field))
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName($field) . ' = ' . $db->quote($this->$field))
            ->where($db->quoteName('id') . ' <> ' . (int) $this->{$this->_tbl_key});

        if (!empty($andWhereCondition)) {
            $query->andWhere($andWhereCondition);
        }


        $db->setQuery($query);
        $db->execute();

        return ($db->getNumRows() == 0) ? true : false;
    }
}
