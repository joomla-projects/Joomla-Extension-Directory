<?php

/**
 * @package JED
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
 * Review class.
 *
 * @since 1.6.0
 */
class ReviewController extends BaseController
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
        $previousId = (int) $app->getUserState('com_jed.edit.review.id');
        $editId     = $app->getInput()->getInt('id', 0);

        // Set the user id for the user to edit in the session.
        $app->setUserState('com_jed.edit.review.id', $editId);

        // Get the model.
        $model = $this->getModel('Review', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId && $previousId !== $editId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_jed&view=reviewform&layout=edit', false));
    }

    /**
     * Method to save data
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
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
            $model = $this->getModel('Review', 'Site');

            // Get the user data.
            $id    = $app->getInput()->getInt('id');
            $state = $app->getInput()->getInt('state');

            // Attempt to save the data.
            $return = $model->publish($id, $state);

            // Check for errors.
            if ($return === false) {
                $this->setMessage(Text::_('Save failed'), 'warning');
            }

            // Clear the profile id from the session.
            $app->setUserState('com_jed.edit.review.id', null);

            // Flush the data from the session.
            $app->setUserState('com_jed.edit.review.data', null);

            // Redirect to the list screen.
            $this->setMessage(Text::_('COM_JED_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));
            $menu = Factory::getApplication()->getMenu();
            $item = $menu->getActive();

            if (!$item) {
                // If there isn't any menu item active, redirect to list view
                $this->setRedirect(Route::_('index.php?option=com_jed&view=reviews', false));
            } else {
                $this->setRedirect(Route::_('index.php?Itemid=' . $item->id, false));
            }
        } else {
            throw new Exception(500);
        }
    }

    /**
     * Soft-deletes a review the current user wrote (published = -2), so it stops showing up
     * anywhere but the extension/user pair remains free for a fresh review later.
     *
     * @return void
     *
     * @since 4.1.0
     * @throws Exception
     */
    public function remove(): void
    {
        $this->checkToken('request');

        $app   = Factory::getApplication();
        $id    = $app->getInput()->getInt('id');
        $model = $this->getModel('Review', 'Site');

        try {
            $model->softDeleteOwn($id);
            $this->setMessage(Text::_('COM_JED_ITEM_DELETED_SUCCESSFULLY'));
        } catch (Exception $e) {
            $errorType = ($e->getCode() == '404') ? 'error' : 'warning';
            $this->setMessage($e->getMessage(), $errorType);
        }

        // Redirect to the dashboard rather than back to the now-deleted review's own page -
        // a regular user without core.edit/core.edit.state can't view a -2 (trashed) review.
        $menu = $app->getMenu()->getActive();
        $url  = (empty($menu->link) ? 'index.php?option=com_jed&view=dashboard' : $menu->link);
        $this->setRedirect(Route::_($url, false));
    }

    /**
     * Saves an extension owner's/maintainer's response to a review.
     *
     * @return void
     *
     * @since 4.1.0
     * @throws Exception
     */
    public function saveResponse(): void
    {
        $this->checkToken();

        $app  = Factory::getApplication();
        $id   = $app->getInput()->getInt('id');
        $text = $app->getInput()->get('developer_response', '', 'raw');

        try {
            if (trim($text) === '') {
                throw new Exception(Text::_('COM_JED_REVIEW_DEVELOPER_RESPONSE_EMPTY'), 400);
            }

            $model = $this->getModel('Review', 'Site');
            $model->saveResponse($id, $text);
            $this->setMessage(Text::_('COM_JED_REVIEW_DEVELOPER_RESPONSE_SAVED'));
        } catch (Exception $e) {
            $errorType = ($e->getCode() == '404') ? 'error' : 'warning';
            $this->setMessage($e->getMessage(), $errorType);
        }

        $this->setRedirect(Route::_('index.php?option=com_jed&view=review&id=' . $id, false));
    }

    /**
     * Lets an extension owner/maintainer retract their own developer response.
     *
     * @return void
     *
     * @since 4.1.0
     * @throws Exception
     */
    public function deleteResponse(): void
    {
        $this->checkToken('request');

        $app   = Factory::getApplication();
        $id    = $app->getInput()->getInt('id');
        $model = $this->getModel('Review', 'Site');

        try {
            $model->deleteOwnResponse($id);
            $this->setMessage(Text::_('COM_JED_ITEM_DELETED_SUCCESSFULLY'));
        } catch (Exception $e) {
            $errorType = ($e->getCode() == '404') ? 'error' : 'warning';
            $this->setMessage($e->getMessage(), $errorType);
        }

        $menu = $app->getMenu()->getActive();
        $url  = (empty($menu->link) ? 'index.php?option=com_jed&view=dashboard' : $menu->link);
        $this->setRedirect(Route::_($url, false));
    }

    /**
     * Check in record
     *
     * @return bool  True on success
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function checkin(): bool
    {
        // Check for request forgeries.
        $this->checkToken('GET');

        $id        = $this->input->post->get('id', 0, 'int');
        $model     = $this->getModel();
        $item      = $model->getItem($id);

        // Checking if the user can remove object
        $user = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.manage', 'com_jed') || $item->checked_out == $user->id) {
            $return = $model->checkin($id);

            if ($return === false) {
                // Checkin failed.
                $message = Text::_('JLIB_APPLICATION_ERROR_CHECKIN_FAILED');
                $this->setRedirect(Route::_('index.php?option=com_jed&view=review' . '&id=' . $id, false), $message, 'error');
                return false;
            }
            // Checkin succeeded.
            $message = Text::_('COM_JED_CHECKEDIN_SUCCESSFULLY');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=review' . '&id=' . $id, false), $message);
            return true;
        }
        throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
    }
}
