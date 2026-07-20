<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * Extension Form model.
 *
 * Editing only: this form never creates a new #__jed_extensions row. It always loads the most
 * recent #__jed_extensions_history entry for the extension_id given in the URL, and only lets the
 * extension's owner, one of its maintainers, or an admin/superuser view or save it.
 *
 * @since 4.0.0
 */
class ExtensionformModel extends FormModel
{
    use ExtensionUtilities;

    /**
     * The item object
     *
     * @var   mixed
     * @since 4.0.0
     */
    private mixed $item = null;

    /**
     * Data Table
     *
     * @var   string
     * @since 4.0.0
     **/
    private string $dbtable = "#__jed_extensions";

    /**
     * Method to delete data
     *
     * @param int $id Item primary key
     *
     * @return int  The id of the deleted item
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function delete(int $id): int
    {
        if (!$id || !$this->isAuthorised($id)) {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }

        $table = $this->getTable();

        if ($table->delete($id) !== true) {
            throw new Exception(Text::_('JERROR_FAILED'), 501);
        }

        return $id;
    }

    /**
     * Check if data can be saved
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getCanSave(): bool
    {
        $table = $this->getTable();

        return $table !== false;
    }

    /**
     * Get Developer Name from jed_developers table
     *
     * @since 4.0.0
     */
    public function getDeveloperName(int $uid): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.developer_name')
            ->from($db->quoteName('#__jed_developers', 'a'))
            ->where('a.user_id = :uid')
            ->bind(':uid', $uid, ParameterType::INTEGER);

        return $db->setQuery($query)->loadResult();
    }

    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form    A Form object on success, false on failure
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form
    {
        // Get the form.
        $form = $this->loadForm(
            'com_jed.extension',
            'extensionform',
            [
                        'control'   => $formname,
                        'load_data' => $loadData,
                ]
        );

        if (! is_object($form)) {
            throw new Exception(Text::_('JERROR_LOADFILE_FAILED'), 500);
        }

        return $form;
    }

    /**
     * Method to get the table
     *
     * @param string $name    Name of the Table class
     * @param string $prefix  Optional prefix for the table class name
     * @param array  $options Optional configuration array for Table object
     *
     * @return Table|bool Table if found, bool false on failure
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'ExtensionHistory', $prefix = 'Administrator', $options = []): Table|bool
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed  The default data is an empty array.
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_jed.edit.extension.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data ?: [];
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    protected function populateState(): void
    {
        /* @var $app SiteApplication */
        $app   = Factory::getApplication();
        $input = $app->getInput();

        // Load state from the request userState on edit or from the passed variable on default
        if (!$app->getUserState('com_jed.edit.extension.id')) {
            $id = $input->get('id', $app->getUserState('com_jed.edit.extension.id'));
            $app->setUserState('com_jed.edit.extension.id', $id);
        } else {
            $id = $app->getUserState('com_jed.edit.extension.id');
        }
        $this->setState('extension.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('extension.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to check in an item.
     *
     * @param int $pk The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function checkin($pk = null): bool
    {
        // Get the id.
        $pk = (! empty($pk)) ? $pk : (int) $this->getState('extension.id');

        if ($pk) {
            // Initialise the table
            $table = $this->getTable();

            // Attempt to check the row in.
            if (method_exists($table, 'checkin')) {
                if (! $table->checkin($pk)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Method to check out an item for editing.
     *
     * @param int $pk The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function checkout($pk = null): bool
    {
        // Get the id.
        $pk = (! empty($pk)) ? $pk : (int) $this->getState('extension.id');

        if (! $pk || $this->isAuthorised($pk)) {
            if ($pk) {
                // Initialise the table
                $table = $this->getTable();

                // Get the current user object.
                $user = Factory::getApplication()->getIdentity();

                // Attempt to check the row out.
                if (method_exists($table, 'checkout')) {
                    if (! $table->checkout($user->id, $pk)) {
                        return false;
                    }
                }
            }

            return true;
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }

    /**
     * Method to get an object.
     *
     * Only ever loads an EXISTING extension: the most recent (highest id) row in
     * #__jed_extensions_history for the given extension_id. There is no "create new" path here -
     * new extensions are created elsewhere (e.g. the admin backend).
     *
     * @param int|null $id The extension_id (the URL "id") of the extension to load.
     *
     * @return stdClass|bool Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem(int $id = null): stdClass|bool
    {
        if ($this->item !== null) {
            return $this->item;
        }

        $this->item = false;

        $extensionId = (!empty($id)) ? $id : (int) $this->getState('extension.id');

        if (!$extensionId) {
            throw new Exception(Text::_('COM_JED_ITEM_DOESNT_EXIST'), 404);
        }

        $db = $this->getDatabase();

        // Load the most recent history entry (highest id) for this extension.
        $latestQuery = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('id') . ')')
            ->from($db->quoteName('#__jed_extensions_history'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->bind(':eid', $extensionId, ParameterType::INTEGER);
        $latestId = (int) $db->setQuery($latestQuery)->loadResult();

        $table = $this->getTable();

        if (!$latestId || $table === false || !$table->load($latestId)) {
            throw new Exception(Text::_('COM_JED_ITEM_DOESNT_EXIST'), 404);
        }

        if (!$this->isAuthorised($extensionId)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 401);
        }

        // Convert the Table to a clean stdClass.
        $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

        // Pre-fill the "categories" multi-select with the extension's existing categories.
        $catQuery = $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__jed_extensions_category_map'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->bind(':eid', $extensionId, ParameterType::INTEGER);
        $this->item->categories = $db->setQuery($catQuery)->loadColumn() ?: [];

        // Pre-fill the "maintainer" subform with the extension's existing maintainers.
        $maintainerQuery = $db->getQuery(true)
            ->select($db->quoteName('user_id'))
            ->from($db->quoteName('#__jed_extensions_maintainers'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->bind(':eid', $extensionId, ParameterType::INTEGER);
        $this->item->maintainer = array_map(
            static fn ($userId) => ['user_id' => (int) $userId],
            $db->setQuery($maintainerQuery)->loadColumn() ?: []
        );

        return $this->item;
    }

    /**
     * Load all images for an extension from #__jed_extensions_images, ordered by ordering.
     *
     * @param int $extensionId The extension id to load images for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getImages(?int $extensionId = null): array
    {
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState('extension.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_extensions_images'))
            ->where($db->quoteName('extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Load all uploaded files for an extension from #__jed_extensions_files.
     *
     * @param int $extensionId The extension id to load files for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getFiles(?int $extensionId = null): array
    {
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState('extension.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_extensions_files'))
            ->where($db->quoteName('extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('id') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Load the selected categories for an extension from #__jed_extensions_category_map.
     *
     * @param int $extensionId The extension id to load categories for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getCategories(?int $extensionId = null): array
    {
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState('extension.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['map.catid', 'c.title']))
            ->from($db->quoteName('#__jed_extensions_category_map', 'map'))
            ->leftJoin(
                $db->quoteName('#__categories', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('map.catid')
            )
            ->where($db->quoteName('map.extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('c.title') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Load the maintainers for an extension from #__jed_extensions_maintainers.
     *
     * @param int $extensionId The extension id to load maintainers for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getMaintainers(?int $extensionId = null): array
    {
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState('extension.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['m.user_id', 'u.name', 'u.username']))
            ->from($db->quoteName('#__jed_extensions_maintainers', 'm'))
            ->leftJoin(
                $db->quoteName('#__users', 'u')
                . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('m.user_id')
            )
            ->where($db->quoteName('m.extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('u.name') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Whether the current user may view/edit the given extension: its owner, one of its
     * maintainers, or an admin/superuser.
     *
     * @param int $extensionId The extension PK in #__jed_extensions.
     *
     * @return bool
     *
     * @since 4.0.0
     * @throws Exception
     */
    private function isAuthorised(int $extensionId): bool
    {
        if (JedHelper::isAdminOrSuperUser()) {
            return true;
        }

        $userId = (int) Factory::getApplication()->getIdentity()->id;

        if (!$userId) {
            return false;
        }

        $db = $this->getDatabase();

        $ownerQuery = $db->getQuery(true)
            ->select($db->quoteName('owner'))
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = :eid')
            ->bind(':eid', $extensionId, ParameterType::INTEGER);

        if ((int) $db->setQuery($ownerQuery)->loadResult() === $userId) {
            return true;
        }

        $maintainerQuery = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__jed_extensions_maintainers'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->where($db->quoteName('user_id') . ' = :uid')
            ->bind(':eid', $extensionId, ParameterType::INTEGER)
            ->bind(':uid', $userId, ParameterType::INTEGER);

        return (bool) $db->setQuery($maintainerQuery)->loadResult();
    }

    /**
     * Method to save the form data.
     *
     * Always updates an existing extension: inserts a new #__jed_extensions_history row (the
     * previous one is deactivated, matching the admin backend's ExtensionModel::save()), syncs
     * categories/maintainers/images/files, and points #__jed_extensions.entry_version at the new
     * history entry. There is no "create new extension" path.
     *
     * @param array $data The form data
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function save(array $data): bool
    {
        $app         = Factory::getApplication();
        $id          = $app->getUserState('com_jed.edit.extension.id');
        $extensionId = (int) ($data['id'] ?? $this->getState('extension.id', $id));
        $extensionId = $id;

        if (!$extensionId) {
            throw new Exception(Text::_('COM_JED_EXTENSION_ID_MISSING'), 400);
        }

        if (!$this->isAuthorised($extensionId)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 401);
        }

        $categories = (array) ($data['categories'] ?? []);

        // Force a new INSERT rather than an UPDATE of an existing history entry.
        $data['id']           = 0;
        $data['extension_id'] = $extensionId;
        $data['active']       = 1;
        unset($data['created']); // ExtensionHistoryTable::bind() sets this for new rows

        // Only one history entry may be active per extension; deactivate the rest
        // before inserting the new (active) one.
        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__jed_extensions_history'))
                ->set($db->quoteName('active') . ' = 0')
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->execute();

        $table = $this->getTable();

        if (!$table->bind($data)) {
            $this->setError($table->getError());

            return false;
        }

        if (!$table->check()) {
            $this->setError($table->getError());

            return false;
        }

        if (!$table->store()) {
            $this->setError($table->getError());

            return false;
        }

        // Point the live row at the history entry that was just created.
        $this->updateEntryVersion($extensionId, (int) $table->id);

        // deleteImages/deleteFiles are plain checkboxes, not declared form fields, so they were
        // stripped by Form::filter() in FormModel::validate() before $data reached us here.
        // Read them straight from the raw request instead.
        $rawPost = (array) Factory::getApplication()->getInput()->post->get('jform', [], 'array');

        $this->storeCategories($extensionId, $categories);
        $this->storeMaintainers($extensionId, (array) ($data['maintainer'] ?? []));
        $this->deleteMarkedUploads($extensionId, (array) ($rawPost['deleteImages'] ?? []), '#__jed_extensions_images');
        $this->deleteMarkedUploads($extensionId, (array) ($rawPost['deleteFiles'] ?? []), '#__jed_extensions_files');
        $this->storeUploadedImages($extensionId, (array) ($data['images'] ?? []));
        $this->storeUploadedFiles($extensionId, (array) ($data['files'] ?? []));

        // Keep state pointing to the extension ID (not the new history entry's PK)
        $this->setState('extension.id', $extensionId);

        return true;
    }
}
