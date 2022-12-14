<?php
/**
 * @package        JED
 *
 * @copyright  (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Table;
// No direct access
defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table as Table;
use Joomla\Database\DatabaseDriver;
use Jed\Component\Jed\Administrator\Helper\JedHelper;

/**
 * Extensionsupplyoption table
 *
 * @since 4.0.0
 */
class ExtensionsupplyoptionTable extends Table
{

	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  A database connector object
	 *
	 * @since 4.0.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->typeAlias = 'com_jed.extensionsupplyoption';
		parent::__construct('#__jed_extension_supply_options', 'id', $db);
		$this->setColumnAlias('published', 'state');

	}

	/**
	 * Define a namespaced asset name for inclusion in the #__assets table
	 *
	 * @return string The asset name
	 *
	 * @see   Table::_getAssetName
	 *
	 * @since 4.0.0
	 */
	protected function _getAssetName(): string
	{
		$k = $this->_tbl_key;

		return $this->typeAlias . '.' . (int) $this->$k;
	}

	/**
	 * Returns the parent asset's id. If you have a tree structure, retrieve the parent's id using the external key field
	 *
	 * @param   Table|null    $table  Table name
	 * @param   integer|null  $id     Id
	 *
	 * @return mixed The id on success, false on failure.
	 *
	 * @see   Table::_getAssetParentId
	 *
	 * @since 4.0.0
	 */
	protected function _getAssetParentId(Table $table = null, $id = null)
	{
		// We will retrieve the parent-asset from the Asset-table
		$assetParent = Table::getInstance('Asset');

		// Default: if no asset-parent can be found we take the global asset
		$assetParentId = $assetParent->getRootId();

		// The item has the component as asset-parent
		$assetParent->loadByName('com_jed');

		// Return the found asset-parent-id
		if ($assetParent->id)
		{
			$assetParentId = $assetParent->id;
		}

		return $assetParentId;
	}

	/**
	 * Overloaded bind function to pre-process the params.
	 *
	 * @param   array  $src     Named array
	 * @param   mixed  $ignore  Optional array or list of parameters to ignore
	 *
	 * @return  null|string  null is operation was satisfactory, otherwise returns an error
	 *
	 * @see     Table:bind
	 * @throws Exception
	 * @since   4.0.0
	 */
	public function bind($src, $ignore = ''): ?string
	{
		$task = Factory::getApplication()->input->get('task');


		if ($src['id'] == 0 && empty($src['created_by']))
		{
			$src['created_by'] = JedHelper::getUser()->id;
		}

		if ($src['id'] == 0 && empty($src['modified_by']))
		{
			$src['modified_by'] = JedHelper::getUser()->id;
		}

		if ($task == 'apply' || $task == 'save')
		{
			$src['modified_by'] = JedHelper::getUser()->id;
		}


		if (!JedHelper::getUser()->authorise('core.admin', 'com_jed.extensionsupplyoption.' . $src['id']))
		{
			$actions         = Access::getActionsFromFile(
				JPATH_ADMINISTRATOR . '/components/com_jed/access.xml',
				"/access/section[@name='extensionsupplyoption']/"
			);
			$default_actions = Access::getAssetRules('com_jed.extensionsupplyoption.' . $src['id'])->getData();
			$array_jaccess   = array();

			foreach ($actions as $action)
			{
				if (key_exists($action->name, $default_actions))
				{
					$array_jaccess[$action->name] = $default_actions[$action->name];
				}
			}

			$src['rules'] = $this->JAccessRulestoArray($array_jaccess);
		}

		// Bind the rules for ACL where supported.
		if (isset($src['rules']) && is_array($src['rules']))
		{
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
		if (property_exists($this, 'ordering') && $this->id == 0)
		{
			$this->ordering = self::getNextOrder();
		}


		return parent::check();
	}

	/**
	 * Delete a record by id
	 *
	 * @param   mixed  $pk  Primary key value to delete. Optional
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
	 * Method to store a row in the database from the Table instance properties.
	 *
	 * If a primary key value is set the row with that primary key value will be updated with the instance property values.
	 * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   4.0.0
	 */
	public function store($updateNulls = true): bool
	{
		return parent::store($updateNulls);
	}

	/**
	 * This function convert an array of Access objects into an rules array.
	 *
	 * @param   array  $jaccessrules  An array of Access objects.
	 *
	 * @return  array
	 *
	 * @since 4.0.0
	 */
	private function JAccessRulestoArray(array $jaccessrules): array
	{
		$rules = array();

		foreach ($jaccessrules as $action => $jaccess)
		{
			$actions = array();

			if ($jaccess)
			{
				foreach ($jaccess->getData() as $group => $allow)
				{
					$actions[$group] = ((bool) $allow);
				}
			}

			$rules[$action] = $actions;
		}

		return $rules;
	}

	/**
	 * Get the type alias for the history table
	 *
	 * @return  string  The alias as described above
	 *
	 * @since   4.0.0
	 */
	public function getTypeAlias(): string
	{
		return $this->typeAlias;
	}
}
