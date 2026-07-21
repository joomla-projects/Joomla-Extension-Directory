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
 * VEL Abandoned Report Form Model Class.
 *
 * @since 4.0.0
 */
class AbandonedreportModel extends FormModel
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
    private string $dbtable = "#__vel_abandoned";



    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return mixed    A Form object on success, false on failure
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form
    {
        // Get the form.
        $form = $this->loadForm(
            'com_vel.abandonedreport',
            'abandonedreportform',
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
     * Method to get an object.
     *
     * @param int|null $id The id of the object to get.
     *
     * @return Object|bool Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem(int $id = null): mixed
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('abandonedreport.id');
            }

            // Get a level row instance.
            $table      = $this->getTable();

            if ($table->load($id) && !empty($table->id)) {
                $table_data = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

                $user = Factory::getApplication()->getIdentity();
                $id   = $table->id;
                if (empty($id) || JedHelper::isAdminOrSuperUser() || $table_data->created_by == $user->id) {
                    $canEdit = $user->authorise('core.edit', 'com_vel') || $user->authorise('core.create', 'com_vel');

                    if (!$canEdit && $user->authorise('core.edit.own', 'com_vel')) {
                        $canEdit = $user->id == $table_data->created_by;
                    }

                    if (!$canEdit) {
                        throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
                    }

                    // Check published state.
                    if ($published = $this->getState('filter.published')) {
                        if (isset($table->state) && $table->state != $published) {
                            return $this->item;
                        }
                    }

                    // Convert the Table to a clean stdClass.
                    $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

                    if (isset($this->item->category_id) && is_object($this->item->category_id)) {
                        $this->item->category_id = ArrayHelper::fromObject($this->item->category_id);
                    }
                } else {
                    throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
                }
            }
        }

        return $this->item;
    }

    /**
     * Method to delete data
     *
     * @param int  $pk  Item primary key
     *
     * @return int  The id of the deleted item
     *
     * @since  4.0.0
     * @throws Exception
     */
    /*public function delete($pk)
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$pk || JedHelper::userIDItem($pk,$this->dbtable) || JedHelper::isAdminOrSuperUser())
        {
            if (empty($pk))
            {
                $pk = (int) $this->getState('abandonedreport.id');
            }

            if ($pk == 0 || $this->getItem($pk) == null)
            {
                throw new Exception(Text::_('COM_VEL_ITEM_DOESNT_EXIST'), 404);
            }

            if ($user->authorise('core.delete', 'com_vel') !== true)
            {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $table = $this->getTable();

            if ($table->delete($pk) !== true)
            {
                throw new Exception(Text::_('JERROR_FAILED'), 501);
            }

            return $pk;
        }
        else
        {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }*/

    /**
     * Method to get the table
     *
     * @param string $name
     * @param string $prefix  Optional prefix for the table class name
     * @param array  $options
     *
     * @return Table Table if found
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Abandonedreport', $prefix = 'Administrator', $options = []): Table
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
        $data = Factory::getApplication()->getUserState('com_vel.edit.abandonedreport.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {
            // Support for multiple or not foreign key field: consent_to_process
            $array = [];

            foreach ((array) $data->consent_to_process as $value) {
                if (!is_array($value)) {
                    $array[] = $value;
                }
            }
            if (!empty($array)) {
                $data->consent_to_process = $array;
            }
            // Support for multiple or not foreign key field: passed_to_vel
            $array = [];

            foreach ((array) $data->passed_to_vel as $value) {
                if (!is_array($value)) {
                    $array[] = $value;
                }
            }
            if (!empty($array)) {
                $data->passed_to_vel = $array;
            }
            // Support for multiple or not foreign key field: data_source
            $array = [];

            foreach ((array) $data->data_source as $value) {
                if (!is_array($value)) {
                    $array[] = $value;
                }
            }
            if (!empty($array)) {
                $data->data_source = $array;
            }

            return $data;
        }

        return [];
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
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_vel.edit.abandonedreport.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_vel.edit.abandonedreport.id', $id);
        }

        $this->setState('abandonedreport.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('abandonedreport.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
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
        $id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('abandonedreport.id');


        $data['user_ip']       = $_SERVER['REMOTE_ADDR'];
        $data['data_source']   = CURL_HTTP_VERSION_1_0;
        $data['passed_to_vel'] = 0;

        $isLoggedIn = JedHelper::isLoggedIn();

        if ((!$id || JedHelper::isAdminOrSuperUser()) && $isLoggedIn) {
            /* Any logged-in user can report an abandoned Item */

            $table = $this->getTable();

            if ($table->save($data) === true) {
                // Ticket creation (initial message + confirmation mail) is handled by
                // AbandonedreportController::save() via TicketHandlingTrait::triggerTicket()
                // once this call returns successfully.
                return $table->id;
            }
            return false;
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }
}
