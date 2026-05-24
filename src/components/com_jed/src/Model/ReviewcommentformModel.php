<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * Jed model.
 *
 * @since 4.0.0
 */
class ReviewcommentformModel extends FormModel
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
     * @var   string
     * @since 4.0.0
     **/
    private string $dbtable = "#__jed_reviews_comments";

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
            'com_jed.reviewcomment',
            'reviewcommentform',
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
     * @param string $name    Name of the Table class
     * @param string $prefix  Optional prefix for the table class name
     * @param array  $options Optional configuration array for Table object
     *
     * @return Table|bool Table if found, bool false on failure
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Reviewcomment', $prefix = 'Administrator', $options = []): Table|bool
    {

        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed The default data is an empty array.
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData(): mixed
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
        if ($app->input->get('layout') == 'edit') {
            $id = $app->getUserState('com_jed.edit.reviewcomment.id');
        } else {
            $id = $app->input->get('id');
            $app->setUserState('com_jed.edit.reviewcomment.id', $id);
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
     * @param int|null $id The id of the object to get.
     *
     * @return object|bool Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem(int $id = null): mixed
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('reviewcomment.id');
            }

            // Get a level row instance.
            $table      = $this->getTable();
            $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

            if ($table !== false && $table->load($id) && ! empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();
                $id   = $table->id;
                if (empty($id) || JedHelper::isAdminOrSuperUser() || $table->created_by == $user->id) {
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
        $pk = (! empty($pk)) ? $pk : (int)$this->getState('reviewcomment.id');
        if (! $pk || JedHelper::userIDItem($pk, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
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
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
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
        $pk = (! empty($pk)) ? $pk : (int)$this->getState('reviewcomment.id');
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
        $id                 = (! empty($data['id'])) ? $data['id'] : (int)$this->getState('reviewcomment.id');
        $state              = (! empty($data['state'])) ? 1 : 0;
        $isLoggedIn         = JedHelper::isLoggedIn();
        $user               = Factory::getApplication()->getIdentity();


        if (! $id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($id) {
                // Check the user can edit this item
                $authorised = $user->authorise('core.edit', 'com_jed') || $authorised = $user->authorise(
                    'core.edit.own',
                    'com_jed'
                );
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
     * @param int $id Item primary key
     *
     * @return int  The id of the deleted item
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function delete(int $id): int
    {
        $user = $this->getCurrentUser();

        if (! $id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if (empty($id)) {
                $id = (int)$this->getState('reviewcomment.id');
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
