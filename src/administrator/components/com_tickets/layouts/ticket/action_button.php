<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Renders one Jed\Component\Tickets\Administrator\Ticket\TicketAction as its own
 * small mini-form, so each action's hidden fields stay isolated from the shared
 * adminForm and from every other action button on the page.
 *
 * @var array $displayData
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Tickets\Administrator\Ticket\TicketAction;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var TicketAction $action */
$action = $displayData['action'];
$formId = 'ticket-action-' . md5($action->option . $action->task . serialize($action->hiddenFields));
?>
<form action="index.php" method="post" name="<?php echo $formId; ?>" id="<?php echo $formId; ?>" class="d-inline-block me-2">
    <input type="hidden" name="option" value="<?php echo htmlspecialchars($action->option); ?>"/>
    <input type="hidden" name="task" value="<?php echo htmlspecialchars($action->task); ?>"/>
    <?php foreach ($action->hiddenFields as $name => $value) : ?>
        <input type="hidden" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars((string) $value); ?>"/>
    <?php endforeach; ?>
    <?php echo HTMLHelper::_('form.token'); ?>
    <button
        type="submit"
        class="btn btn-primary btn-sm"
        <?php if ($action->confirmMessage) : ?>
        onclick="return confirm('<?php echo htmlspecialchars(addslashes(Text::_($action->confirmMessage)), ENT_QUOTES); ?>');"
        <?php endif; ?>
    >
        <span class="icon-<?php echo htmlspecialchars($action->icon); ?>" aria-hidden="true"></span>
        <?php echo Text::_($action->label); ?>
    </button>
</form>
