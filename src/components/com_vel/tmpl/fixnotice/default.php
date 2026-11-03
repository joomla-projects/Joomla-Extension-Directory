<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Site\View\Fixnotice\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_vel.site', 'com_vel/css/vel.css')->useScript('form.validate')->useScript('showon');
$wa->addInlineStyle('.com-vel-hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }');
?>
<div class="com-vel-report">
    <div class="page-header">
        <h1><?php echo Text::_('COM_VEL_FIXNOTICE_HEADING'); ?></h1>
    </div>

    <div class="alert alert-info">
        <p class="mb-0"><?php echo Text::_('COM_VEL_FIXNOTICE_INTRO'); ?></p>
    </div>

    <form action="<?php echo Route::_('index.php?option=com_vel&task=fixnotice.submit'); ?>" method="post" name="fixnoticeForm" id="fixnoticeForm" class="form-validate">
        <?php foreach (['reporter', 'fix', 'consent'] as $fieldset) : ?>
            <fieldset class="card card-body">
                <legend><?php echo Text::_($this->form->getFieldsets()[$fieldset]->label ?? ''); ?></legend>
                <?php foreach ($this->form->getFieldset($fieldset) as $field) : ?>
                    <?php if ($field->fieldname === 'website') : ?>
                        <div class="com-vel-hp" aria-hidden="true"><?php echo $field->input; ?></div>
                    <?php elseif ($field->hidden) : ?>
                        <?php echo $field->input; ?>
                    <?php else : ?>
                        <?php echo $field->renderField(); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>

        <div class="mb-3">
            <button type="submit" class="btn btn-primary validate"><?php echo Text::_('COM_VEL_BUTTON_SUBMIT_FIXNOTICE'); ?></button>
            <a class="btn btn-link" href="<?php echo Route::_('index.php?option=com_vel&view=advisories'); ?>"><?php echo Text::_('JCANCEL'); ?></a>
        </div>

        <input type="hidden" name="task" value="fixnotice.submit">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
