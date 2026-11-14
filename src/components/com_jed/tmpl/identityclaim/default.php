<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Site\View\Identityclaim\HtmlView $this */
?>
<div class="jed-identity-claim">
    <h1><?php echo Text::_('COM_JED_IDENTITY_TITLE'); ?></h1>

    <?php if (!$this->isLoggedIn) : ?>
        <p><?php echo Text::_('COM_JED_IDENTITY_LOGIN_REQUIRED'); ?></p>
        <a class="btn btn-primary" href="<?php echo Route::_(JedHelper::getLoginlink()); ?>"><?php echo Text::_('JLOGIN'); ?></a>
    <?php elseif (!$this->isPortalAccount) : ?>
        <p><?php echo Text::_('COM_JED_IDENTITY_NOT_PORTAL'); ?></p>
    <?php else : ?>
        <p><?php echo Text::_('COM_JED_IDENTITY_INTRO'); ?></p>
        <form action="<?php echo Route::_('index.php?option=com_jed&task=identity.request'); ?>" method="post" class="form-validate">
            <div class="mb-3">
                <label for="jed-identity-identifier" class="form-label"><?php echo Text::_('COM_JED_IDENTITY_IDENTIFIER_LABEL'); ?></label>
                <input type="text" name="identifier" id="jed-identity-identifier" class="form-control" required autocomplete="off" aria-describedby="jed-identity-identifier-help">
                <div id="jed-identity-identifier-help" class="form-text"><?php echo Text::_('COM_JED_IDENTITY_IDENTIFIER_HELP'); ?></div>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_JED_IDENTITY_SUBMIT'); ?></button>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php endif; ?>
</div>
