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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Registry\Registry;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * Extensionvarieddatum model.
 *
 * @since  4.0.0
 */
class ExtensionvarieddatumModel extends FormModel
{
    /**
     * @var    string  Alias to manage history control
     *
     * @since  4.0.0
     */
    public $typeAlias = 'com_jed.extensionvarieddatum';
    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  4.0.0
     */
    protected $text_prefix = 'COM_JED';
    /**
     * @var    mixed  Item data
     *
     * @since  4.0.0
     */
    protected mixed $item = null;


    /**
     * Method to get the record form.
     *
     * @param   array    $data      An optional array of data for the form to interogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|bool  A Form object on success, false on failure
     *
     * @throws Exception
     * @since   4.0.0
     *
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form|bool
    {
        // Get the form.
        $form = $this->loadForm(
            'com_jed.extensionvarieddatum',
            'extensionvarieddatum',
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
     * Method to get the empty record form.
     *

     *
     * @return  Form|bool  A Form object on success, false on failure
     *
     * @throws Exception
     * @since   4.0.0
     *
     */
    public function getFormTemplate(): Form|bool
    {
        // Get the form.
        $form = $this->loadForm(
            'com_jed.extensionvarieddatum',
            'extensionvarieddatum',
            [
                'control'   => 'jform_extensionvarieddatum',
                'load_data' => false,
            ]
        );


        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get a single record.
     *
     * @param   null  $pk  The id of the primary key.
     *
     * @return stdClass Object on success
     *
     * @throws Exception
     * @since   4.0.0
     *
     */
    public function getItem($pk = null): stdClass
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($pk)) {
                $pk = $this->getState('extensionvarieddatum.id');
            }

            // Get a level row instance.
            $table      = $this->getTable();
            $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

            if ($table !== false && $table->load($pk) && !empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();
                $pk   = $table->id;
                if (empty($pk) || JedHelper::isAdminOrSuperUser() || $table->created_by == Factory::getUser()->id) {
                    $canEdit = $user->authorise('core.edit', 'com_jed') || $user->authorise('core.create', 'com_jed');

                    if (!$canEdit && $user->authorise('core.edit.own', 'com_jed')) {
                        $canEdit = $user->id == $table->created_by;
                    }

                    if (!$canEdit) {
                        throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
                    }

                    // Check published state.
                    if ($published = $this->getState('filter.published')) {
                        if (isset($table->state) && $table->state != $published) {
                            return $this->item;
                        }
                    }

                    // Convert the Table to a clean CMSObject.
                    $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

                    if (isset($this->item->catid) && is_object($this->item->catid)) {
                        $this->item->catid = ArrayHelper::fromObject($this->item->catid);
                    }
                } else {
                    throw new \Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
                }
            }
        }

        return $this->item;
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string  $name     The table type to instantiate
     * @param   string  $prefix  A prefix for the table class name. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table    A database object
     *
     * @throws Exception
     * @since   4.0.0
     *
     */
    public function getTable($name = 'Extensionvarieddatum', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @throws Exception
     * @since   4.0.0
     *
     */
    protected function loadFormData(): mixed
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jed.edit.extensionvarieddatum.data', []);

        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;
        }

        return $data;
    }

    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param   Table  $table  Table Object
     *
     * @return  void
     *
     * @since   4.0.0
     */
    protected function prepareTable($table)
    {
    }
}
