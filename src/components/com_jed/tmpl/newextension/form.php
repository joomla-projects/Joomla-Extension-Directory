<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Step 2 of the "create a new extension" wizard: forms/extensionform.xml, one tab per fieldset,
 * pre-filled from whatever step 1 detected (see NewextensionModel::loadFormData()). Submitting
 * creates the new #__jed_extensions row and its first #__jed_extensions_history entry.
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Site\View\Newextension\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip');

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('webcomponent.field-subform');
?>
<div class="newextension-form front-end-edit">

    <form id="form-newextension" name="adminForm" class="form-validate"
          action="<?php echo Route::_('index.php?option=com_jed&task=newextension.save'); ?>"
          method="post" enctype="multipart/form-data">

        <div class="main-card">
            <?php
            echo HTMLHelper::_('uitab.startTabSet', 'newextensionTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]);

            foreach ($this->form->getFieldsets() as $fieldset) :
                echo HTMLHelper::_('uitab.addTab', 'newextensionTab', $fieldset->name, Text::_($fieldset->label));
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
                <a class="btn btn-danger" href="<?php echo Route::_('index.php?option=com_jed&task=newextension.cancel'); ?>"
                   title="<?php echo Text::_('JCANCEL'); ?>">
                    <span class="fas fa-times" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </a>
            </div>
        </div>

        <input type="hidden" name="option" value="com_jed"/>
        <input type="hidden" name="task" value="newextension.save"/>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
