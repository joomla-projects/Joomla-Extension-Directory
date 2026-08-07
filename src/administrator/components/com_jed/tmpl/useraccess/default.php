<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Access\Privilege;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Jed\Component\Jed\Administrator\View\Useraccess\HtmlView $this */

HTMLHelper::_('bootstrap.collapse');

// The five privileges, straight from the enum - a column added there appears here without this
// template being touched, and cannot be forgotten.
$privileges = Privilege::cases();

/**
 * Whether this row's ban is in force right now.
 *
 * The same rule the gate applies (P1-05): `banned = 1` with a window that has passed is not a
 * ban. Showing the flag instead would send somebody looking for a restriction that is not there.
 */
$banInForce = static function (object $item): bool {
    if ((int) $item->banned !== 1) {
        return false;
    }

    $now = time();

    if (!empty($item->banned_from) && strtotime((string) $item->banned_from) > $now) {
        return false;
    }

    if (!empty($item->banned_until) && strtotime((string) $item->banned_until) < $now) {
        return false;
    }

    return true;
};
?>

<form action="<?php echo Route::_('index.php?option=com_jed&view=useraccess'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <p class="text-muted small">
                        <?php echo Text::_('COM_JED_USERACCESS_INTRO'); ?>
                    </p>

                    <table class="table">
                        <caption class="visually-hidden"><?php echo Text::_('COM_JED_USERACCESS_TITLE'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_JED_USERACCESS_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_JED_USERACCESS_PRIVILEGES'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_JED_USERACCESS_TRUST'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_JED_USERACCESS_LAST_DECISION'); ?></th>
                                <th scope="col" class="w-1 text-center"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php $banned = $banInForce($item); ?>
                            <tr>
                                <td>
                                    <strong><?php echo $this->escape($item->name); ?></strong>
                                    <div class="small text-muted"><?php echo $this->escape($item->username); ?></div>
                                </td>
                                <td>
                                    <?php if ($banned) : ?>
                                        <span class="badge bg-danger"><?php echo Text::_('COM_JED_USERACCESS_BANNED'); ?></span>
                                        <?php if (!empty($item->banned_until)) : ?>
                                            <div class="small text-muted">
                                                <?php echo Text::sprintf('COM_JED_USERACCESS_BANNED_UNTIL', HTMLHelper::_('date', $item->banned_until, Text::_('DATE_FORMAT_LC4'))); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ((int) $item->banned === 1) : ?>
                                        <?php /* The flag is set but the window has passed or not started. */ ?>
                                        <span class="badge bg-secondary"><?php echo Text::_('COM_JED_USERACCESS_BAN_NOT_IN_FORCE'); ?></span>
                                    <?php else : ?>
                                        <span class="badge bg-success"><?php echo Text::_('COM_JED_USERACCESS_ACTIVE'); ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) $item->block === 1) : ?>
                                        <div class="small text-muted"><?php echo Text::_('COM_JED_USERACCESS_JOOMLA_BLOCKED'); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($privileges as $privilege) : ?>
                                        <?php $held = (int) ($item->{$privilege->value} ?? 1) === 1; ?>
                                        <span class="badge <?php echo $held ? 'bg-light text-dark' : 'bg-danger'; ?>">
                                            <?php echo Text::_('COM_JED_USERACCESS_PRIVILEGE_' . strtoupper($privilege->value)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php if ((int) $item->auto_approve_extensions === 1) : ?>
                                        <span class="badge bg-info"><?php echo Text::_('COM_JED_USERACCESS_TRUST_EXTENSIONS'); ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) $item->auto_approve_reviews === 1) : ?>
                                        <span class="badge bg-info"><?php echo Text::_('COM_JED_USERACCESS_TRUST_REVIEWS'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item->set_time)) : ?>
                                        <div class="small">
                                            <?php echo HTMLHelper::_('date', $item->set_time, Text::_('DATE_FORMAT_LC4')); ?>
                                            <?php if (!empty($item->set_by_name)) : ?>
                                                &mdash; <?php echo $this->escape($item->set_by_name); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($item->banned_reason)) : ?>
                                            <div class="small text-muted"><?php echo $this->escape($item->banned_reason); ?></div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted small"><?php echo Text::_('COM_JED_USERACCESS_NEVER_DECIDED'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo (int) $item->id; ?></td>
                            </tr>

                            <?php if ($this->canDecide) : ?>
                                <tr>
                                    <td colspan="6" class="py-0">
                                        <a class="small" data-bs-toggle="collapse" href="#jed-access-<?php echo (int) $item->id; ?>" role="button">
                                            <?php echo Text::_('COM_JED_USERACCESS_EDIT'); ?>
                                        </a>
                                        <?php if ($banned) : ?>
                                            <?php /* Lifting a ban is its own action so nobody has to open the form and untick a box, which is an invitation to change something else by accident. */ ?>
                                            <a class="small ms-3"
                                               href="<?php echo Route::_(
                                                   'index.php?option=com_jed&task=useraccess.unban&user_id=' . (int) $item->id
                                                   . '&' . Session::getFormToken() . '=1'
                                               ); ?>">
                                                <?php echo Text::_('COM_JED_USERACCESS_UNBAN'); ?>
                                            </a>
                                        <?php endif; ?>

                                        <div class="collapse" id="jed-access-<?php echo (int) $item->id; ?>">
                                            <div class="card card-body my-2">
                                                    <div class="row g-2">
                                                        <div class="col-md-6">
                                                            <fieldset>
                                                                <legend class="fs-6"><?php echo Text::_('COM_JED_USERACCESS_PRIVILEGES'); ?></legend>
                                                                <?php foreach ($privileges as $privilege) : ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" value="1"
                                                                               name="<?php echo $privilege->value; ?>" form="jed-access-form-<?php echo (int) $item->id; ?>"
                                                                               id="p-<?php echo $privilege->value . '-' . (int) $item->id; ?>"
                                                                            <?php echo (int) ($item->{$privilege->value} ?? 1) === 1 ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label" for="p-<?php echo $privilege->value . '-' . (int) $item->id; ?>">
                                                                            <?php echo Text::_('COM_JED_USERACCESS_PRIVILEGE_' . strtoupper($privilege->value)); ?>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </fieldset>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <fieldset>
                                                                <legend class="fs-6"><?php echo Text::_('COM_JED_USERACCESS_TRUST'); ?></legend>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" value="1"
                                                                           name="auto_approve_extensions" form="jed-access-form-<?php echo (int) $item->id; ?>"
                                                                           id="tx-<?php echo (int) $item->id; ?>"
                                                                        <?php echo (int) $item->auto_approve_extensions === 1 ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="tx-<?php echo (int) $item->id; ?>">
                                                                        <?php echo Text::_('COM_JED_USERACCESS_TRUST_EXTENSIONS'); ?>
                                                                    </label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" value="1"
                                                                           name="auto_approve_reviews" form="jed-access-form-<?php echo (int) $item->id; ?>"
                                                                           id="tr-<?php echo (int) $item->id; ?>"
                                                                        <?php echo (int) $item->auto_approve_reviews === 1 ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="tr-<?php echo (int) $item->id; ?>">
                                                                        <?php echo Text::_('COM_JED_USERACCESS_TRUST_REVIEWS'); ?>
                                                                    </label>
                                                                </div>
                                                            </fieldset>

                                                            <fieldset class="mt-2">
                                                                <legend class="fs-6"><?php echo Text::_('COM_JED_USERACCESS_BAN'); ?></legend>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" value="1"
                                                                           name="banned" form="jed-access-form-<?php echo (int) $item->id; ?>" id="b-<?php echo (int) $item->id; ?>"
                                                                        <?php echo (int) $item->banned === 1 ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="b-<?php echo (int) $item->id; ?>">
                                                                        <?php echo Text::_('COM_JED_USERACCESS_BANNED'); ?>
                                                                    </label>
                                                                </div>
                                                                <div class="row g-1 mt-1">
                                                                    <div class="col">
                                                                        <label class="form-label small" for="bf-<?php echo (int) $item->id; ?>">
                                                                            <?php echo Text::_('COM_JED_USERACCESS_BANNED_FROM'); ?>
                                                                        </label>
                                                                        <input type="datetime-local" class="form-control form-control-sm"
                                                                               id="bf-<?php echo (int) $item->id; ?>" name="banned_from" form="jed-access-form-<?php echo (int) $item->id; ?>"
                                                                               value="<?php echo $item->banned_from ? $this->escape(str_replace(' ', 'T', (string) $item->banned_from)) : ''; ?>">
                                                                    </div>
                                                                    <div class="col">
                                                                        <label class="form-label small" for="bu-<?php echo (int) $item->id; ?>">
                                                                            <?php echo Text::_('COM_JED_USERACCESS_BANNED_UNTIL_LABEL'); ?>
                                                                        </label>
                                                                        <input type="datetime-local" class="form-control form-control-sm"
                                                                               id="bu-<?php echo (int) $item->id; ?>" name="banned_until" form="jed-access-form-<?php echo (int) $item->id; ?>"
                                                                               value="<?php echo $item->banned_until ? $this->escape(str_replace(' ', 'T', (string) $item->banned_until)) : ''; ?>">
                                                                    </div>
                                                                </div>
                                                                <div class="form-text"><?php echo Text::_('COM_JED_USERACCESS_BAN_DATES_HELP'); ?></div>
                                                            </fieldset>
                                                        </div>

                                                        <div class="col-12">
                                                            <label class="form-label" for="r-<?php echo (int) $item->id; ?>">
                                                                <?php echo Text::_('COM_JED_USERACCESS_REASON'); ?>
                                                            </label>
                                                            <?php /* Mandatory for any change: a privilege change nobody can explain later is what the audit columns exist to prevent. */ ?>
                                                            <textarea class="form-control" rows="2" required
                                                                      id="r-<?php echo (int) $item->id; ?>" name="reason" form="jed-access-form-<?php echo (int) $item->id; ?>"></textarea>
                                                            <div class="form-text"><?php echo Text::_('COM_JED_USERACCESS_REASON_HELP'); ?></div>
                                                        </div>

                                                        <div class="col-12">
                                                            <button type="submit" class="btn btn-primary btn-sm" form="jed-access-form-<?php echo (int) $item->id; ?>">
                                                                <?php echo Text::_('JSAVE'); ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<?php /* One form per listed user, declared *outside* the search form and outside the table. The controls above are attached to it by the HTML5 `form` attribute, so the editor can sit inside a table cell without nesting a form inside another - which is invalid markup, and which browsers recover from in ways that would silently post the wrong thing. */ ?>
<?php if ($this->canDecide) : ?>
    <?php foreach ($this->items as $item) : ?>
        <form action="<?php echo Route::_('index.php?option=com_jed'); ?>" method="post"
              id="jed-access-form-<?php echo (int) $item->id; ?>" class="d-none">
            <input type="hidden" name="option" value="com_jed">
            <input type="hidden" name="task" value="useraccess.save">
            <input type="hidden" name="user_id" value="<?php echo (int) $item->id; ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php endforeach; ?>
<?php endif; ?>
