<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access to $displayData file
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
// phpcs:enable PSR1.Files.SideEffects
/**
 * @var \Joomla\CMS\Form\Form $displayData
*/
$headerlabeloptions = ['hiddenLabel' => true];
$fieldhiddenoptions = ['hidden' => true];
?>
<div class="span10 form-horizontal">
    <div class="row vel-header-row">
        <div class="col-md-12 vel-header">
            <h1><?php echo Text::_('COM_JED_LAYOUT_VEL_PUBLISHED_TITLE'); ?>
                <button type="button" class="" id="buildTitle">
                    <span class="icon-wand"></span>
                </button>
            </h1>
            <?php echo $displayData->renderField('title', null, null, $headerlabeloptions); ?>
        </div>
    </div>
    <div class="row vel-header-row">
        <div class="col-md-3 vel-header">
            <h1><?php echo Text::_('COM_JED_VEL_GENERAL_VULNERABLE_ITEM_NAME_LABEL'); ?></h1>

            <?php echo $displayData->renderField('vulnerable_item_name', null, null, $headerlabeloptions); ?>
        </div>
        <div class="col-md-3  vel-header">
            <h1><?php echo Text::_('COM_JED_VEL_GENERAL_VULNERABLE_ITEM_VERSION_LABEL'); ?></h1>
            <?php echo $displayData->renderField('vulnerable_item_version', null, null, $headerlabeloptions); ?>
        </div>
        <div class="col-md-3  vel-header">
            <h1><?php echo Text::_('JSTATUS'); ?></h1>
            <?php echo $displayData->renderField('status', null, null, $headerlabeloptions); ?>

        </div>
        <div class="col-md-3  vel-header">
            <h1><?php echo Text::_('COM_JED_VEL_GENERAL_FIELD_RISK_LEVEL_LABEL'); ?></h1>
            <?php echo $displayData->renderField('risk_level', null, null, $headerlabeloptions); ?>

        </div>
    </div>
    <div class="row vel-header-row">
        <div class="col-md-3 vel-header">
            <h1><?php echo Text::_('COM_JED_VEL_GENERAL_FIELD_START_VERSION_LABEL'); ?></h1>

            <?php echo $displayData->renderField('start_version', null, null, $headerlabeloptions); ?>
        </div>
        <div class="col-md-3  vel-header">
            <h1><?php echo Text::_('COM_JED_VEL_GENERAL_FIELD_VULNERABLE_VERSION_LABEL'); ?></h1>
            <?php echo $displayData->renderField('vulnerable_version', null, null, $headerlabeloptions); ?>
        </div>
        <div class="col-md-3  vel-header">
            <h1><?php echo Text::_('COM_JED_VEL_GENERAL_FIELD_PATCH_VERSION_LABEL'); ?></h1>
            <?php echo $displayData->renderField('patch_version', null, null, $headerlabeloptions); ?>

        </div>
        <div class="col-md-3  vel-header">
            <h1><?php echo Text::_('COM_JED_VEL_GENERAL_EXPLOIT_TYPE_LABEL'); ?></h1>
            <?php echo $displayData->renderField('exploit_type', null, null, $headerlabeloptions); ?>
            <?php echo $displayData->renderField('exploit_other_description', null, null, $headerlabeloptions); ?>
        </div>
    </div>


