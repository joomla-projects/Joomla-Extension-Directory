<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Filesystem\File;

/**
 * Velvulnerableitem table
 *
 * @since 4.0.0
 */
class VelvulnerableitemTable extends Table
{
    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see   Table::_getAssetName
     * @since 4.0.0
     */
    protected function _getAssetName(): string
    {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int)$this->$k;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array|object  $src     An associative array or object to bind to the Table instance.
     * @param   array|string  $ignore  An optional array or space separated list of properties to ignore while binding.
     *
     * @return  boolean  True on success.
     *
     * @see    Table:bind
     * @since  4.0.0
     * @throws Exception
     */
    public function bind($src, $ignore = '')
    {
        $date = Factory::getDate();
        $task = Factory::getApplication()->input->get('task');

        foreach (['status', 'report_id', 'risk_level', 'exploit_type', 'xml_manifest', 'discoverer_public'] as $field) {
            if (isset($src[$field]) && $src[$field]) {
                if (is_array($src[$field])) {
                    $src[$field] = implode(',', $src[$field]);
                }
            } else {
                $src[$field] = '';
            }
        }


        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($src['id'] == 0 && empty($src['modified_by'])) {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($src['id'] == 0) {
            $src['created'] = $date->toSql();
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified'] = $date->toSql();
        }

        return parent::bind($src, $ignore);
    }

    /**
     * Overloaded check function
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function check(): bool
    {
        // Support multi file field: xml_manifest
        $app   = Factory::getApplication();
        $files = $app->input->files->get('jform', [], 'raw');
        $array = $app->input->get('jform', [], 'ARRAY');

        if ($files['xml_manifest'][0]['size'] > 0) {
            // Deleting existing files
            $oldFiles = JedHelper::getFiles($this->id, $this->_tbl, 'xml_manifest');

            foreach ($oldFiles as $f) {
                $oldFile = JPATH_ROOT . '/tmp/' . $f;

                if (file_exists($oldFile) && !is_dir($oldFile)) {
                    unlink($oldFile);
                }
            }

            $this->xml_manifest = "";

            foreach ($files['xml_manifest'] as $singleFile) {
                // Check if the server found any error.
                $fileError = $singleFile['error'];
                $message   = '';

                if ($fileError > 0 && $fileError != 4) {
                    switch ($fileError) {
                        case 1:
                            $message = Text::_('File size exceeds allowed by the server');
                            break;
                        case 2:
                            $message = Text::_('File size exceeds allowed by the html form');
                            break;
                        case 3:
                            $message = Text::_('Partial upload error');
                            break;
                    }

                    if ($message != '') {
                        $app->enqueueMessage($message, 'warning');

                        return false;
                    }
                } elseif ($fileError == 4) {
                    if (isset($array['xml_manifest'])) {
                        $this->xml_manifest = $array['xml_manifest'];
                    }
                } else {
                    // Check for filesize
                    $fileSize = $singleFile['size'];

                    if ($fileSize > 1048576) {
                        $app->enqueueMessage('File bigger than 1MB', 'warning');

                        return false;
                    }

                    // Replace any special characters in the filename

                    $filename   = File::stripExt($singleFile['name']);
                    $extension  = File::getExt($singleFile['name']);
                    $filename   = preg_replace("/[^A-Za-z0-9]/i", "-", $filename);
                    $filename   = $filename . '.' . $extension;
                    $uploadPath = JPATH_ROOT . '//tmp/' . $filename;
                    $fileTemp   = $singleFile['tmp_name'];

                    if (!file_exists($uploadPath)) {
                        if (!File::upload($fileTemp, $uploadPath)) {
                            $app->enqueueMessage('Error moving file', 'warning');

                            return false;
                        }
                    }
                    $xml_man            = $this->xml_manifest;
                    $this->xml_manifest = $xml_man .= (!empty($xml_man)) ? "," : "";
                    $xml_man            = $this->xml_manifest;
                    $this->xml_manifest = $xml_man .= $filename;
                }
            }
        } else {
            $xml_man            = $this->xml_manifest;
            $this->xml_manifest = $xml_man .= $array['xml_manifest_hidden'];
        }

        return parent::check();
    }

    /**
     * Delete a record by id
     *
     * @param mixed $pk Primary key value to delete. Optional
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function delete($pk = null): bool
    {
        $this->load($pk);
        $result = parent::delete($pk);

        if ($result) {
            $checkImageVariableType = gettype($this->xml_manifest);

            switch ($checkImageVariableType) {
                case 'string':
                    File::delete(JPATH_ROOT . '/tmp/' . $this->xml_manifest);
                    break;
                default:
                    foreach ($this->xml_manifest as $xml_manifestFile) {
                        File::delete(JPATH_ROOT . '/tmp/' . $xml_manifestFile);
                    }
            }
        }

        return $result;
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * @param bool $updateNulls True to update fields even if they are null.
     *
     * @return bool  True on success.
     *
     * @since 4.0.0
     */
    public function store($updateNulls = true): bool
    {
        return parent::store($updateNulls);
    }

    /**
     * Get the type alias for the history table
     *
     * @return string  The alias as described above
     *
     * @since 4.0.0
     */
    public function getTypeAlias(): string
    {
        return $this->typeAlias;
    }

    /**
     * Constructor
     *
     * @param DatabaseDriver $db A database connector object
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_jed.velvulnerableitem';
        parent::__construct('#__jed_vel_vulnerable_item', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }
}
