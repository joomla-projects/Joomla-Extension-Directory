<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use InvalidArgumentException;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Administrator\Table\ExtensionHistoryTable;
use Jed\Component\Jed\Administrator\Table\ExtensionTable;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Users\Administrator\Table\NoteTable;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Michelf\Markdown;
use RuntimeException;
use stdClass;

use function defined;

/**
 * Extension model.
 *
 * @since 4.0.0
 */
class ExtensionModel extends AdminModel
{
    use ExtensionUtilities;

    /**
     * @var string  Alias to manage history control
     *
     * @since 4.0.0
     */
    public $typeAlias = 'com_jed.extension';

    /**
     * @var string  The prefix to use with controller messages.
     *
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_JED';

    /**
     * @var stdClass  Item data
     *
     * @since 4.0.0
     */
    protected mixed $item;

    public function __construct(
        $config = [],
        ?MVCFactoryInterface $factory = null,
        ?FormFactoryInterface $formFactory = null
    ) {
        parent::__construct($config, $factory, $formFactory);
        $this->setUseExceptions(true);
    }

    protected function populateState()
    {
        parent::populateState();

        // Get the version ID of the record from the request.
        $version = Factory::getApplication()->getInput()->getInt('version');
        $this->setState($this->getName() . '.version', $version);
    }

    public function getItem($pk = null, $version = null)
    {
        $pk      = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');
        $version = (!empty($version)) ? $version : (int) $this->getState($this->getName() . '.version');
        $table   = $this->getTable('ExtensionHistory');

        if ($pk > 0) {
            // Attempt to load the row.
            if ($version) {
                $return = $table->load(['extension_id' => $pk, 'id' => $version]);
            } else {
                // Load the most recent history entry (highest id) for this extension.
                $db          = $this->getDatabase();
                $latestQuery = $db->getQuery(true)
                    ->select('MAX(' . $db->quoteName('id') . ')')
                    ->from($db->quoteName('#__jed_extensions_history'))
                    ->where($db->quoteName('extension_id') . ' = :eid')
                    ->bind(':eid', $pk, ParameterType::INTEGER);
                $latestId = (int) $db->setQuery($latestQuery)->loadResult();

                $return = $latestId > 0 ? $table->load($latestId) : false;
            }

            // Check for a table object error.
            if ($return === false) {
                // If there was no underlying error, then the false means there simply was not a row in the db for this $pk.
                throw new Exception(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));
            }
        }

        // Convert to \stdClass before adding other data
        $properties = get_object_vars($table);
        $item       = ArrayHelper::toObject($properties);

        if (property_exists($item, 'params')) {
            $registry     = new Registry($item->params);
            $item->params = $registry->toArray();
        }

        $db               = $this->getDatabase();
        $mapId            = $item->extension_id ?: (int) $item->id;
        $catQuery         = $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__jed_extensions_category_map'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->bind(':eid', $mapId, ParameterType::INTEGER);
        $item->categories = $db->setQuery($catQuery)->loadColumn() ?: [];

        // Pre-fill the "maintainer" subform with the extension's existing maintainers.
        $maintainerQuery = $db->getQuery(true)
            ->select($db->quoteName('user_id'))
            ->from($db->quoteName('#__jed_extensions_maintainers'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->bind(':eid', $mapId, ParameterType::INTEGER);
        $item->maintainer = array_map(
            static fn ($userId) => ['user_id' => (int) $userId],
            $db->setQuery($maintainerQuery)->loadColumn() ?: []
        );

        return $item;
    }

    /**
     * Method to get the record form.
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form|bool  A \JForm object on success, false on failure
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form|bool
    {
        // Get the form.
        $form = $this->loadForm('com_jed.extension.' . $formname, 'extension', ['control' => $formname, 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
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
        $extensionId      = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

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
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

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
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

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
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

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
     * Load all history entries for an extension from #__jed_extensions_history.
     *
     * @param int $extensionId The extension id to load history for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getHistory(?int $extensionId = null): array
    {
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('h') . '.*')
            ->select($db->quoteName('u.name', 'editor_name'))
            ->from($db->quoteName('#__jed_extensions_history', 'h'))
            ->leftJoin(
                $db->quoteName('#__users', 'u')
                . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('h.modified_by')
            )
            ->where($db->quoteName('h.extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('h.id') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Set one history entry as active and deactivate all others for the extension.
     *
     * @param int $extensionId The extension PK in #__jed_extensions.
     * @param int $historyId   The history entry PK to activate.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function activateVersion(int $extensionId, int $historyId): void
    {
        $db = $this->getDatabase();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__jed_extensions_history'))
                ->set($db->quoteName('active') . ' = 0')
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__jed_extensions_history'))
                ->set($db->quoteName('active') . ' = 1')
                ->where($db->quoteName('id') . ' = :id')
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->bind(':id', $historyId, ParameterType::INTEGER)
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Load all review entries for an extension from #__jed_reviews.
     *
     * @param int $extensionId The extension id to load reviews for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getReviews(?int $extensionId = null): array
    {
        $extensionId      = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_reviews'))
            ->where($db->quoteName('extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('id') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param string $name    The table type to instantiate
     * @param string $prefix  A prefix for the table class name. Optional.
     * @param array  $options Configuration array for model. Optional.
     *
     * @return Table    A database object
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'ExtensionHistory', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed  The data for the form.
     *
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData(): mixed
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jed.edit.extension.data', []);

        if (empty($data)) {
            return $this->getItem();
        }

        return $data;
    }

    /**
     * Method to save the form data.
     *
     * @param array $data The form data.
     *
     * @return bool  True on success, False on error.
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function save($data): bool
    {
        // The extension id is tracked explicitly in the session by
        // ExtensionController::edit()/add() - the same pattern the site side's
        // ExtensionformController/ExtensionformModel already use. Model::getState() alone isn't
        // reliable here because getTable() intentionally returns the history table rather than
        // the live #__jed_extensions table, so the framework's generic id bookkeeping doesn't apply.
        $extensionId = (int) Factory::getApplication()->getUserState('com_jed.edit.extension.id');

        if (!$extensionId) {
            // No live row yet: create one first, so we have an id to attach the history entry to.
            $extensionId = $this->createExtension($data);

            if (!$extensionId) {
                return false;
            }

            Factory::getApplication()->setUserState('com_jed.edit.extension.id', $extensionId);
        }

        $categories = (array) ($data['categories'] ?? []);

        // Force a new INSERT rather than an UPDATE of an existing history entry
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
        // stripped by Form::filter() in AdminModel::validate() before $data reached us here.
        // Read them straight from the raw request instead.
        $rawPost = (array) Factory::getApplication()->getInput()->post->get('jform', [], 'array');

        $this->storeCategories($extensionId, $categories);
        $this->storeMaintainers($extensionId, (array) ($data['maintainer'] ?? []));
        $this->deleteMarkedUploads($extensionId, (array) ($rawPost['deleteImages'] ?? []), '#__jed_extensions_images');
        $this->deleteMarkedUploads($extensionId, (array) ($rawPost['deleteFiles'] ?? []), '#__jed_extensions_files');
        $this->storeUploadedImages($extensionId, (array) ($data['images'] ?? []));
        $this->storeUploadedFiles($extensionId, (array) ($data['files'] ?? []));

        // Keep state pointing to the extension ID (not the new history entry's PK)
        $this->setState($this->getName() . '.id', $extensionId);

        return true;
    }

    /**
     * Create the live #__jed_extensions row for a brand new extension (state.id is still 0).
     *
     * @param array $data The submitted form data.
     *
     * @return int The new #__jed_extensions.id, or 0 on failure (an error is set on the model).
     *
     * @since 4.0.0
     */
    private function createExtension(array $data): int
    {
        $user = Factory::getApplication()->getIdentity();

        /** @var ExtensionTable $table */
        $table = $this->getTable('Extension');

        $liveData = [
            'id'    => 0,
            'name'  => (string) ($data['name'] ?? ''),
            'alias' => (string) ($data['alias'] ?? ''),
            'catid' => !empty($data['catid']) ? (int) $data['catid'] : null,
            'owner' => !empty($data['owner']) ? (int) $data['owner'] : (int) $user->id,
            'state' => 0,
        ];

        if (!$table->save($liveData)) {
            $this->setError($table->getError());

            return 0;
        }

        $extensionId = (int) $table->id;

        $this->setState($this->getName() . '.id', $extensionId);

        return $extensionId;
    }

    /**
     * Get array of review scores for extension
     *
     * @param int $extension_id
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getScores(int $extension_id): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true);
        $query->select('*')->from($db->quoteName('#__jed_extension_scores'))->where($db->quoteName('extension_id') . ' = ' . $db->quote($extension_id));

        $db->setQuery($query);
        $result = $db->loadObjectList();
        $retval = [];
        foreach ($result as $r) {
            if ($r->supply_option_id == 1) {
                $supply = 'Free';
            } else {
                $supply = 'Paid';
            }
            $retval[$supply] = $r;
        }

        return $retval;
    }

    /**
     * Method to get Developer Information
     *
     * @return stdClass
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getDeveloperInfo(): stdClass
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)->select('e.id,d.developer_name,u.username,u.email')->from('#__jed_extensions as e')->join('INNER', '#__users as u ON u.id = e.created_by')->join('INNER', '#__jed_developers as d ON d.user_id=u.id')->where($db->quoteName('e.id') . ' = ' . $this->item->id);
        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Method to save the approved state.
     *
     * @param array $data The form data.
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function saveApprove(array $data): void
    {
        if (!$data['id']) {
            throw new InvalidArgumentException(
                Text::_('COM_JED_EXTENSION_ID_MISSING')
            );
        }

        $extensionId = (int) $data['id'];

        /**
         *
         *
         * @var ExtensionTable $table
         */
        $table = $this->getTable('Extension');

        $table->load($extensionId);

        // approved_reason/approved_notes live directly on #__jed_extensions and are persisted by save() below.
        if (!empty($data['approvedReason']) && (int) $data['approved'] === 3) {
            $data['approved_reason'] = implode("\n", (array) $data['approvedReason']);
        }

        if (!$table->save($data)) {
            throw new RuntimeException('Save Failed');
        }
    }

    /**
     * Method to save the published state.
     *
     * @param array $data The form data.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function savePublish(array $data): void
    {
        if (!$data['id']) {
            throw new InvalidArgumentException(
                Text::_('COM_JED_EXTENSION_ID_MISSING')
            );
        }

        $extensionId = (int) $data['id'];

        /**
         * @var ExtensionTable $table
         */
        $table = $this->getTable('Extension');

        $table->load($extensionId);

        // approved_notes lives directly on #__jed_extensions and is persisted by save() below.
        if (!empty($data['publishedReason']) && (int) $data['published'] !== 1) {
            $data['approved_notes'] = implode("\n", (array) $data['publishedReason']);
        }

        if (!$table->save($data)) {
            throw new RuntimeException('Save Failed');
        }
    }

    /**
     * Store the images for an extension.
     *
     * @param int   $extensionId The extension ID to save the images for
     * @param array $images      The extension types to store
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function storeImages(int $extensionId, array $images): void
    {
        $db = $this->getDatabase();


        $query = $db->getQuery(true)->delete($db->quoteName('#__jed_extensions_images'))->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query)->execute();

        if (empty($images)) {
            return;
        }

        $query->clear()->insert($db->quoteName('#__jed_extensions_images'))->columns(
            $db->quoteName(
                [
                    'extension_id',
                    'filename',
                    'order',
                ]
            )
        );

        array_walk(
            $images,
            static function ($image, $key) use (&$query, $db, $extensionId) {
                $order = (int) str_replace('images', '', $key) + 1;
                $query->values(
                    $extensionId . ',' . $db->quote($image['image']) . ',' . $order
                );
            }
        );

        $db->setQuery($query)->execute();
    }

    /**
     * Store an internal note.
     *
     * @param string $body        The note content
     * @param int    $developerId The developer to store the note for
     * @param int    $userId      The JED member storing the note
     * @param int    $extensionId The extension ID the message is about
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function storeNote(string $body, int $developerId, int $userId, int $extensionId): void
    {

        $developer = new User($developerId);

        if ($developer->id == 0) {
            throw new InvalidArgumentException(
                Text::_('COM_JED_DEVELOPER_NOT_FOUND')
            );
        }

        $noteTable = new NoteTable($this->getDatabase());
        $result    = $noteTable->save(
            [
                'extension_id'    => $extensionId,
                'body'            => $body,
                'developer_id'    => $developer->id,
                'developer_name'  => $developer->name,
                'developer_email' => $developer->email,
                'created'         => (Date::getInstance())->toSql(),
                'created_by'      => $userId,
            ]
        );

        if ($result === false) {
            throw new RuntimeException('Save Failed');
        }
    }

    /**
     * Store supported versions for an extension.
     *
     * @param int    $extensionId The extension ID to save the versions for
     * @param array  $versions    The versions to store
     * @param string $type        THe type of versions to store
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function storeVersions(
        int $extensionId,
        array $versions,
        string $type
    ): void {
        $db = $this->getDatabase();


        $query = $db->getQuery(true)->delete($db->quoteName('#__jed_extensions_' . $type . '_versions'))->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query)->execute();

        if (empty($versions)) {
            return;
        }

        $query->clear()->insert($db->quoteName('#__jed_extensions_' . $type . '_versions'))->columns(
            $db->quoteName(
                [
                    'extension_id',
                    'version',
                ]
            )
        );

        array_walk(
            $versions,
            static function ($version) use (&$query, $db, $extensionId) {
                $query->values($extensionId . ',' . $db->quote($version));
            }
        );

        $db->setQuery($query)->execute();
    }
}
