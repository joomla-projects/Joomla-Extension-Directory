<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

/**
 * VEL Abandoned Report model.
 *
 * @since 4.0.0
 */
class VelabandonedreportModel extends AdminModel
{
    /**
     * @var   string    Alias to manage history control
     * @since 4.0.0
     */
    public $typeAlias = 'com_jed.velabandonedreport';
    /**
     * @var   string    The prefix to use with controller messages.
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_JED';
    /**
     * @var   mixed  Item data
     * @since 4.0.0
     */
    protected mixed $item = null;

    /**
     * Method to get the record form.
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form|bool  A Form object on success, false on failure
     *
     * @since 4.0.0
     *
     * @throws
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form|bool
    {

        // Get the form.
        $form = $this->loadForm(
            'com_jed.velabandonedreport',
            'velabandonedreport',
            ['control'        => $formname,
                  'load_data' => $loadData,
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
     * @param int $pk The id of the primary key.
     *
     * @return mixed    Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem($pk = null): mixed
    {
        return parent::getItem($pk);
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
    public function getTable($name = 'Velabandonedreport', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed  The data for the form.
     *
     * @since 4.0.0
     *
     * @throws
     */
    protected function loadFormData(): mixed
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jed.edit.velabandonedreport.data', []);

        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;


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
        }

        return $data;
    }
}
