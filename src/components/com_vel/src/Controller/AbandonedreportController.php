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
 * Vel Abandoned Report Form controller class.
 *
 * @since 4.0.0
 */
class AbandonedreportController extends FormController
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
        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        $url  = (empty($item->link) ? 'index.php?option=com_vel&view=abandoneditems' : $item->link);
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
        // Get the current edit id.
        $editId = $this->input->getInt('id', 0);

        // Set the user id for the user to edit in the session.
        $this->app->setUserState('com_vel.edit.abandonedreport.id', $editId);

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_vel&view=abandonedreport&layout=edit', false));
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
        $model = $this->getModel('Abandonedreport', 'Site');

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
            $this->app->setUserState('com_vel.edit.abandonedreport.data', $jform);

            // Redirect back to the edit screen.
            $id = (int) $this->app->getUserState('com_vel.edit.abandonedreport.id');
            $this->setRedirect(Route::_('index.php?option=com_vel&view=abandonedreport&layout=edit&id=' . $id, false));

            $this->redirect();
        }

        // Attempt to save the data.
        $return = $model->save($data);

        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $this->app->setUserState('com_vel.edit.abandonedreport.data', $data);

            // Redirect back to the edit screen.
            $id = (int) $this->app->getUserState('com_vel.edit.abandonedreport.id');
            $this->setMessage(Text::_('Save failed'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_vel&view=abandonedreport&layout=edit&id=' . $id, false));
            $this->redirect();
        }

        // Clear the profile id from the session.
        $this->app->setUserState('com_vel.edit.abandonedreport.id', null);

        // Redirect to the list screen.
        if (!empty($return)) {
            $this->setMessage(Text::_('COM_VEL_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));

            $this->triggerTicket(
                TicketType::AbandonedExtension,
                (int) $return,
                Text::sprintf('COM_VEL_TICKET_NEW_ABANDONEDREPORT_EVENT', $return)
            );
        }
        $url = 'index.php?option=com_vel&view=tickets';
        $this->setRedirect(Route::_($url, false));

        // Flush the data from the session.
        $this->app->setUserState('com_vel.edit.abandonedreport.data', null);

        // Invoke the postSave method to allow for the child class to access the model.
        $this->postSaveHook($model, $data);
    }

    /**
     * Method to remove data
     *
     * There should be no removing of submitted forms, so this function is commented out
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    /*  public function remove()
        {
            $app   = Factory::getApplication();
            $model = $this->getModel('Abandonedreport', 'Site');
        $pk    = $this->input->getInt('id');

            // Attempt to save the data
            try
            {
            // Check in before delete
            $return = $model->checkin($return);
            // Clear id from the session.
            $this->app->setUserState('com_vel.edit.abandonedreport.id', null);

                // Clear the profile id from the session.
                $app->setUserState('com_vel.edit.abandonedreport.id', null);

                $menu = $app->getMenu();
                $item = $menu->getActive();
                $url  = (empty($item->link) ? 'index.php?option=com_vel&view=abandonedreports' : $item->link);

                // Redirect to the list screen
                $this->setMessage(Text::_('COM_VEL_ITEM_DELETED_SUCCESSFULLY'));
                $this->setRedirect(Route::_($url, false));

                // Flush the data from the session.
                $app->setUserState('com_vel.edit.abandonedreport.data', null);
            }
            catch (Exception $e)
            {
                $errorType = ($e->getCode() == '404') ? 'error' : 'warning';
                $this->setMessage($e->getMessage(), $errorType);
                $this->setRedirect('index.php?option=com_vel&view=abandonedreports');
            }
        }*/
}
