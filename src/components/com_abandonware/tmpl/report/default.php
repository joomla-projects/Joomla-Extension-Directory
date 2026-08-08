<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
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
use Joomla\CMS\Uri\Uri;

/** @var \Jed\Component\Abandonware\Site\View\Report\HtmlView $this */

HTMLHelper::_('behavior.formvalidator');
?>

<div class="com-abandonware-report">
    <div class="page-header">
        <h1><?php echo $this->escape($this->params->get('page_heading', Text::_('COM_ABANDONWARE_REPORT_HEADING'))); ?></h1>
    </div>

    <?php
    /*
     * What a report is for, said before the form rather than after it. 4.10's warning applies to
     * the public as much as to the automation: an extension with no release for three years may
     * simply be finished, and telling somebody that up front is cheaper than working the case that
     * comes of them not knowing.
     */
    ?>
    <div class="alert alert-info">
        <p class="mb-0"><?php echo Text::_('COM_ABANDONWARE_REPORT_INTRO'); ?></p>
    </div>

    <?php if (!$this->mayReport) : ?>
        <div class="alert alert-warning">
            <p class="mb-0"><?php echo $this->escape($this->refusal); ?></p>
        </div>

        <p>
            <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_abandonware&view=abandoned'); ?>">
                <?php echo Text::_('COM_ABANDONWARE_BUTTON_BACK_TO_LIST'); ?>
            </a>
        </p>
    <?php else : ?>
        <form action="<?php echo htmlspecialchars(Uri::getInstance()->toString(), ENT_QUOTES, 'UTF-8'); ?>"
              method="post" name="adminForm" id="abandonware-report-form" class="form-validate">

            <fieldset>
                <legend><?php echo Text::_('COM_ABANDONWARE_REPORT_LEGEND_SUBJECT'); ?></legend>
                <?php foreach ($this->form->getFieldset('subject') as $field) : ?>
                    <div class="mb-3"><?php echo $field->renderField(); ?></div>
                <?php endforeach; ?>
            </fieldset>

            <fieldset>
                <legend><?php echo Text::_('COM_ABANDONWARE_REPORT_LEGEND_REPORTER'); ?></legend>
                <?php foreach ($this->form->getFieldset('reporter') as $field) : ?>
                    <div class="mb-3"><?php echo $field->renderField(); ?></div>
                <?php endforeach; ?>
            </fieldset>

            <div class="mb-3">
                <button type="button" class="btn btn-primary"
                        onclick="Joomla.submitbutton('report.submit')">
                    <?php echo Text::_('COM_ABANDONWARE_BUTTON_SUBMIT_REPORT'); ?>
                </button>
                <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_abandonware&view=abandoned'); ?>">
                    <?php echo Text::_('JCANCEL'); ?>
                </a>
            </div>

            <input type="hidden" name="task" value=""/>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php endif; ?>
</div>
