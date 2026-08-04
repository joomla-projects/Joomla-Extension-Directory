<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Site\View\Extension\HtmlView $this */

// Served with 200 rather than 404 on purpose (4.8): this page exists to tell a visitor who
// followed a search result or an old link why the listing is not usable. prepareDocument()
// puts a noindex on it so it does not stay in the index while it says so.
//
// What is public here is the reason's *title* and its knowledge base article - never
// block_reason_text, which is the JED team's internal note, and never anything from an audit
// (8.7). getPublicBlockReason() only returns the three public fields, so this template cannot
// leak the rest even by accident.
$reason = $this->item->block_reason ?? null;

// Editing stays open to the developer while the block stands - otherwise the block would be
// unresolvable, since fixing the cause is exactly what is being asked of them.
$isDeveloper = JedHelper::isLoggedIn() && JedHelper::isOwnerOrMaintainer((int) $this->item->id);
?>

<div class="jed-wrapper jed-extension-blocked margin-bottom">
    <div class="jed-container">
        <h1 class="heading heading--l"><?php echo $this->escape($this->item->name); ?></h1>

        <div class="alert alert-warning" role="alert">
            <h2 class="alert-heading heading--m"><?php echo Text::_('COM_JED_EXTENSION_BLOCKED_HEADING'); ?></h2>

            <p><?php echo Text::_('COM_JED_EXTENSION_BLOCKED_INTRO'); ?></p>

            <?php if ($reason !== null) : ?>
                <p class="jed-extension-blocked__reason">
                    <strong><?php echo Text::_('COM_JED_EXTENSION_BLOCKED_REASON_LABEL'); ?></strong>
                    <?php echo $this->escape($reason['title']); ?>
                    <span class="text-muted">(<?php echo $this->escape($reason['code']); ?>)</span>
                </p>

                <?php if (!empty($reason['article_id'])) : ?>
                    <p>
                        <a href="<?php echo Route::_('index.php?option=com_content&view=article&id=' . (int) $reason['article_id']); ?>">
                            <?php echo Text::_('COM_JED_EXTENSION_BLOCKED_READ_MORE'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($isDeveloper) : ?>
            <p>
                <a class="btn btn-outline-primary"
                   href="<?php echo Route::_('index.php?option=com_jed&task=extensionform.edit&id=' . (int) $this->item->id); ?>">
                    <span class="icon-pencil" aria-hidden="true"></span>
                    <?php echo Text::_('COM_JED_EXTENSION_BLOCKED_EDIT'); ?>
                </a>
            </p>
        <?php endif; ?>

        <p>
            <a href="<?php echo Route::_('index.php?option=com_jed&view=extensions'); ?>">
                <?php echo Text::_('COM_JED_EXTENSION_BLOCKED_BROWSE'); ?>
            </a>
        </p>
    </div>
</div>
