<?php

/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * VEL Developer Update Form Controller Class.
 *
 * @since 4.0.0
 */
class DeveloperupdateformController extends FormController
{
    /**
     * Method to abort current operation
     *
     * @param null $key
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function cancel($key = null): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Get the current edit id.
        $editId = (int) $app->getUserState('com_vel.edit.developerupdate.id');

        // Get the model.
        $model = $this->getModel('Developerupdateform', 'Site');

        // Check in the item
        if ($editId) {
            $model->checkin($editId);
        }

        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        $url  = (empty($item->link) ? 'index.php?option=com_vel&view=developerupdates' : $item->link);
        $this->setRedirect(Route::_($url, false));
    }

    /**
     * Method to check out an item for editing and redirect to the edit form.
     *
     * @param null $key
     * @param null $urlVar
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function edit($key = null, $urlVar = null): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $app->getUserState('com_vel.edit.developerupdate.id');
        $editId     = $app->input->getInt('id', 0);

        // Set the user id for the user to edit in the session.
        $app->setUserState('com_vel.edit.developerupdate.id', $editId);

        // Get the model.
        $model = $this->getModel('Developerupdateform', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_vel&view=developerupdateform&layout=edit', false));
    }

    /**
     * Method to remove data
     *
     * No one should be removing data from the front end so this is commented out.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function remove()
    {
        /*$app   = Factory::getApplication();
        $model = $this->getModel('Developerupdateform', 'Site');
        $pk    = $app->input->getInt('id');

        // Attempt to save the data
        try
        {
            $return = $model->delete($pk);

            // Check in the profile
            $model->checkin($return);

            // Clear the profile id from the session.
            $app->setUserState('com_vel.edit.developerupdate.id', null);

            $menu = $app->getMenu();
            $item = $menu->getActive();
            $url  = (empty($item->link) ? 'index.php?option=com_vel&view=developerupdates' : $item->link);

            // Redirect to the list screen
            $this->setMessage(Text::_('COM_VEL_ITEM_DELETED_SUCCESSFULLY'));
            $this->setRedirect(Route::_($url, false));

            // Flush the data from the session.
            $app->setUserState('com_vel.edit.developerupdate.data', null);
        }
        catch (Exception $e)
        {
            $errorType = ($e->getCode() == '404') ? 'error' : 'warning';
            $this->setMessage($e->getMessage(), $errorType);
            $this->setRedirect('index.php?option=com_vel&view=developerupdates');
        }*/
    }

    /**
     * Method to save data.
     *
     * All logged-in users can create Developer Updates
     *
     * @param null $key
     * @param null $urlVar
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

        // Initialise variables.
        $app   = Factory::getApplication();
        $model = $this->getModel('Developerupdateform', 'Site');

        // Get the user data.
        $data = Factory::getApplication()->input->get('jform', [], 'array');

        // Validate the posted data.
        $form = $model->getForm();

        if (!$form) {
            throw new Exception('Could not validate data', 500);
        }

        // Validate the posted data.
        $data = $model->validate($form, $data);

        // Check for errors.
        if ($data === false) {
            $this->app->enqueueMessage('An error occured saving your data. Please go back and try again', 'warning');

            $jform = $this->input->get('jform', [], 'ARRAY');

            // Save the data in the session.
            $app->setUserState('com_vel.edit.developerupdate.data', $jform);

            // Redirect back to the edit screen.
            $id = (int) $app->getUserState('com_vel.edit.developerupdate.id');
            $this->setRedirect(Route::_('index.php?option=com_vel&view=developerupdateform&layout=edit&id=' . $id, false));

            $this->redirect();
        }

        // Attempt to save the data.
        $return = $model->save($data);

        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $app->setUserState('com_vel.edit.developerupdate.data', $data);

            // Redirect back to the edit screen.
            $id = (int) $app->getUserState('com_vel.edit.developerupdate.id');
            $this->setMessage(Text::_('Save failed'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_vel&view=developerupdateform&layout=edit&id=' . $id, false));
        }

        // Check in the profile.
        if ($return) {
            $model->checkin($return);
        }

        // Clear the profile id from the session.
        $app->setUserState('com_vel.edit.developerupdate.id', null);

        // Redirect to the list screen.
        $this->setMessage(Text::_('COM_VEL_DEVELOPERUPDATES_UPDATE_SAVED_SUCCESSFULLY_REPLY'));
        $url = 'index.php?option=com_vel&view=tickets';
        $this->setRedirect(Route::_($url, false));


        // Flush the data from the session.
        $app->setUserState('com_vel.edit.developerupdate.data', null);
    }
}
