<?php

/** @var \Jed\Component\Jed\Site\View\Extensionform\HtmlView $this */
/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Edit-only form for an existing extension: one tab per fieldset of forms/extensionform.xml.
 * ExtensionformModel::getItem() already enforces that only the owner, a maintainer, or an
 * admin/superuser can reach this page (it throws otherwise), so there is no separate access
 * check here.
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('bootstrap.tooltip');

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('webcomponent.field-subform')
    ->useStyle('com_jed.submitextension');

$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();

if (!$isLoggedIn) {
    /* @var $app \Joomla\CMS\Application\SiteApplication */
    $app = Factory::getApplication();
    $app->enqueueMessage(Text::_('COM_JED_EXTENSION_NO_ACCESS_LABEL'), 'warning');
    $app->redirect($redirectURL);

    return;
}
?>
<div class="extension-edit front-end-edit">

    <form id="form-extension" name="adminForm" class="form-validate"
          action="<?php echo Route::_('index.php?option=com_jed&task=extensionform.save'); ?>"
          method="post" enctype="multipart/form-data">

        <div class="main-card">
            <?php
            echo HTMLHelper::_('uitab.startTabSet', 'extensionformTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]);

            foreach ($this->form->getFieldsets() as $fieldset) :
                echo HTMLHelper::_('uitab.addTab', 'extensionformTab', $fieldset->name, Text::_($fieldset->label));
                ?>
                <div class="row">
                    <div class="col-12 col-lg-8">
                        <?php echo $this->form->renderFieldset($fieldset->name); ?>
                    </div>
                </div>
                <?php
                echo HTMLHelper::_('uitab.endTab');
            endforeach;

            echo HTMLHelper::_('uitab.endTabSet');
            ?>
        </div>

        <div class="control-group mt-3">
            <div class="controls">
                <?php if ($this->canSave) : ?>
                    <button type="submit" class="validate btn btn-primary">
                        <span class="fas fa-check" aria-hidden="true"></span>
                        <?php echo Text::_('JSUBMIT'); ?>
                    </button>
                <?php endif; ?>
                <a class="btn btn-danger" href="<?php echo Route::_('index.php?option=com_jed&task=extensionform.cancel'); ?>"
                   title="<?php echo Text::_('JCANCEL'); ?>">
                    <span class="fas fa-times" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </a>
            </div>
        </div>

        <input type="hidden" name="option" value="com_jed"/>
        <input type="hidden" name="task" value="extensionform.save"/>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
