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
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table as Table;
use Joomla\Database\DatabaseDriver;

/**
 * Ticketinternalnote table
 *
 * @since 4.0.0
 */
class TicketinternalnoteTable extends Table
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
        $this->typeAlias = 'com_jed.ticketinternalnote';
        parent::__construct('#__jed_ticket_internal_notes', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * This function convert an array of Access objects into an rules array.
     *
     * @param array $jaccessrules An array of Access objects.
     *
     * @return array
     * @since  4.0.0
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
     * @since  4.0.0
     * @throws Exception
     */
    public function bind($src, $ignore = '')
    {
        $date = Factory::getDate();

        $input = Factory::getApplication()->input;
        $task  = $input->getString('task', '');

        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($src['id'] == 0 && empty($src['modified_by'])) {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }

        // Support for multiple or not foreign key field: ticket_id
        if (!empty($src['ticket_id'])) {
            if (is_array($src['ticket_id'])) {
                $src['ticket_id'] = implode(',', $src['ticket_id']);
            } elseif (strrpos($src['ticket_id'], ',')) {
                $src['ticket_id'] = explode(',', $src['ticket_id']);
            }
        } else {
            $src['ticket_id'] = 0;
        }

        if ($src['id'] == 0) {
            $src['created_on'] = $date->toSql();
        }
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_jed.ticketinternalnote.' . $src['id'])) {
            $actions         = Access::getActionsFromFile(
                JPATH_ADMINISTRATOR . '/components/com_jed/access.xml',
                "/access/section[@name='ticketinternalnote']/"
            );
            $default_actions = Access::getAssetRules('com_jed.ticketinternalnote.' . $src['id'])->getData();
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
