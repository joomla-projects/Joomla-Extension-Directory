<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Ticketmessage class.
 *
 * @since 4.0.0
 */
class TicketmessageController extends BaseController
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
    public function edit(): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $app->getUserState('com_jed.edit.ticketmessage.id');
        $editId     = $app->input->getInt('id', 0);

        // Set the user id for the user to edit in the session.
        $app->setUserState('com_jed.edit.ticketmessage.id', $editId);

        // Get the model.
        $model = $this->getModel('Ticketmessage', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId && $previousId !== $editId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_jed&view=ticketmessageform&layout=edit', false));
    }

    /**
     * Method to save data
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function publish(): void
    {
        // Initialise variables.
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Checking if the user can remove object
        $user = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.edit', 'com_jed') || $user->authorise('core.edit.state', 'com_jed')) {
            $model = $this->getModel('Ticketmessage', 'Site');

            // Get the user data.
            $id    = $app->input->getInt('id');
            $state = $app->input->getInt('state');

            // Attempt to save the data.
            $return = $model->publish($id, $state);

            // Check for errors.
            if ($return === false) {
                $this->setMessage(Text::_('Save failed'), 'warning');
            }

            // Clear the profile id from the session.
            $app->setUserState('com_jed.edit.ticketmessage.id', null);

            // Flush the data from the session.
            $app->setUserState('com_jed.edit.ticketmessage.data', null);

            // Redirect to the list screen.
            $this->setMessage(Text::_('COM_JED_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));
            $menu = Factory::getApplication()->getMenu();
            $item = $menu->getActive();

            if (!$item) {
                // If there isn't any menu item active, redirect to list view
                $this->setRedirect(Route::_('index.php?option=com_jed&view=ticketmessages', false));
            } else {
                $this->setRedirect(Route::_('index.php?Itemid=' . $item->id, false));
            }
        } else {
            throw new Exception(500);
        }
    }
}
