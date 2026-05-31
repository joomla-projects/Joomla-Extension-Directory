<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access to file
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * @var \Joomla\CMS\Form\Form $displayData
*/
try {
    Factory::getApplication()->getDocument()->getWebAssetManager()
        ->useScript('form.validate')
        ->useScript('keepalive')
        ->usePreset('choicesjs')
        ->useScript('webcomponent.field-fancy-select')
        ->useStyle('com_jed.Tickets')
        ->useStyle('com_jed.jquery_dataTables');
} catch (Exception $e) {
}
$headerlabeloptions = ['hiddenLabel' => true];
$fieldhiddenoptions = ['hidden' => true];

$extension_form = $displayData->extension_form;
$title          = $extension_form->getField('title') ? 'title' : ($extension_form->getField('name') ? 'name' : '');
JedHelper::lockFormFields($extension_form, ['primary_category_id']);
?>
    <div class="row title-alias form-vertical mb-3">
        <div class="col-12 col-md-6">
            <?php echo $title ? $extension_form->renderField($title) : ''; ?>
        </div>
        <div class="col-12 col-md-6">
            <?php echo $extension_form->renderField('alias'); ?>

        </div>
    </div>
<?php
echo HTMLHelper::_('uitab.startTabSet', 'viewExtensionTab', ['active' => 'info']);

foreach ($extension_form->getFieldsets() as $fieldset) :
    echo HTMLHelper::_('uitab.addTab', 'viewExtensionTab', $fieldset->name, Text::_($fieldset->label));
    ?>
    <div class="row">
        <div class="col-12 col-lg-6">
            <?php  echo $extension_form->renderFieldset($fieldset->name); ?>
        </div>
    </div>
    <?php
    echo HTMLHelper::_('uitab.endTab'); ?>
<?php endforeach;

foreach ($displayData->varied as $st) {
    echo HTMLHelper::_(
        'uitab.addTab',
        'viewExtensionTab',
        'varied-' . $st->supply_option_id,
        Text::_($st->supply_option_type)
    );

    $varied_form = $displayData->varied_form[$st->supply_option_id];
    $varied_form->bind($st);
    $fieldsets                            = [];
    $fieldsets['overview']['supply_type'] = $st->supply_option_type;
    $fieldsets['overview']['title']       = '';
    $fieldsets['overview']['description'] = '';
    $fieldsets['overview']['fields']      = ['id', 'supply_option_id', ['title', 'is_default_data'], 'description'];
    $fieldsets['overview']['hidden']      = ['id', 'supply_option_id'];


    $fieldsets['links']['supply_type'] = $st->supply_option_type;
    $fieldsets['links']['title']       = Text::_('COM_JED_EXTENSION_LINKS_TITLE');
    $fieldsets['links']['description'] = '';
    $fieldsets['links']['fields']      = [
            ['homepage_link', 'download_link'],
            ['demo_link', 'support_link'],
            ['documentation_link', 'license_link'],
            ['translation_link', ''],
    ];
    $fieldsets['links']['hidden']      = [];

    $fieldsets['integration']['supply_type'] = $st->supply_option_type;
    $fieldsets['integration']['title']       = Text::_('COM_JED_EXTENSION_INTEGRATION_TITLE');
    $fieldsets['integration']['description'] = Text::_('COM_JED_EXTENSION_INTEGRATION_DESCR');
    $fieldsets['integration']['fields']      = [['download_integration_type', 'download_integration_url']];
    $fieldsets['integration']['hidden']      = [];
    JedHelper::lockFormFields($varied_form, ['']);
    JedHelper::outputFieldsets($fieldsets, $varied_form);
    $fieldsets = [];

    echo HTMLHelper::_('uitab.endTab');
}



echo HTMLHelper::_('uitab.endTabSet');



?>
