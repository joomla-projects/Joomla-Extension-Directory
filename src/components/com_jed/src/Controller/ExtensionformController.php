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
     * @since  4.0.0
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

            // Get the user data.
            $data = $app->input->get('jform', [], 'array');
            $file = $_FILES;

            //Translate/Fill out default values
            $data['joomla_versions'] = json_encode($data['joomla_versions']);
            $data['includes']        = json_encode($data['includes']);
            if ($data['download_integration_type'] == 2) {
                $data['requires_registration'] = 1;
            } else {
                $data['requires_registration'] = 0;
            }
            $data['can_update']  = $data['uses_updater'];
            $data['popular']     = 0;
            $data['approved']    = 0;
            $data['jed_checked'] = 0;
            $data['alias']       = OutputFilter::stringUrlSafe($data['title']);
            $data['intro_text']  = '????'; // look this up in JED3


            echo "<pre>";
            print_r($data);
            echo "<br/><br/><br/>";
            print_r($file);
            echo "</pre>";
            exit();
            // Validate the posted data.
            $form = $model->getForm();

            if (!$form) {
                throw new Exception($model->getError(), 500);
            }
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }






        // Validate the posted data.
        $data = $model->validate($form, $data);

        // Check for errors.
        if ($data === false) {
            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof Exception) {
                    $app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $app->enqueueMessage($errors[$i], 'warning');
                }
            }

            $input = $app->input;
            $jform = $input->get('jform', [], 'ARRAY');

            // Save the data in the session.
            $app->setUserState('com_jed.edit.extension.data', $jform);

            // Redirect back to the edit screen.
            $id = (int) $app->getUserState('com_jed.edit.extension.id');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extensionform&layout=edit&id=' . $id, false));

            $this->redirect();
        }

        // Attempt to save the data.
        $return = $model->save($data);

        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $app->setUserState('com_jed.edit.extension.data', $data);

            // Redirect back to the edit screen.
            $id = (int) $app->getUserState('com_jed.edit.extension.id');
            $this->setMessage(Text::sprintf('Save failed', $model->getError()), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extensionform&layout=edit&id=' . $id, false));
        }

        // Check in the profile.
        if ($return) {
            $model->checkin($return);
        }

        // Clear the profile id from the session.
        $app->setUserState('com_jed.edit.extension.id', null);

        // Redirect to the list screen.
        $this->setMessage(Text::_('COM_JED_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));
        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        $url  = (empty($item->link) ? 'index.php?option=com_jed&view=extensions' : $item->link);
        $this->setRedirect(Route::_($url, false));

        // Flush the data from the session.
        $app->setUserState('com_jed.edit.extension.data', null);
    }

    /**
     * Method to abort current operation
     *
     * @return void
     *
     * @since  4.0.0
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
     * @since  4.0.0
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
