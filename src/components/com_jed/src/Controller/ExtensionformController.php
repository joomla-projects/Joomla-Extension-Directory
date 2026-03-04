<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

use function defined;

/**
 * Extension class.
 *
 * @since 4.0.0
 */
class ExtensionformController extends FormController
{
    /**
     * Method to check out an item for editing and redirect to the edit form.
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function edit($key = null, $urlVar = null): void
    {
        $app = Factory::getApplication();

        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $app->getUserState('com_jed.edit.extension.id');
        $editId     = $app->input->getInt('id', 0);

        // Set the user id for the user to edit in the session.
        $app->setUserState('com_jed.edit.extension.id', $editId);

        // Get the model.
        $model = $this->getModel('Extensionform', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_jed&view=extensionform&layout=edit', false));
    }

    /**
     * Method to save data.
     *
     * @return void
     *
     * @since 4.0.0
     * @throws Exception
     */
    public function save($key = null, $urlVar = null): void
    {
        // Check for request forgeries.
        $this->checkToken();
        $isLoggedIn         = JedHelper::isLoggedIn();

        if ($isLoggedIn) {
            // Initialise variables.
            $app   = Factory::getApplication();
            $model = $this->getModel('Extensionform', 'Site');

            // Raw payload (includes subforms)
            $dataRaw = $app->input->get('jform', [], 'array');

            // Keep the tabbed varied payload aside (main form validation doesn't know about it)
            $supplyPayload = $dataRaw['supply'] ?? [];

            // Translate/Fill out default values for the MAIN extension record
            $dataRaw['joomla_versions'] = json_encode($dataRaw['joomla_versions'] ?? []);
            $dataRaw['includes']        = json_encode($dataRaw['includes'] ?? []);

            if (($dataRaw['download_integration_type'] ?? null) == 2) {
                $dataRaw['requires_registration'] = 1;
            } else {
                $dataRaw['requires_registration'] = 0;
            }

            $dataRaw['can_update']  = $dataRaw['uses_updater'] ?? 0;
            $dataRaw['popular']     = 0;
            $dataRaw['approved']    = 0;
            $dataRaw['jed_checked'] = 0;
            $uploadedExtensionFiles = [];

            // Handle main listing logo upload and set the stored relative path on the extension record.
            $dataRaw = $this->processLogoUpload($dataRaw, $model);

            // Handle supply-tab zip uploads and map stored names back into payload.
            $uploadedExtensionFiles = $this->processSupplyFileUploads($supplyPayload);

            // Validate the posted data. (MAIN form only)
            $form = $model->getForm();

            if (!$form) {
                throw new Exception($model->getError(), 500);
            }
            // Validate the posted data.

            $data = $model->validate($form, $dataRaw);

            // Check for errors.
            if ($data === false) {
                $errors = $model->getErrors();

                for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                    $app->enqueueMessage(
                        $errors[$i] instanceof Exception ? $errors[$i]->getMessage() : $errors[$i],
                        'warning'
                    );
                }

                $app->setUserState('com_jed.edit.extension.data', $dataRaw);

                $id = (int) $app->getUserState('com_jed.edit.extension.id');
                $this->setRedirect(Route::_('index.php?option=com_jed&view=extensionform&layout=edit&id=' . $id, false));
                $this->redirect();
            }

            // Re-attach varied payload so the model can store it after saving the parent extension
            $data['supply'] = $supplyPayload;

            // Ensure the ID is set for edit operations
            $editId = (int) $app->getUserState('com_jed.edit.extension.id');
            if ($editId > 0) {
                $data['id'] = $editId;
            }

            $return = $model->save($data);

            if ($return === false) {
                $app->setUserState('com_jed.edit.extension.data', $data);
                $id = (int) $app->getUserState('com_jed.edit.extension.id');
                $this->setMessage(Text::sprintf('Save failed', $model->getError()), 'warning');
                $this->setRedirect(Route::_('index.php?option=com_jed&view=extensionform&layout=edit&id=' . $id, false));
                return;
            }

            if ($return) {
                $model->storeExtensionFiles(
                    (int) $return,
                    $uploadedExtensionFiles,
                    (int) Factory::getApplication()->getIdentity()->id
                );
                $model->checkin($return);
            }

            $app->setUserState('com_jed.edit.extension.id', null);
            $this->setMessage(Text::_('COM_JED_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));

            $menu = Factory::getApplication()->getMenu();
            $item = $menu->getActive();
            $url  = 'index.php?option=com_jed&view=controlpanel';
            $this->setRedirect(Route::_($url, false));

            $app->setUserState('com_jed.edit.extension.data', null);
            return;
        }

        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }

    /**
     * Save uploaded logo image into a dedicated images folder.
     *
     * @param array $dataRaw
     * @param mixed $model
     *
     * @return array
     *
     * @throws Exception
      * @since 4.0.0
     */
    private function processLogoUpload(array $dataRaw, mixed $model): array
    {
        $logoUpload  = $this->getJformUpload('logo');
        $extensionId = (int) ($dataRaw['id'] ?? 0);

        if (($logoUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            // Preserve current logo when editing and no new file is provided.
            if ($extensionId > 0 && empty($dataRaw['logo'])) {
                $item = $model->getItem($extensionId);
                if (!empty($item->logo)) {
                    $dataRaw['logo'] = $item->logo;
                }
            }

            return $dataRaw;
        }

        if (($logoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($logoUpload['tmp_name'])) {
            throw new Exception(Text::_('COM_JED_GENERAL_ITEM_SAVED_UNSUCCESSFULLY_LABEL'));
        }

        $stored = $this->storeUploadedFile(
            $logoUpload,
            'images/jed/extensions/logos',
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            'logo'
        );

        if ($stored !== null) {
            $dataRaw['logo'] = $stored;
        }

        return $dataRaw;
    }

    /**
     * Save uploaded extension zip files from supply tabs.
     *
     * @param array $supplyPayload
     *
     * @return array
     *
     * @throws Exception
      * @since 4.0.0
     */
    private function processSupplyFileUploads(array &$supplyPayload): array
    {
        $uploadedExtensionFiles = [];

        foreach ($supplyPayload as $supplyKey => &$row) {
            if (!is_array($row)) {
                continue;
            }

            $upload = $this->getJformUpload('file', (string) $supplyKey);

            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($upload['tmp_name'])) {
                throw new Exception(Text::_('COM_JED_GENERAL_ITEM_SAVED_UNSUCCESSFULLY_LABEL'));
            }

            $stored = $this->storeUploadedFile(
                $upload,
                'files/jed/extensions/zips',
                ['zip'],
                'extension'
            );

            if ($stored === null) {
                continue;
            }

            $row['file'] = $stored;

            $uploadedExtensionFiles[] = [
                'supply_key'    => (string) $supplyKey,
                'supply_option' => (int) ($row['supply_option_id'] ?? 0),
                'file'          => $stored,
                'originalFile'  => (string) ($upload['name'] ?? ''),
                'size'          => (int) ($upload['size'] ?? 0),
            ];
        }
        unset($row);

        return $uploadedExtensionFiles;
    }

    /**
     * Get one uploaded jform file from the nested $_FILES structure.
     *
     * @param string      $field
     * @param string|null $supplyKey
     *
     * @return array|null
      * @since 4.0.0
     */
    private function getJformUpload(string $field, ?string $supplyKey = null): ?array
    {
        if (empty($_FILES['jform']) || !is_array($_FILES['jform'])) {
            return null;
        }

        $root = $_FILES['jform'];

        if ($supplyKey === null) {
            return [
                'name'     => $root['name'][$field] ?? '',
                'type'     => $root['type'][$field] ?? '',
                'tmp_name' => $root['tmp_name'][$field] ?? '',
                'error'    => $root['error'][$field] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $root['size'][$field] ?? 0,
            ];
        }

        return [
            'name'     => $root['name']['supply'][$supplyKey][$field] ?? '',
            'type'     => $root['type']['supply'][$supplyKey][$field] ?? '',
            'tmp_name' => $root['tmp_name']['supply'][$supplyKey][$field] ?? '',
            'error'    => $root['error']['supply'][$supplyKey][$field] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $root['size']['supply'][$supplyKey][$field] ?? 0,
        ];
    }

    /**
     * Store a single uploaded file in a relative target folder.
     *
     * @param array $upload
     * @param string $targetRelativeDir
     * @param array $allowedExtensions
     * @param string $prefix
     *
     * @return string|null
     *
     * @throws Exception
      * @since 4.0.0
     */
    private function storeUploadedFile(array $upload, string $targetRelativeDir, array $allowedExtensions, string $prefix): ?string
    {
        $originalName = (string) ($upload['name'] ?? '');
        $extension    = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            return null;
        }

        $targetDir = JPATH_ROOT . '/' . trim($targetRelativeDir, '/');

        if (!Folder::exists($targetDir)) {
            Folder::create($targetDir);
        }

        $safeBase = File::makeSafe((string) pathinfo($originalName, PATHINFO_FILENAME));
        $safeBase = $safeBase !== '' ? OutputFilter::stringURLSafe($safeBase) : $prefix;
        $unique   = date('YmdHis') . '_' . substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
        $fileName = $prefix . '_' . $safeBase . '_' . $unique . '.' . $extension;

        $targetPath = $targetDir . '/' . $fileName;

        if (!File::upload((string) $upload['tmp_name'], $targetPath)) {
            throw new Exception(Text::_('COM_JED_GENERAL_ITEM_SAVED_UNSUCCESSFULLY_LABEL'));
        }

        return trim($targetRelativeDir, '/') . '/' . $fileName;
    }


    /**
     * Method to abort current operation
     *
     * @return void
     *
     * @since 4.0.0
     * @throws Exception
     */
    public function cancel($key = null): void
    {
        $app = Factory::getApplication();

        // Get the current edit id.
        $editId = (int) $app->getUserState('com_jed.edit.extension.id');

        // Get the model.
        $model = $this->getModel('Extensionform', 'Site');

        // Check in the item
        if ($editId) {
            $model->checkin($editId);
        }

        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        $url  = (empty($item->link) ? 'index.php?option=com_jed&view=extensions' : $item->link);
        $this->setRedirect(Route::_($url, false));
    }

    /**
     * Method to remove data
     *
     * @return void
     *
     * @since 4.0.0
     * @throws Exception
     */
    public function remove()
    {
        $app   = Factory::getApplication();
        $model = $this->getModel('Extensionform', 'Site');
        $pk    = $app->input->getInt('id');

        // Attempt to save the data
        try {
            $return = $model->delete($pk);

            // Check in the profile
            $model->checkin($return);

            // Clear the profile id from the session.
            $app->setUserState('com_jed.edit.extension.id', null);

            $menu = $app->getMenu();
            $item = $menu->getActive();
            $url  = (empty($item->link) ? 'index.php?option=com_jed&view=extensions' : $item->link);

            // Redirect to the list screen
            $this->setMessage(Text::_('COM_JED_ITEM_DELETED_SUCCESSFULLY'));
            $this->setRedirect(Route::_($url, false));

            // Flush the data from the session.
            $app->setUserState('com_jed.edit.extension.data', null);
        } catch (Exception $e) {
            $errorType = ($e->getCode() == '404') ? 'error' : 'warning';
            $this->setMessage($e->getMessage(), $errorType);
            $this->setRedirect('index.php?option=com_jed&view=extensions');
        }
    }
}
