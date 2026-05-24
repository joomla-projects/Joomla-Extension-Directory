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
use Jed\Component\Jed\Site\Helper\JedemailHelper;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\MediaHandling\ImageSize;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Michelf\Markdown;
use stdClass;

/**
 * Extension Form model.
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
        $user = Factory::getApplication()->getIdentity();

        if (! $id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if (empty($id)) {
                $id = (int) $this->getState('extension.id');
            }

            if ($id == 0 || $this->getItem($id) == null) {
                throw new Exception(Text::_('COM_JED_ITEM_DOESNT_EXIST'), 404);
            }

            if ($user->authorise('core.delete', 'com_jed') !== true) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $table = $this->getTable();

            if ($table->delete($id) !== true) {
                throw new Exception(Text::_('JERROR_FAILED'), 501);
            }

            return $id;
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
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
    public function getTable($name = 'Extension', $prefix = 'Administrator', $options = []): Table|bool
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
            // Use getvariedItem instead of getItem to get the joomla_supply_type data
            if (!is_null($this->item)) {
                $data = $this->item;
            } else {
                $data = $this->getvariedItem();
            }
        }

        if ($data) {
            return $data;
        }

        return [];
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
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_jed.edit.extension.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_jed.edit.extension.id', $id);
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
        $pk = ( ! empty($pk)) ? $pk : (int) $this->getState('extension.id');

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
        // Get the user id.
        $pk = ( ! empty($pk)) ? $pk : (int) $this->getState('extension.id');


        if (! $pk || JedHelper::userIDItem($pk, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
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
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }

    /**
     * Method to get an object.
     *
     * @param int|null $id The id of the object to get.
     *
     * @return stdClass|bool Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem(int $id = null): stdClass|bool
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('extension.id');
            }

            // Get a level row instance.
            $table      = $this->getTable();
            $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

            if ($table !== false && $table->load($id) && ! empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();

                if (JedHelper::isAdminOrSuperUser() || $table->created_by == $user->id) {
                    // Convert the Table to a clean stdClass.
                    $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

                    if (isset($this->item->primary_category_id) && is_object($this->item->primary_category_id)) {
                        $this->item->primary_category_id = ArrayHelper::fromObject($this->item->primary_category_id);
                    }
                } else {
                    throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
                }
            }
        }

        return $this->item;
    }


    /**
     * Get varied data for extension, i.e. fields for free, fields for paid
     *
     * @param int      $extension_id
     * @param int|null $supply_option_type
     *
     * @return array
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getVariedData(int $extension_id, int $supply_option_type = null): array
    {
        $retval = null;
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('supply_options.title AS supply_type, a.*')
            ->from($db->quoteName('#__jed_extension_varied_data', 'a'))
            ->leftJoin(
                $db->quoteName('#__jed_extension_supply_options', 'supply_options')
                        . ' ON ' . $db->quoteName('supply_options.id') . ' = ' . $db->quoteName('a.supply_option_id')
            )
            ->where($db->quoteName('extension_id') . ' = :extension_id')
            ->bind(':extension_id', $extension_id, ParameterType::INTEGER);

        if (($supply_option_type ?? 0) > 0) {
            $query
                ->where($db->quoteName('supply_option_id') . ' = :supply_option_type')
                ->bind(':supply_option_type', $supply_option_type, ParameterType::INTEGER);
        }

        $result = $db->setQuery($query)->loadObjectList();

        foreach ($result as $variedDatum) {
            $supply = $variedDatum->supply_type;

            if (! empty($variedDatum->logo)) {
                $variedDatum->logo = JedHelper::formatImage($variedDatum->logo, ImageSize::LARGE);
            }

            if ($variedDatum->is_default_data == 1 && empty($variedDatum->intro_text)) {
                $split_data = self::splitDescription($variedDatum->description);

                if (! is_null($split_data)) {
                    $variedDatum->intro_text  = $split_data['intro'];
                    $variedDatum->description = $split_data['body'] . Markdown::defaultTransform($variedDatum->description);
                }
            } else {
                $variedDatum->intro_text  = Markdown::defaultTransform($variedDatum->intro_text);
                $variedDatum->description = Markdown::defaultTransform($variedDatum->description);
            }

            $retval[$supply] = $variedDatum;
        }

        return $retval;
    }

    /**
     * jdav
     *
     * Returns an array of values from a json encoded string
     *
     * @param $value
     *
     * @return array
     *
     * @since 1.0
     */
    private function jdav($value): array
    {

        if (is_array($value)) {
            return array_values($value);
        } else {
            return array_values(json_decode($value));
        }
    }
    /**
     * Method to get a single record.
     *
     * @param int|null $pk                 The id of the primary key.
     * @param int      $supply_option_type The type of varied data to look for
     *
     * @return stdClass    Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getvariedItem(int $pk = null, int $supply_option_type = 0): stdClass
    {
        if ($item = $this::getItem($pk)) {
            /* Convert cmsobject to stdClass */

            $this->item = (object) $item;

            if (isset($this->item->includes)) {
                $this->item->includes = self::jdav($this->item->includes);
            }
            if (isset($this->item->joomla_versions)) {
                $this->item->joomla_versions = self::jdav($this->item->joomla_versions);
            }
            if (isset($this->item->created_by)) {
                $this->item->created_by_name = JedHelper::getUserById($this->item->created_by)->name;
            }

            if (isset($this->item->modified_by)) {
                $this->item->modified_by_name = JedHelper::getUserById($this->item->modified_by)->name;
            }

            /* Load Category Hierarchy */
            if (is_null($this->item->primary_category_id)) {
                $this->item->category_hierarchy = "";
            } else {
                  $this->item->category_hierarchy = self::getCategoryHierarchy($this->item->primary_category_id);
            }

            /* Load Varied Data */

            $supply_types = $this->getSupplyTypes();

            // Track which supply types have actual data
            // For existing extensions, populate based on database records
            // For new extensions (id = 0), leave as null to use form defaults
            if ($this->item->id == 0) {
                $this->item->joomla_supply_type[] = '1';
            }


            foreach ($supply_types as $st) {
                $keys['extension_id']     = $this->item->id;
                $keys['supply_option_id'] = $st->supply_id;
                $vi                       = new ExtensionvarieddatumModel();
                $vitem                    = $vi->getItem($keys);

                // For existing extensions, check if this supply type has saved data
                if ($this->item->id > 0 && !empty($vitem->id)) {
                    // This supply type has data, add it to the checked list
                    $this->item->joomla_supply_type[] = (string)$st->supply_id;
                }

                $this->item->varied[$st->supply_id] = $vitem;
            }


            $this->item->title        = '';
            $this->item->alias        = '';
            $this->item->intro_text   = '';
            $this->item->support_link = '';


            $this->item->scores = 0;


            $this->item->number_of_reviews = 0;


            $this->item->developer_email   = '';
            $this->item->developer_company = '';


            /*  $db = $this->getDatabase();

            $query = $db->getQuery(true);
            $query->select('supply_options.title AS supply_type, a.*')
                ->from($db->quoteName('#__jed_extension_varied_data', 'a'))
                ->leftJoin(
                    $db->quoteName('#__jed_extension_supply_options', 'supply_options')
                    . ' ON ' . $db->quoteName('supply_options.id') . ' = ' . $db->quoteName('a.supply_option_id')
                )
                ->where($db->quoteName('extension_id') . ' = ' . $db->quote($pk))
            ->where($db->quoteName('supply_option_id') . ' = ' . $db->quote($supply_option_type));

            $db->setQuery($query);
            $result = $db->loadObjectList();

            foreach ($result as $r)
            {

                $supply = $r->supply_type;

                if ($r->logo <> "")
                {
                    ///cache/fab_image/61273fd97f89c_resizeDown1200px525px16.png
                    $r->logo = 'https://extensions.joomla.org/cache/fab_image/' . str_replace('.png', '', $r->logo) . '_resizeDown1200px525px16.png';
                    //echo $item->logo;exit();
                }
                if($r->is_default_data == 1)
                {
                    //echo "<pre>";print_r($r);echo "</pre>";exit();
                    $split_data =  $this->SplitDescription($r->description);
                    if(!is_null($split_data))
                    {
                        $r->intro_text =$split_data['intro'];
                        $r->description = $split_data['body'];
                    }
                }
                $retval[$supply] = $r;
            }
            $item->varied_data = $retval; */
            //echo "<pre>";print_r($this->item);echo "</pre>";
            return $this->item;
        }

        return new stdClass();
    }

    /**
     * Get array of supply types for extension
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getSupplyTypes(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);


        $query->select(
            [
                        $db->quoteName('supply_options.id', 'supply_id'),
                        $db->quoteName('supply_options.title', 'supply_type'),
                ]
        )
            ->from($db->quoteName('#__jed_extension_supply_options', 'supply_options'))
            ->where('state=1');

        return $db->setQuery($query)->loadObjectList();
    }

    /**
     * Method to save the form data.
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
        $id   = ( ! empty($data['id'])) ? (int) $data['id'] : (int) $this->getState('extension.id');
        $user = Factory::getApplication()->getIdentity();

        if (! $id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($id) {
                $authorised = $user->authorise('core.edit', 'com_jed') || $user->authorise('core.edit.own', 'com_jed');
            } else {
                // Check the user can create new items in this section
                $authorised = $user->authorise('core.create', 'com_jed');
            }

            if ($authorised !== true) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $table = $this->getTable();
            unset($data['alias']);
            unset($data['title']);
            if ($table->save($data) === true) {
                $extensionId = (int) $table->id;

                // Ensure the submitting user is present in the jed_developers table.
                try {
                    $db    = $this->getDatabase();
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__jed_developers'))
                        ->where($db->quoteName('user_id') . ' = ' . $db->quote((int) $user->id));

                    $exists = $db->setQuery($query)->loadResult();

                    if (empty($exists)) {
                        $devName = ( ! empty($user->name)) ? $user->name : $user->username;
                        $columns = [
                                $db->quoteName('user_id'),
                                $db->quoteName('developer_name'),
                        ];

                        $values = [
                                $db->quote((int) $user->id),
                                $db->quote($devName),
                        ];

                        $insert = $db->getQuery(true)
                            ->insert($db->quoteName('#__jed_developers'))
                            ->columns($columns)
                            ->values(implode(', ', $values));

                        $db->setQuery($insert)->execute();
                    }
                } catch (\Exception $e) {
                    // Don't break the save if developer insertion fails. Log and continue.
                    try {
                        Log::add('Failed to ensure developer entry for user id ' . (int) $user->id . ': ' . $e->getMessage(), Log::ERROR, 'com_jed');
                    } catch (\Exception) {
                    }
                }

                // If editing an existing extension, backup current data to history before updating
                if ($id > 0) {
                    $this->backupExtensionToHistory($extensionId, (int) $user->id);
                }

                // Store tabbed varied data (one row per supply option)
                $this->storeVariedData($extensionId, (array) ($data['supply'] ?? []), (int) $user->id);

                // ... existing code ... (ticket creation etc.)
                $this->id = $extensionId;

                $ticket                              = JedHelper::createExtensionTicket($extensionId);
                $ticket_message                      = JedHelper::createEmptyTicketMessage();
                $ticket_message['subject']           = $ticket['ticket_subject'];
                $ticket_message['message']           = $ticket['ticket_text'];
                $ticket_message['message_direction'] = 1; /*  1 for coming in, 0 for going out */

                $ticket_model = new TicketformModel();
                $ticket_model->save($ticket);

                $ticket_id                   = $ticket_model->getId();
                $ticket_message['ticket_id'] = $ticket_id;

                $ticket_message_model = new TicketmessageformModel();
                $ticket_message_model->save($ticket_message);

                /* We need to email standard message to user and store message in ticket */
                $message_out = JedHelper::getMessageTemplate(1000);
                if (isset($message_out->subject)) {
                    JedemailHelper::sendEmail($message_out->subject, $message_out->template, $user, 'dummy@dummy.com');

                    $ticket_message['id']                = 0;
                    $ticket_message['subject']           = $message_out->subject;
                    $ticket_message['message']           = $message_out->template;
                    $ticket_message['message_direction'] = 0; /* 1 for coming in, 0 for going out */
                    //$ticket_message['created_by']                = -1;
                    //$ticket_message['modified_by']               = -1;
                    $ticket_message_model->save($ticket_message);
                }

                return $table->id;
            }

            return false;
        }

        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }

    /**
     * Backup extension and varied data to history table before updating
     *
     * @param int $extensionId
     * @param int $userId
     *
     * @return void
     * @since  4.0.0
     * @throws Exception
     */
    private function backupExtensionToHistory(int $extensionId, int $userId): void
    {
        if ($extensionId <= 0) {
            return;
        }

        try {
            $db = $this->getDatabase();

            // Get current extension data
            $query = $db->getQuery(true)
                ->select('e.*')
                ->from($db->quoteName('#__jed_extensions', 'e'))
                ->where($db->quoteName('e.id') . ' = ' . $extensionId);

            $extension = $db->setQuery($query)->loadObject();

            if (! $extension) {
                return;
            }

            // Get all varied data for this extension
            $query = $db->getQuery(true)
                ->select('v.*')
                ->from($db->quoteName('#__jed_extension_varied_data', 'v'))
                ->where($db->quoteName('v.extension_id') . ' = ' . $extensionId);

            $variedData = $db->setQuery($query)->loadObjectList();

            // Create a history row for each supply option variant
            foreach ($variedData as $varied) {
                $historyColumns = [
                        'extension_id',
                        'joomla_versions',
                        'popular',
                        'requires_registration',
                        'gpl_license_type',
                        'jed_internal_note',
                        'can_update',
                        'video',
                        'version',
                        'uses_updater',
                        'includes',
                        'approved',
                        'approved_time',
                        'second_contact_email',
                        'jed_checked',
                        'uses_third_party',
                        'primary_category_id',
                        'logo',
                        'supply_option_id',
                        'title',
                        'alias',
                        'intro_text',
                        'description',
                        'homepage_link',
                        'download_link',
                        'demo_link',
                        'support_link',
                        'documentation_link',
                        'license_link',
                        'translation_link',
                        'tags',
                        'update_url',
                        'update_url_ok',
                        'download_integration_type',
                        'download_integration_url',
                        'is_default_data',
                        'ordering',
                        'approved_notes',
                        'approved_reason',
                        'published_notes',
                        'published_reason',
                        'published',
                        'created_by',
                        'modified_by',
                        'created_on',
                        'modified_on',
                        'state',
                ];

                $historyValues = [
                        $extensionId,
                        $db->quote($extension->joomla_versions ?? ''),
                        (int) ($extension->popular ?? 0),
                        (int) ($extension->requires_registration ?? 0),
                        $db->quote($extension->gpl_license_type ?? ''),
                        $db->quote($extension->jed_internal_note ?? ''),
                        (int) ($extension->can_update ?? 0),
                        $db->quote($extension->video ?? ''),
                        $db->quote($extension->version ?? ''),
                        (int) ($extension->uses_updater ?? 0),
                        $db->quote($extension->includes ?? ''),
                        (int) ($extension->approved ?? 0),
                        $extension->approved_time ? $db->quote($extension->approved_time) : 'NULL',
                        $db->quote($extension->second_contact_email ?? ''),
                        (int) ($extension->jed_checked ?? 0),
                        (int) ($extension->uses_third_party ?? 0),
                        $extension->primary_category_id ? (int) $extension->primary_category_id : 'NULL',
                        $db->quote($extension->logo ?? ''),
                        (int) ($varied->supply_option_id ?? 0),
                        $db->quote($varied->title ?? ''),
                        $db->quote($varied->alias ?? ''),
                        $db->quote($varied->intro_text ?? ''),
                        $db->quote($varied->description ?? ''),
                        $db->quote($varied->homepage_link ?? ''),
                        $db->quote($varied->download_link ?? ''),
                        $db->quote($varied->demo_link ?? ''),
                        $db->quote($varied->support_link ?? ''),
                        $db->quote($varied->documentation_link ?? ''),
                        $db->quote($varied->license_link ?? ''),
                        $db->quote($varied->translation_link ?? ''),
                        $db->quote($varied->tags ?? ''),
                        $db->quote($varied->update_url ?? ''),
                        (int) ($varied->update_url_ok ?? 0),
                        $db->quote($varied->download_integration_type ?? ''),
                        $db->quote($varied->download_integration_url ?? ''),
                        (int) ($varied->is_default_data ?? 0),
                        (int) ($varied->ordering ?? 0),
                        $db->quote($extension->approved_notes ?? ''),
                        $db->quote($extension->approved_reason ?? ''),
                        $db->quote($extension->published_notes ?? ''),
                        $db->quote($extension->published_reason ?? ''),
                        (int) ($extension->published ?? 0),
                        (int) ($extension->created_by ?? 0),
                        $userId,
                        $extension->created_on ? $db->quote($extension->created_on) : 'NULL',
                        'NOW()',
                        (int) ($extension->state ?? 0),
                ];

                $insert = $db->getQuery(true)
                    ->insert($db->quoteName('#__jed_extensions_history'))
                    ->columns($db->quoteName($historyColumns))
                    ->values(implode(', ', $historyValues));

                $db->setQuery($insert)->execute();
            }
        } catch (\Exception $e) {
            // Don't break the save if backup fails. Log and continue.
            try {
                Log::add('Failed to backup extension ' . $extensionId . ' to history: ' . $e->getMessage(), Log::ERROR, 'com_jed');
            } catch (\Exception) {
            }
        }
    }

    /**
     * Upsert varied data rows for each supply tab into #__jed_extension_varied_data
     *
     * @param int   $extensionId
     * @param array $supplyPayload Posted as jform[supply][supplyX][field]=...
     * @param int   $userId
     *
     * @return void
     * @since  4.0.0
     * @throws Exception
     */
    private function storeVariedData(int $extensionId, array $supplyPayload, int $userId): void
    {
        if ($extensionId <= 0 || empty($supplyPayload)) {
            return;
        }

        // Ensure at most one "default" row
        $defaultAlreadySet = false;

        foreach ($supplyPayload as $row) {
            if (! is_array($row)) {
                continue;
            }

            $row['extension_id'] = $extensionId;

            $supplyOptionId = (int) ($row['supply_option_id'] ?? 0);
            if ($supplyOptionId <= 0) {
                continue;
            }

            // Normalize default flag
            $row['is_default_data'] = (int) ($row['is_default_data'] ?? 0);
            if ($row['is_default_data'] === 1) {
                if ($defaultAlreadySet) {
                    $row['is_default_data'] = 0;
                } else {
                    $defaultAlreadySet = true;
                }
            }

            // Basic "skip empty tab" safeguard: only store if there is at least something meaningful
            $hasMeaningfulContent = ! empty($row['title'])
                    || ! empty($row['description'])
                    || ! empty($row['download_link'])
                    || ! empty($row['homepage_link'])
                    || ! empty($row['documentation_link']);

            if (! $hasMeaningfulContent && $row['is_default_data'] !== 1) {
                continue;
            }

            // Load existing row by unique key (extension_id + supply_option_id)
            $variedTable = $this->getTable('Extensionvarieddatum', 'Administrator');

            $variedTable->load(
                [
                            'extension_id'     => $extensionId,
                            'supply_option_id' => $supplyOptionId,
                    ]
            );

            // If load() found a row, $variedTable->id will be set; otherwise it stays empty/0
            $row['id'] = (int) ($variedTable->id ?? 0);

            // Ensure created_by is set on insert
            if (empty($row['created_by'])) {
                $row['created_by'] = $userId;
            }

            $variedTable->save($row);
        }
    }

    /**
     * Upsert uploaded extension zip file metadata in #__jed_extensions_files.
     *
     * @param int   $extensionId
     * @param array $uploadedFiles
     * @param int   $userId
     *
     * @return void
     * @since  4.0.0
     * @throws Exception
     */
    public function storeExtensionFiles(int $extensionId, array $uploadedFiles, int $userId): void
    {
        if ($extensionId <= 0 || empty($uploadedFiles)) {
            return;
        }

        $db = $this->getDatabase();

        $primary      = reset($uploadedFiles);
        $file         = (string) ($primary['file'] ?? '');
        $originalFile = (string) ($primary['originalFile'] ?? '');
        $meta         = json_encode($uploadedFiles, JSON_UNESCAPED_SLASHES);

        $select = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jed_extensions_files'))
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId);

        $existingId = (int) $db->setQuery($select)->loadResult();

        if ($existingId > 0) {
            $update = $db->getQuery(true)
                ->update($db->quoteName('#__jed_extensions_files'))
                ->set($db->quoteName('file') . ' = ' . $db->quote($file))
                ->set($db->quoteName('originalFile') . ' = ' . $db->quote($originalFile))
                ->set($db->quoteName('meta') . ' = ' . $db->quote((string) $meta))
                ->where($db->quoteName('id') . ' = ' . $existingId);

            $db->setQuery($update)->execute();

            return;
        }

        $insert = $db->getQuery(true)
            ->insert($db->quoteName('#__jed_extensions_files'))
            ->columns(
                $db->quoteName(
                    [
                                        'extension_id',
                                        'file',
                                        'meta',
                                        'created_by',
                                        'originalFile',
                                ]
                )
            )
            ->values(
                implode(
                    ', ',
                    [
                                        $extensionId,
                                        $db->quote($file),
                                        $db->quote((string) $meta),
                                        $userId,
                                        $db->quote($originalFile),
                                ]
                )
            );

        $db->setQuery($insert)->execute();
    }
}
