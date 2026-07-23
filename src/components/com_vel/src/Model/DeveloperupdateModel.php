<?php

/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * VEL Developer Update Model Class.
 *
 * @since 4.0.0
 */
class DeveloperupdateModel extends FormModel
{
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
     * @since 4.0.0
     **/
    private string $dbtable = "#__vel_developer_update";

    /**
     * Method to get an object.
     *
     * @param int $pk The id of the object to get.
     *
     * @return false|object|null    Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem($pk = null): object|bool|null
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();
        if ($this->item === null) {
            $this->item = false;

            if (empty($pk)) {
                $pk = $this->getState('developerupdate.id');
            }

            // No id to load: this is a blank "new developer update" submission, not an error.
            if (empty($pk)) {
                return null;
            }

            // Get a level row instance.
            $table = $this->getTable();

            // Attempt to load the row.
            $keys = ["id" => $pk, "created_by" => Factory::getApplication()->getIdentity()->id];

            if ($table->load($keys)) {
                if (empty($result) || JedHelper::isAdminOrSuperUser()) {
                    // Check published state.
                    if ($published = $this->getState('filter.published')) {
                        if (isset($table->state) && $table->state != $published) {
                            $app->enqueueMessage("Item is not published", "message");

                            return null;
                        }
                    }

                    // Convert the JTable to a clean JObject.
                    $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);
                } else {
                    $app->enqueueMessage("Sorry you did not create that report item", "message");

                    return null;
                    //throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
                }
            }

            if (empty($this->item)) {
                $app->enqueueMessage(Text::_('COM_VEL_SECURITY_CANT_LOAD'), "message");

                return null;
            }
        }


        if (!empty($this->item->consent_to_process)) {
            $this->item->consent_to_process = Text::_(
                'COM_VEL_GENERAL_CONSENT_TO_PROCESS_OPTION_' . $this->item->consent_to_process
            );
        }

        if (!empty($this->item->update_data_source)) {
            $this->item->update_data_source = Text::_(
                'COM_VEL_DEVELOPERUPDATES_UPDATE_DATA_SOURCE_OPTION_' . $this->item->update_data_source
            );
        }

        if (isset($this->item->created_by)) {
            $this->item->created_by_name = JedHelper::getUserById($this->item->created_by)->name;
        }

        if (isset($this->item->modified_by)) {
            $this->item->modified_by_name = JedHelper::getUserById($this->item->modified_by)->name;
        }

        return $this->item;
    }

    /**
     * Get an instance of Table class
     *
     * @param string $name    Name of the JTable class to get an instance of.
     * @param string $prefix  Prefix for the table class name. Optional.
     * @param array  $options Array of configuration values for the JTable object. Optional.
     *
     * @return Table Table if success, false on failure.
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Developerupdate', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to autopopulate the model state.
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
        $app  = Factory::getApplication();
        $user = Factory::getApplication()->getIdentity();

        // Check published state
        if ((!$user->authorise('core.edit.state', 'com_vel')) && (!$user->authorise('core.edit', 'com_vel'))) {
            $this->setState('filter.published', 1);
            $this->setState('filter.archived', 2);
        }

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_vel.edit.developerupdate.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_vel.edit.developerupdate.id', $id);
        }

        $this->setState('developerupdate.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('developerupdate.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Publish the element
     *
     * @param int $id    Item id
     * @param int $state Publish state
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function publish(int $id, int $state): bool
    {
        $table = $this->getTable();
        if ($id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            $table->load($id);

            $table->state = $state;

            return $table->store();
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
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
        $form = $this->loadForm('com_vel.developerupdate', 'developerupdate', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed The default data is an empty array.
     *
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_vel.edit.developerupdate.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data ?: [];
    }

    /**
     * Method to save the form data.
     *
     * @param array $data The form data
     *
     * @return int|bool The new developer update's id on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function save(array $data): int|bool
    {
        $id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('developerupdate.id');

        $data['update_user_ip']        = $_SERVER['REMOTE_ADDR'];
        $data['update_data_source']    = 1;
        $data['vel_item_id']           = (int) ($data['vel_item_id'] ?? 0);

        $isLoggedIn = JedHelper::isLoggedIn();

        if ((!$id || JedHelper::isAdminOrSuperUser()) && $isLoggedIn) {
            /* Any logged-in user can submit a developer update */

            $table = $this->getTable();

            if ($table->save($data) === true) {
                // Ticket creation (initial message + confirmation mail) is handled by
                // DeveloperupdateController::save() via TicketHandlingTrait::triggerTicket()
                // once this call returns successfully.
                return $table->id;
            }
            return false;
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }
}
