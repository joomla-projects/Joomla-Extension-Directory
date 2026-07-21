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
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Jed\Component\Tickets\Administrator\Traits\TicketHandlingTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * VEL Developer Update Controller Class.
 *
 * @since 4.0.0
 */
class DeveloperupdateController extends FormController
{
    use TicketHandlingTrait;

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
        // Get the current edit id.
        $editId = (int) $this->app->getUserState('com_vel.edit.developerupdate.id');

        // Get the model.
        $model = $this->getModel('Developerupdate', 'Site');

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
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function edit(): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $app->getUserState('com_vel.edit.developerupdate.id');
        $editId     = $app->input->getInt('id', 0);

        // Set the user id for the user to edit in the session.
        $app->setUserState('com_vel.edit.developerupdate.id', $editId);

        // Get the model.
        $model = $this->getModel('Developerupdate', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId && $previousId !== $editId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_vel&view=developerupdate&layout=edit', false));
    }

    /**
     * Method to save data.
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
        $model = $this->getModel('Developerupdate', 'Site');

        // Get the user data.
        $data = $this->input->get('jform', [], 'array');

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
            $this->app->setUserState('com_vel.edit.developerupdate.data', $jform);

            // Redirect back to the edit screen.
            $id = (int) $this->app->getUserState('com_vel.edit.developerupdate.id');
            $this->setRedirect(Route::_('index.php?option=com_vel&view=developerupdate&layout=edit&id=' . $id, false));

            $this->redirect();
        }

        // Attempt to save the data.
        $return = $model->save($data);

        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $this->app->setUserState('com_vel.edit.developerupdate.data', $data);

            // Redirect back to the edit screen.
            $id = (int) $this->app->getUserState('com_vel.edit.developerupdate.id');
            $this->setMessage(Text::_('Save failed'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_vel&view=developerupdate&layout=edit&id=' . $id, false));
            $this->redirect();
        }

        // Check in the profile.
        if ($return) {
            $model->checkin($return);
        }

        // Clear the profile id from the session.
        $this->app->setUserState('com_vel.edit.developerupdate.id', null);

        // Redirect to the list screen.
        if (!empty($return)) {
            $this->setMessage(Text::_('COM_VEL_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));

            $this->triggerTicket(
                TicketType::VulnerableExtension,
                (int) $return,
                Text::sprintf('COM_VEL_TICKET_NEW_DEVELOPERUPDATE_EVENT', $return)
            );
        }
        $url = 'index.php?option=com_vel&view=tickets';
        $this->setRedirect(Route::_($url, false));

        // Flush the data from the session.
        $this->app->setUserState('com_vel.edit.developerupdate.data', null);

        // Invoke the postSave method to allow for the child class to access the model.
        $this->postSaveHook($model, $data);
    }

    /**
     * Method to publish/unpublish an item (admin action from the ticket master-data view).
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function publish(): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        $user = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.edit', 'com_vel') || $user->authorise('core.edit.state', 'com_vel')) {
            $model = $this->getModel('Developerupdate', 'Site');

            $id    = $app->input->getInt('id');
            $state = $app->input->getInt('state');

            $return = $model->publish($id, $state);

            if ($return === false) {
                $this->setMessage(Text::_('Save failed'), 'warning');
            }

            $app->setUserState('com_vel.edit.developerupdate.id', null);
            $app->setUserState('com_vel.edit.developerupdate.data', null);

            $this->setMessage(Text::_('COM_VEL_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));
            $menu = Factory::getApplication()->getMenu();
            $item = $menu->getActive();

            if (!$item) {
                $this->setRedirect(Route::_('index.php?option=com_vel&view=developerupdates', false));
            } else {
                $this->setRedirect(Route::_('index.php?Itemid=' . $item->id, false));
            }
        } else {
            throw new Exception(500);
        }
    }
}
