<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\MediaHandling\ImageSize;
use Jed\Component\Jed\Site\Helper\JedemailHelper;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Log\Log;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\MVC\Model\FormModel;
use Michelf\Markdown;
use SimpleXMLElement;
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

        if (!is_object($form)) {
            throw new Exception(Text::_('JERROR_LOADFILE_FAILED'), 500);
        }

        return $form;
    }

    /**
     * Method to get the table
     *
     * @param string $name   Name of the Table class
     * @param string $prefix Optional prefix for the table class name
     * @param array  $options Optional configuration array for Table object
     *
     * @return Table|bool Table if found, bool false on failure
     * @throws Exception
     * @since 4.0.0
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
            $data = $this->getItem();
        }

        if ($data) {
            // Support for multiple or not foreign key field: uses_updater
            /*   $array = [];

               foreach ((array) $data->uses_updater as $value) {
                   if (!is_array($value)) {
                       $array[] = $value;
                   }
               }
               if (!empty($array)) {
                   $data->uses_updater = $array;
               }
*/
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
     * Method to get an object.
     *
     * @param int|null $id The id of the object to get.
     *
     * @return object|bool Object on success, false on failure.
     *
     * @since 4.0.0
     * @throws Exception
     */
    public function getItem(int $id = null)
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('extension.id');
            }

            // Get a level row instance.
            $table      = $this->getTable();
            $properties = $table->getTableProperties();
            $this->item = ArrayHelper::toObject($properties, stdClass::class);

            if ($table !== false && $table->load($id) && !empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();
                $id   = $table->id;
                if (empty($id) || JedHelper::isAdminOrSuperUser() || $table->created_by == $user->id) {
                    // Convert the Table to a clean CMSObject.
                    $properties = $table->getTableProperties(1);
                    $this->item = ArrayHelper::toObject($properties, stdClass::class);

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
     * Get an item by alias
     *
     * @param   string  $alias  Alias string
     *
     * @return int Element id
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function getItemIdByAlias($alias): ?int
    {
        $table      = $this->getTable();
        $properties = $table->getTableProperties();

        if (!in_array('alias', $properties)) {
            return null;
        }

        $table->load(['alias' => $alias]);
        $id = $table->id;

        if (empty($id) || JedHelper::isAdminOrSuperUser() || $table->created_by == Factory::getApplication()->getIdentity()->id) {
            return $id;
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }

    /**
     * Method to check in an item.
     *
     * @param   int  $pk  The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since 4.0.0
     * @throws Exception
     */
    public function checkin($pk = null): bool
    {
        // Get the id.
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('extension.id');
        if (!$pk || JedHelper::userIDItem($pk, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($pk) {
                // Initialise the table
                $table = $this->getTable();

                // Attempt to check the row in.
                if (method_exists($table, 'checkin')) {
                    if (!$table->checkin($pk)) {
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
     * Method to check out an item for editing.
     *
     * @param   int  $pk  The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since 4.0.0
     * @throws Exception
     */
    public function checkout($pk = null): bool
    {
        // Get the user id.
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('extension.id');
        if (!$pk || JedHelper::userIDItem($pk, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($pk) {
                // Initialise the table
                $table = $this->getTable();

                // Get the current user object.
                $user = Factory::getApplication()->getIdentity();

                // Attempt to check the row out.
                if (method_exists($table, 'checkout')) {
                    if (!$table->checkout($user->id, $pk)) {
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
     * Method to save the form data.
     *
     * @param array $data The form data
     *
     * @return bool
     *
     * @throws Exception
     * @since  4.0.0
     */
    public function save(array $data): bool
    {
        $id    = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('extension.id');
        $state = (!empty($data['state'])) ? 1 : 0;
        $user  = Factory::getApplication()->getIdentity();

        if (!$id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($id) {
                // Check the user can edit this item
                $authorised = $user->authorise('core.edit', 'com_jed') || $authorised = $user->authorise('core.edit.own', 'com_jed');
            } else {
                // Check the user can create new items in this section
                $authorised = $user->authorise('core.create', 'com_jed');
            }

            if ($authorised !== true) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $table = $this->getTable();

            if ($table->save($data) === true) {
                $this->id                            = $table->id;
                $ticket                              = JedHelper::createExtensionTicket($table->id);
                $ticket_message                      = JedHelper::createEmptyTicketMessage();
                $ticket_message['subject']           = $ticket['ticket_subject'];
                $ticket_message['message']           = $ticket['ticket_text'];
                $ticket_message['message_direction'] = 1; /*  1 for coming in, 0 for going out */


                //$ticket_model = BaseDatabaseModel::getInstance('Ticketform', 'JedModel', ['ignore_request' => true]);
                $ticket_model = new TicketformModel();
                $ticket_model->save($ticket);

                $ticket_id = $ticket_model->getId();
                /* We need to store the incoming ticket message */
                $ticket_message['ticket_id'] = $ticket_id;

                //$ticket_message_model = BaseDatabaseModel::getInstance('Ticketmessageform', 'JedModel', ['ignore_request' => true]);
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
                    $ticket['created_by']                = -1;
                    $ticket['modified_by']               = -1;
                    $ticket_message_model->save($ticket_message);
                }

                return $table->id;
            } else {
                return false;
            }
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }

    /**
     * Method to delete data
     *
     * @param int $pk Item primary key
     *
     * @return int  The id of the deleted item
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function delete($id)
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
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
    public function getCanSave()
    {
        $table = $this->getTable();

        return $table !== false;
    }

    /**
     * Get array of supply types for extension
     *
     * @param int $extension_id
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getSupplyTypes(): array
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);


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
     * Method to get a single record.
     *
     * @param   int|null  $pk                  The id of the primary key.
     * @param   int       $supply_option_type  The type of varied data to look for
     *
     * @return stdClass    Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getvariedItem(int $pk = null, int $supply_option_type = 0)
    {
        if ($item = $this::getItem($pk)) {
            /* Convert cmsobject to stdClass */

            $this->item = (object) $item;

            if (isset($this->item->includes)) {
                $this->item->includes = array_values(json_decode($this->item->includes));
            }
            if (isset($this->item->joomla_versions)) {
                $this->item->joomla_versions = array_values(json_decode($this->item->joomla_versions));
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
                //  $this->item->category_hierarchy = ExtensionUtilities::getCategoryHierarchy($this->item->primary_category_id);
            }

            /* Load Varied Data */

            //$s = $item->getTableProperties();

            //  $this->item->varied_data = $this->getVariedData($this->item->id, $supply_option_type);
            $supply_types = $this->getSupplyTypes();
            $vi           = new ExtensionvarieddatumModel();

            foreach ($supply_types as $st) {
                $keys['extension_id']     = $this->item->id;
                $keys['supply_option_id'] = $st->supply_id;
                $vi                       = new ExtensionvarieddatumModel();
                $vitem                    = $vi->getItem($keys);
                //echo "<pre>";print_r($vitem);echo "</pre>";exit();
                //if($st->supply_type==='Free') {

                //  $vitem->is_default_data=1;
                //}
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

            return $this->item;
        }

        return new stdClass();
    }

    /**
     * Get varied data for extension, i.e. fields for free, fields for paid
     *
     * @param int      $extension_id
     * @param int|null $supply_option_type
     *
     * @return array
     *
     * @throws Exception
     * @since  4.0.0
     */
    public function getVariedData(int $extension_id, int $supply_option_type = null): array
    {
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

            if (!empty($variedDatum->logo)) {
                $variedDatum->logo = JedHelper::formatImage($variedDatum->logo, ImageSize::LARGE);
            }

            if ($variedDatum->is_default_data == 1 && empty($variedDatum->intro_text)) {
                $split_data = ExtensionUtility::splitDescription($variedDatum->description);

                if (!is_null($split_data)) {
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
}
