<?php

/**
 * @package JED
 *
 * @subpackage Tickets
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
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Messagetemplate table
 *
 * @since 4.0.0
 */
class MessagetemplateTable extends Table
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
        $this->typeAlias = 'com_jed.messagetemplate';
        parent::__construct('#__jed_message_templates', 'id', $db);
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
     * @see    Table::bind
     * @since  4.0.0
     * @throws Exception
     */
    public function bind($src, $ignore = '')
    {
        $date = Factory::getDate();
        $task = Factory::getApplication()->input->get('task');


        // Support for multiple field: email_type
        if (isset($src['email_type'])) {
            if (is_array($src['email_type'])) {
                $src['email_type'] = implode(',', $src['email_type']);
            } elseif (strpos($src['email_type'], ',') != false) {
                $src['email_type'] = explode(',', $src['email_type']);
            } elseif (strlen($src['email_type']) == 0) {
                $src['email_type'] = '';
            }
        } else {
            $src['email_type'] = '';
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
            $src['created'] = $date->toSql();
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified'] = $date->toSql();
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
}
