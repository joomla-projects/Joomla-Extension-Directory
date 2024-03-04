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
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Table as Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use Jed\Component\Jed\Administrator\Helper\JedHelper;

/**
 * Review table
 *
 * @since 4.0.0
 */
class ReviewTable extends Table
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
        $this->typeAlias = 'com_jed.review';
        parent::__construct('#__jed_reviews', 'id', $db);
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
        $task = Factory::getApplication()->input->get('task');


        // Support for multiple or not foreign key field: extension_id
        if (!empty($src['extension_id'])) {
            if (is_array($src['extension_id'])) {
                $src['extension_id'] = implode(',', $src['extension_id']);
            } elseif (strrpos($src['extension_id'], ',') != false) {
                $src['extension_id'] = explode(',', $src['extension_id']);
            }
        } else {
            $src['extension_id'] = 0;
        }

        // Support for multiple or not foreign key field: supply_option_id
        if (!empty($src['supply_option_id'])) {
            if (is_array($src['supply_option_id'])) {
                $src['supply_option_id'] = implode(',', $src['supply_option_id']);
            } elseif (strrpos($src['supply_option_id'], ',') != false) {
                $src['supply_option_id'] = explode(',', $src['supply_option_id']);
            }
        } else {
            $src['supply_option_id'] = 0;
        }

        // Support for alias field: alias
        if (empty($src['alias'])) {
            if (empty($src['title'])) {
                $src['alias'] = OutputFilter::stringURLSafe(date('Y-m-d H:i:s'));
            } else {
                if (Factory::getConfig()->get('unicodeslugs') == 1) {
                    $src['alias'] = OutputFilter::stringURLUnicodeSlug(trim($src['title']));
                } else {
                    $src['alias'] = OutputFilter::stringURLSafe(trim($src['title']));
                }
            }
        }


        // Support for checkbox field: flagged
        if (!isset($src['flagged'])) {
            $src['flagged'] = 0;
        }

        // Support for checkbox field: published
        if (!isset($src['published'])) {
            $src['published'] = 0;
        }

        if ($src['id'] == 0) {
            $src['created_on'] = $date->toSql();
        }

        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if (isset($src['params']) && is_array($src['params'])) {
            $registry = new Registry();
            $registry->loadArray($src['params']);
            $src['params'] = (string) $registry;
        }

        if (isset($src['metadata']) && is_array($src['metadata'])) {
            $registry = new Registry();
            $registry->loadArray($src['metadata']);
            $src['metadata'] = (string) $registry;
        }

        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_jed.review.' . $src['id'])) {
            $actions         = Access::getActionsFromFile(
                JPATH_ADMINISTRATOR . '/components/com_jed/access.xml',
                "/access/section[@name='review']/"
            );
            $default_actions = Access::getAssetRules('com_jed.review.' . $src['id'])->getData();
            $src_jaccess     = [];

            foreach ($actions as $action) {
                if (key_exists($action->name, $default_actions)) {
                    $src_jaccess[$action->name] = $default_actions[$action->name];
                }
            }

            $src['rules'] = $this->JAccessRulestoArray($src_jaccess);
        }

        // Bind the rules for ACL where supported.
        if (isset($src['rules']) && is_array($src['rules'])) {
            $this->setRules($src['rules']);
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
            while (!$this->isUnique('alias')) {
                $this->alias = $currentAlias . '-' . $count++;
            }
        }


        return parent::check();
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
     * Check if a field is unique
     *
     * @param string $field Name of the field
     *
     * @return bool True if unique
     *
     * @since 4.0.0
     */
    private function isUnique(string $field): bool
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true);

        $query
            ->select($db->quoteName($field))
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName($field) . ' = ' . $db->quote($this->$field))
            ->where($db->quoteName('id') . ' <> ' . (int) $this->{$this->_tbl_key});

        $db->setQuery($query);
        $db->execute();

        return $db->getNumRows() == 0;
    }
}
