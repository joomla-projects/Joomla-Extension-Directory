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
use Joomla\CMS\Access\Access;
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


        // Support for multiple field: status
        if (isset($src['status'])) {
            if (is_array($src['status'])) {
                $src['status'] = implode(',', $src['status']);
            } elseif (strpos($src['status'], ',')) {
                $src['status'] = explode(',', $src['status']);
            } elseif (strlen($src['status']) == 0) {
                $src['status'] = '';
            }
        } else {
            $src['status'] = '';
        }

        // Support for multiple or not foreign key field: report_id
        if (!empty($src['report_id'])) {
            if (is_array($src['report_id'])) {
                $src['report_id'] = implode(',', $src['report_id']);
            } elseif (strrpos($src['report_id'], ',')) {
                $src['report_id'] = explode(',', $src['report_id']);
            }
        } else {
            $src['report_id'] = '';
        }

        // Support for multiple field: risk_level
        if (isset($src['risk_level'])) {
            if (is_array($src['risk_level'])) {
                $src['risk_level'] = implode(',', $src['risk_level']);
            } elseif (strpos($src['risk_level'], ',')) {
                $src['risk_level'] = explode(',', $src['risk_level']);
            } elseif (strlen($src['risk_level']) == 0) {
                $src['risk_level'] = '';
            }
        } else {
            $src['risk_level'] = '';
        }

        // Support for multiple field: exploit_type
        if (isset($src['exploit_type'])) {
            if (is_array($src['exploit_type'])) {
                $src['exploit_type'] = implode(',', $src['exploit_type']);
            } elseif (strpos($src['exploit_type'], ',')) {
                $src['exploit_type'] = explode(',', $src['exploit_type']);
            } elseif (strlen($src['exploit_type']) == 0) {
                $src['exploit_type'] = '';
            }
        } else {
            $src['exploit_type'] = '';
        }
        // Support for multi file field: xml_manifest
        if (!empty($src['xml_manifest'])) {
            if (is_array($src['xml_manifest'])) {
                $src['xml_manifest'] = implode(',', $src['xml_manifest']);
            } elseif (strpos($src['xml_manifest'], ',')) {
                $src['xml_manifest'] = explode(',', $src['xml_manifest']);
            }
        } else {
            $src['xml_manifest'] = '';
        }


        // Support for multiple field: discoverer_public
        if (isset($src['discoverer_public'])) {
            if (is_array($src['discoverer_public'])) {
                $src['discoverer_public'] = implode(',', $src['discoverer_public']);
            } elseif (strpos($src['discoverer_public'], ',')) {
                $src['discoverer_public'] = explode(',', $src['discoverer_public']);
            } elseif (strlen($src['discoverer_public']) == 0) {
                $src['discoverer_public'] = '';
            }
        } else {
            $src['discoverer_public'] = '';
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


        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_jed.velvulnerableitem.' . $src['id'])) {
            $actions         = Access::getActionsFromFile(
                JPATH_ADMINISTRATOR . '/components/com_jed/access.xml',
                "/access/section[@name='velvulnerableitem']/"
            );
            $default_actions = Access::getAssetRules('com_jed.velvulnerableitem.' . $src['id'])->getData();
            $array_jaccess   = [];

            foreach ($actions as $action) {
                if (key_exists($action->name, $default_actions)) {
                    $array_jaccess[$action->name] = $default_actions[$action->name];
                }
            }

            $src['rules'] = $this->JAccessRulestoArray($array_jaccess);
        }

        // Bind the rules for ACL where supported.
        if (isset($src['rules']) && is_array($src['rules'])) {
            $this->setRules($src['rules']);
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
     * This function convert an array of Access objects into an rules array.
     *
     * @param array $jaccessrules An array of Access objects.
     *
     * @return array
     * @since  4.0.0
     */
    private function JAccessRulestoArray(array $jaccessrules): array
    {
        $rules = [];

        foreach ($jaccessrules as $action => $jaccess) {
            $actions = [];

            if ($jaccess) {
                foreach ($jaccess->getData() as $group => $allow) {
                    $actions[$group] = ((bool)$allow);
                }
            }

            $rules[$action] = $actions;
        }

        return $rules;
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
