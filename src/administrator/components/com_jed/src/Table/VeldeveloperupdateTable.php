<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table as Table;
use Joomla\Database\DatabaseDriver;

/**
 * Veldeveloperupdate table
 *
 * @since 4.0.0
 */
class VeldeveloperupdateTable extends Table
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
        $this->typeAlias = 'com_jed.veldeveloperupdate';
        parent::__construct('#__jed_vel_developer_update', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * This function convert an array of Access objects into an rules array.
     *
     * @param array $jaccessrules An array of Access objects.
     *
     * @return array
     *
     * @since 4.0.0
     */
    private function JAccessRulestoArray(array $jaccessrules): array
    {
        $rules = [];

        foreach ($jaccessrules as $action => $jaccess) {
            $actions = [];

            if ($jaccess) {
                foreach ($jaccess->getData() as $group => $allow) {
                    $actions[$group] = ((bool) $allow);
                }
            }

            $rules[$action] = $actions;
        }

        return $rules;
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see   Table::_getAssetName
     * @since 4.0.0
     */
    protected function _getAssetName(): string
    {
        $k = $this->_tbl_key;

        return 'com_jed.veldeveloperupdate.' . (int) $this->$k;
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
     * @since  4.0.0
     * @throws Exception
     */
    public function bind($src, $ignore = '')
    {
        $date = Factory::getDate();

        $src['update_date_submitted'] = $date->toSQL();

        // Support for multiple field: consent_to_process
        if (isset($src['consent_to_process'])) {
            if (is_array($src['consent_to_process'])) {
                $src['consent_to_process'] = implode(',', $src['consent_to_process']);
            } elseif (strpos($src['consent_to_process'], ',')) {
                $src['consent_to_process'] = explode(',', $src['consent_to_process']);
            } elseif (strlen($src['consent_to_process']) == 0) {
                $src['consent_to_process'] = '';
            }
        } else {
            $src['consent_to_process'] = '';
        }

        // Support for multiple field: update_data_source
        if (isset($src['update_data_source'])) {
            if (is_array($src['update_data_source'])) {
                $src['update_data_source'] = implode(',', $src['update_data_source']);
            } elseif (strpos($src['update_data_source'], ',')) {
                $src['update_data_source'] = explode(',', $src['update_data_source']);
            } elseif (strlen($src['update_data_source']) == 0) {
                $src['update_data_source'] = '';
            }
        } else {
            $src['update_data_source'] = '';
        }

        // Support for empty date field: update_date_submitted
        if ($src['update_date_submitted'] == '0000-00-00') {
            $src['update_date_submitted'] = '';
        }

        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($src['id'] == 0 && empty($src['modified_by'])) {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }
        $input = Factory::getApplication()->input;
        $task  = $input->getString('task', '');
        if ($task == 'apply' || $task == 'save') {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($src['id'] == 0) {
            $src['created'] = $date->toSql();
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified'] = $date->toSql();
        }


        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_jed.veldeveloperupdate.' . $src['id'])) {
            $actions         = Access::getActionsFromFile(
                JPATH_ADMINISTRATOR . '/components/com_jed/access.xml',
                "/access/section[@name='veldeveloperupdate']/"
            );
            $default_actions = Access::getAssetRules('com_jed.veldeveloperupdate.' . $src['id'])->getData();
            $array_jaccess   = [];

            foreach ($actions as $action) {
                if (key_exists($action->name, $default_actions)) {
                    $array_jaccess[$action->name] = $default_actions[$action->name];
                }
            }

            $src['rules'] = $this->JAccessRulestoArray($array_jaccess);
        }

        // Bind the rules for ACL where supported.
        if (isset($src['rules']) && is_array($src['rules'])) {
            $this->setRules($src['rules']);
        }

        return parent::bind($src, $ignore);
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
