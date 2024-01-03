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
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Registry\Registry;
use stdClass;

/**
 * Jed model.
 *
 * @since 4.0.0
 */
class ReviewcommentformModel extends FormModel
{
    private $item = null;


    /**
     * Checks whether or not a user is manager or super user
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function isAdminOrSuperUser()
    {
        try {
            $user = Factory::getApplication()->getIdentity();
            return in_array("8", $user->groups) || in_array("7", $user->groups);
        } catch (Exception $exc) {
            return false;
        }
    }


    /**
     * This method revises if the $id of the item belongs to the current user
     *
     * @param  int $id The id of the item
     * @return bool             true if the user is the owner of the row, false if not.
     *
     * @since 4.0.0
     */
    public function userIDItem($id)
    {
        try {
            $user  = Factory::getApplication()->getIdentity();
            $db    = Factory::getDbo();

            $query = $db->getQuery(true);
            $query->select("id")
                ->from($db->quoteName('#__jed_reviews_comments'))
                ->where("id = " . $db->escape($id))
                ->where("created_by = " . $user->id);

            $db->setQuery($query);

            $results = $db->loadObject();
            if ($results) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $exc) {
            return false;
        }
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
        $app = Factory::getApplication('com_jed');

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_jed.edit.reviewcomment.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_jed.edit.reviewcomment.id', $id);
        }

        $this->setState('reviewcomment.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('reviewcomment.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to get an ojbect.
     *
     * @param int $id The id of the object to get.
     *
     * @return Object|bool Object on success, false on failure.
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function getItem($id = null)
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('reviewcomment.id');
            }

            // Get a level row instance.
            $table      = $this->getTable();
            $properties = $table->getProperties();
            $this->item = ArrayHelper::toObject($properties, stdClass::class);

            if ($table !== false && $table->load($id) && !empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();
                $id   = $table->id;
                if (empty($id) || $this->isAdminOrSuperUser() || $table->created_by == Factory::getApplication()->getIdentity()->id) {
                    $canEdit = $user->authorise('core.edit', 'com_jed') || $user->authorise('core.create', 'com_jed');

                    if (!$canEdit && $user->authorise('core.edit.own', 'com_jed')) {
                        $canEdit = $user->id == $table->created_by;
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

                    // Convert the Table to a clean CMSObject.
                    $properties = $table->getProperties(1);
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
     * Method to get the table
     *
     * @param string $type   Name of the Table class
     * @param string $prefix Optional prefix for the table class name
     * @param array  $config Optional configuration array for Table object
     *
     * @return Table|bool Table if found, bool false on failure
     * @throws Exception
     * @since  4.0.0
     */
    public function getTable($type = 'Reviewcomment', $prefix = 'Administrator', $config = [])
    {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Get an item by alias
     *
     * @param string $alias Alias string
     *
     * @return int Element id
     * @throws Exception
     * @since  4.0.0
     */
    public function getItemIdByAlias($alias)
    {
        $table      = $this->getTable();
        $properties = $table->getProperties();

        if (!in_array('alias', $properties)) {
            return null;
        }

        $table->load(['alias' => $alias]);
        $id = $table->id;

        if (empty($id) || $this->isAdminOrSuperUser() || $table->created_by == Factory::getApplication()->getIdentity()->id) {
            return $id;
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
    }

    /**
     * Method to check in an item.
     *
     * @param int $id The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function checkin($id = null)
    {
        // Get the id.
        $id = (!empty($id)) ? $id : (int) $this->getState('reviewcomment.id');
        if (!$id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($id) {
                // Initialise the table
                $table = $this->getTable();

                // Attempt to check the row in.
                if (method_exists($table, 'checkin')) {
                    if (!$table->checkin($id)) {
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
     * @param int $id The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function checkout($id = null)
    {
        // Get the user id.
        $id = (!empty($id)) ? $id : (int) $this->getState('reviewcomment.id');
        if (!$id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($id) {
                // Initialise the table
                $table = $this->getTable();

                // Get the current user object.
                $user = Factory::getApplication()->getIdentity();

                // Attempt to check the row out.
                if (method_exists($table, 'checkout')) {
                    if (!$table->checkout($user->id, $id)) {
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
    public function getForm($data = [], $loadData = true, $formname = 'jform')
    {
        // Get the form.
        $form = $this->loadForm(
            'com_jed.reviewcomment',
            'reviewcommentform',
            [
                        'control'   => $formname,
                        'load_data' => $loadData,
                ]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return array  The default data is an empty array.
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jed.edit.reviewcomment.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {
            return $data;
        }

        return [];
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
    public function save($data)
    {
        $id    = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('reviewcomment.id');
        $state = (!empty($data['state'])) ? 1 : 0;
        $user  = Factory::getUser();

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
     * @since  4.0.0
     * @throws Exception
     */
    public function delete($id)
    {
        $user = Factory::getUser();

        if (!$id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if (empty($id)) {
                $id = (int) $this->getState('reviewcomment.id');
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
}
