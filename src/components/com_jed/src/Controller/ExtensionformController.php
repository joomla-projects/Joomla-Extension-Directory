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
use Jed\Component\Jed\Site\Model\ExtensionformModel;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Extension class.
 *
 * This form only ever edits an existing extension (see ExtensionformModel::getItem()/save()),
 * so the standard, inherited FormController::save() is used as-is - it already validates via the
 * form and calls $model->save($data); there is no "create new" branch to special-case here.
 * File uploads (logo/overview_image/images/files subforms) are handled inside
 * ExtensionformModel::save() itself, reading $_FILES directly, same as the admin backend.
 *
 * @since 4.0.0
 */
class ExtensionformController extends FormController
{
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
        $previousId = (int) $app->getUserState('com_jed.edit.extension.id');
        $editId     = $app->getInput()->getInt('id', 0);

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
     * Method to abort current operation
     *
     * @param null $key
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function cancel($key = null): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
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

    protected function getRedirectUrlToList(): string
    {
        return Route::_('index.php?option=com_jed&view=dashboard');
    }

    /**
     * Method to check if you can save the record overridden because the form posts no `id`/`jform[id]`
     * field, so FormController::allowSave()'s own record-id-from-request logic always sees 0 and takes the
     * allowAdd() branch - requiring core.create, the wrong permission for an edit-only form - rather than allowEdit().
     * It also duplicates a global core.edit ACL check this component doesn't otherwise use for per-listing permission
     * (access.xml's own comment: "may this group approve anything at all" is a global question, "may
     * this person touch this extension" is not expressible as an ACL action). ExtensionformModel::
     * isAuthorised() (core.edit / core.edit.own + ownership) is already the single source of truth
     * for that question, so allowSave() below just asks it instead of reimplementing the check.
     *
     * @param array  $data An array of input data.
     * @param string $key  The name of the key for the primary key; unused, kept for signature
     *                      compatibility with FormController::allowSave().
     *
     * @return bool
     *
     * @throws Exception
     * @since 4.0.0
     */
    protected function allowSave($data, $key = 'id'): bool
    {
        $extensionId = (int) Factory::getApplication()->getUserState('com_jed.edit.extension.id');

        if (!$extensionId) {
            return false;
        }

        /** @var ExtensionformModel $model */
        $model = $this->getModel();

        return $model->isAuthorised($extensionId);
    }
}
