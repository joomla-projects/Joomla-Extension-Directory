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

/** @var \Jed\Component\Vel\Site\View\Report\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_vel.site', 'com_vel/css/vel.css')->useScript('form.validate')->useScript('showon');
$wa->addInlineStyle('.com-vel-hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }');
?>
<div class="com-vel-report">
    <div class="page-header">
        <h1><?php echo Text::_('COM_VEL_REPORT_HEADING'); ?></h1>
    </div>

    <div class="alert alert-info">
        <p><?php echo Text::_('COM_VEL_REPORT_INTRO'); ?></p>
        <ul class="mb-0">
            <li><?php echo Text::_('COM_VEL_REPORT_INTRO_SCOPE'); ?></li>
            <li><?php echo Text::_('COM_VEL_REPORT_INTRO_CORE'); ?> <a href="https://developer.joomla.org/security-centre.html" rel="noopener" target="_blank">developer.joomla.org/security-centre</a></li>
            <li><?php echo Text::_('COM_VEL_REPORT_INTRO_PRIVATE'); ?></li>
            <?php if ($this->contactEmail !== '') : ?>
                <li><?php echo Text::sprintf('COM_VEL_REPORT_INTRO_EMAIL', '<a href="mailto:' . $this->escape($this->contactEmail) . '">' . $this->escape($this->contactEmail) . '</a>'); ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if (!$this->guestsAllowed) : ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_VEL_REPORT_LOGIN_REQUIRED'); ?>
            <a href="<?php echo Route::_('index.php?option=com_users&view=login&return=' . base64_encode(Route::_('index.php?option=com_vel&view=report', false))); ?>"><?php echo Text::_('JLOGIN'); ?></a>
        </div>
    <?php else : ?>
        <form action="<?php echo Route::_('index.php?option=com_vel&task=report.submit'); ?>" method="post" name="reportForm" id="reportForm"
              class="form-validate" enctype="multipart/form-data">
            <?php foreach (['reporter', 'extension', 'vulnerability', 'developer', 'attachments', 'consent'] as $fieldset) : ?>
                <fieldset class="card card-body">
                    <legend><?php echo Text::_($this->form->getFieldsets()[$fieldset]->label ?? ''); ?></legend>
                    <?php foreach ($this->form->getFieldset($fieldset) as $field) : ?>
                        <?php if ($field->fieldname === 'website') : ?>
                            <div class="com-vel-hp" aria-hidden="true"><?php echo $field->input; ?></div>
                        <?php else : ?>
                            <?php echo $field->renderField(); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>

            <?php if ($this->pgpKey !== '') : ?>
                <details class="mb-3">
                    <summary><?php echo Text::_('COM_VEL_REPORT_PGP_SUMMARY'); ?></summary>
                    <p class="small text-muted"><?php echo Text::_('COM_VEL_REPORT_PGP_DESC'); ?></p>
                    <pre class="com-vel-pgp"><?php echo $this->escape($this->pgpKey); ?></pre>
                </details>
            <?php endif; ?>

            <div class="mb-3">
                <button type="submit" class="btn btn-primary validate"><?php echo Text::_('COM_VEL_BUTTON_SUBMIT_REPORT'); ?></button>
                <a class="btn btn-link" href="<?php echo Route::_('index.php?option=com_vel&view=advisories'); ?>"><?php echo Text::_('JCANCEL'); ?></a>
            </div>

            <input type="hidden" name="task" value="report.submit">
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php endif; ?>
</div>
