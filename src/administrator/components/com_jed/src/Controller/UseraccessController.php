<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Access\Privilege;
use Jed\Component\Jed\Administrator\Model\UseraccessModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Throwable;

/**
 * Ban, unban, trust and per-privilege changes.
 *
 * Guarded by `jed.user.ban` throughout (`P1-03`): suspending somebody's ability to take part is
 * not something everyone who may edit a listing should be able to do.
 *
 * @since 4.1.0
 */
class UseraccessController extends BaseController
{
    /**
     * Save the decision made on the edit form for one user.
     *
     * One action rather than one per field: the reason is mandatory for any change, and a form
     * that submits everything at once means the person writes the reason for what they actually
     * did instead of for each checkbox in turn.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function save(): void
    {
        $this->checkToken();

        $app    = Factory::getApplication();
        $userId = $this->input->getInt('user_id', 0);
        $reason = (string) $this->input->getString('reason', '');

        if (!$this->allowed()) {
            return;
        }

        // A ban with no end is permanent; an empty date means "no boundary that way", which is
        // how the gate reads it. Empty strings become NULL rather than '0000-00-00' (8.14).
        $from  = trim((string) $this->input->getString('banned_from', ''));
        $until = trim((string) $this->input->getString('banned_until', ''));

        $columns = [
            'banned'                  => $this->input->getInt('banned', 0) === 1 ? 1 : 0,
            'banned_from'             => $from === '' ? null : $from,
            'banned_until'            => $until === '' ? null : $until,
            'auto_approve_extensions' => $this->input->getInt('auto_approve_extensions', 0) === 1 ? 1 : 0,
            'auto_approve_reviews'    => $this->input->getInt('auto_approve_reviews', 0) === 1 ? 1 : 0,
        ];

        foreach (Privilege::cases() as $privilege) {
            $columns[$privilege->value] = $this->input->getInt($privilege->value, 0) === 1 ? 1 : 0;
        }

        $this->run($userId, $columns, $reason, 'COM_JED_USERACCESS_SAVED');
    }

    /**
     * Lift a ban without touching anything else.
     *
     * Offered as its own action because it is the one the JED team will reach for most, and
     * making them open a form to untick a box would invite them to change something else by
     * accident.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function unban(): void
    {
        // 'get': the list offers this as a plain link rather than a form, so the token travels in
        // the query string. checkToken() defaults to POST and would reject every attempt.
        $this->checkToken('get');

        if (!$this->allowed()) {
            return;
        }

        $this->run(
            $this->input->getInt('user_id', 0),
            ['banned' => 0, 'banned_from' => null, 'banned_until' => null],
            (string) $this->input->getString('reason', '') ?: Text::_('COM_JED_USERACCESS_UNBANNED_DEFAULT_REASON'),
            'COM_JED_USERACCESS_UNBANNED'
        );
    }

    /**
     * Whether the current user may decide about other people's privileges.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    private function allowed(): bool
    {
        $app = Factory::getApplication();

        if ($app->getIdentity()->authorise('jed.user.ban', 'com_jed')) {
            return true;
        }

        $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
        $this->setRedirect($this->listUrl());

        return false;
    }

    /**
     * Apply a decision and report what happened.
     *
     * @param int    $userId     The user.
     * @param array  $columns    What to write.
     * @param string $reason     Why.
     * @param string $successKey Language key for the success message.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function run(int $userId, array $columns, string $reason, string $successKey): void
    {
        $app = Factory::getApplication();

        try {
            /** @var UseraccessModel $model */
            $model = $this->getModel('Useraccess', 'Administrator', ['ignore_request' => true]);
            $model->applyDecision($userId, $columns, $reason);
            $app->enqueueMessage(Text::_($successKey));
        } catch (Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($this->listUrl());
    }

    /**
     * @return string
     *
     * @since 4.1.0
     */
    private function listUrl(): string
    {
        return Route::_('index.php?option=com_jed&view=useraccess', false);
    }
}
