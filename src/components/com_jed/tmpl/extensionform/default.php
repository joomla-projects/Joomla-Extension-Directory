<?php

/**
 * @package       JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('jquery.framework');

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useStyle('com_jed.kartikv_fileinput_style')
    ->useScript('com_jed.kartikv_fileinput_buffer_js')
    ->useScript('com_jed.kartikv_fileinput_filetype_js')
    ->useScript('com_jed.kartikv_fileinput_piexif_js')
    ->useScript('com_jed.kartikv_fileinput_sortable_js')
    ->useScript('com_jed.kartikv_fileinput_fileinput_js')
    ->useScript('com_jed.kartikv_fileinput_bs5_js')
    ->useScript('com_jed.extensionTestUpload')
    ->useStyle('com_jed.submitextension')
    ->useScript('com_jed.extensionform')
	->useScript('com_jed.form_validate');


// Load admin language file
$lang = Factory::getApplication()->getLanguage();
$lang->load('com_jed', JPATH_SITE);

$user    = Factory::getApplication()->getIdentity();
$canEdit = JedHelper::canUserEdit($this->item);

$isLoggedIn  = JedHelper::IsLoggedIn();
$redirectURL = JedHelper::getLoginlink();

if ($this->item->state == 1) {
    $state_string = 'Publish';
    $state_value  = 1;
} else {
    $state_string = 'Unpublish';
    $state_value  = 0;
}
$canState = Factory::getApplication()->getIdentity()->authorise('core.edit.state', 'com_jed');
?>
<style>
 /*   #Free {
        background-color:#9C9C9C;
    }
    #Paid {
        background-color: #9c9c9c;
    }*/
    .extension-edit {
        padding-left: 10px;
        padding-right: 10px;
       /* background-color: beige;*/
    }

    span.badge-com {
        background-color: #00a500
    }

    span.badge-mod {
        background-color: #ff4b39
    }

    span.badge-plugin {
        background-color: #e20079
    }

    span.badge-search {
        background-color: #ff8100
    }

    span.badge-ext {
        background-color: #ffc40d;
        color: #333
    }

    span.joomla_versionsbadge {
        display: inline-block;
        font-weight: bold;
        color: white;
        white-space: nowrap;
        vertical-align: baseline;
        box-sizing: border-box;
        border-radius: 12px;
        height: 24px;
        padding: 6px 9px 6px 9px;
        margin: 5px 0;
        font-size: 12px;
        line-height: 12px;
        text-transform: uppercase;
        text-align: center;
        background-color: #5091cd;
        width: auto;
    }

    fieldset#jform_includes.checkboxes div.form-check.form-check-inline {
        display: block !important;

    }
</style>
<div class="extension-edit front-end-edit">

	<?php

    if (!$isLoggedIn) {
        try {
            $app = Factory::getApplication();
	        $app->enqueueMessage(Text::_('COM_JED_EXTENSION_NO_ACCESS_LABEL'), 'success');
	        $app->redirect($redirectURL);
        } catch (Exception $e) {
            echo $e->getMessage();
        }


    } else {



        ?>

	<?php
        if (!$canEdit) : ?>
        <h3>
			<?php
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403); ?>
        </h3>
	<?php
        else : ?>

<h3 id="extensiontitle"></h3>
        <form id="form-extension"
              action="<?php
                  echo Route::_('index.php?option=com_jed&task=extensionform.save'); ?>"
              method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

			<?php
                $fieldsets['overview']['title']       = Text::_('COM_JED_EXTENSION_ADD_EXTENSION_LABEL');
            $fieldsets['overview']['description'] = Text::_('COM_JED_EXTENSION_ADD_EXTENSION_LABEL_DESCR') . '</br>' . '</br>';
            $fieldsets['overview']['fields']      = [['title','alias'],'version',['primary_category_id', 'tags']];
            $fieldsets['overview']['hidden']      = [];

            JedHelper::OutputFieldsets($fieldsets, $this->form);

            echo HTMLHelper::_('uitab.startTabSet', 'supplyTab', ['active' => '']);


            foreach ($this->supply_types as $st) {
                echo HTMLHelper::_('uitab.addTab', 'supplyTab', $st->supply_type, $st->supply_type);

                $varied_form                          = $this->supply_forms[$st->supply_id];
                $fieldsets                            = [];
                $fieldsets['overview']['supply_type'] = $st->supply_type;
                $fieldsets['overview']['title']       =  Text::sprintf('COM_JED_EXTENSION_VARIED_TITLE_LABEL', $st->supply_type);
                $fieldsets['overview']['description'] = Text::_('COM_JED_EXTENSION_VARIED_TITLE_LABEL_DESCR');
                $fieldsets['overview']['fields']   = ['id', 'supply_option_id', ['title', 'is_default_data'],  'description'];
                $fieldsets['overview']['hidden']   = ['id', 'supply_option_id'];

                $fieldsets['extensionfile']['supply_type'] = $st->supply_type;
                $fieldsets['extensionfile']['title']       = '';
                $fieldsets['extensionfile']['description'] = Text::_('COM_JED_EXTENSION_EXTENSIONFILE_LABEL').'<br/>'.Text::_('COM_JED_EXTENSION_EXTENSIONFILE_EXTRA');
                $fieldsets['extensionfile']['fields']      = ['file'];
                $fieldsets['extensionfile']['hidden']      = [];
                $fieldsets['links']['supply_type'] = $st->supply_type;
                $fieldsets['links']['title']       = Text::_('COM_JED_EXTENSION_LINKS_TITLE');
                $fieldsets['links']['description'] = Text::_('COM_JED_EXTENSION_LINKS_DESCR');
                $fieldsets['links']['fields']      = [['homepage_link', 'download_link'], ['demo_link', 'support_link'], ['documentation_link', 'license_link'], ['translation_link', '']];
                $fieldsets['links']['hidden']      = [];

                $fieldsets['integration']['supply_type'] = $st->supply_type;
                $fieldsets['integration']['title']       = Text::_('COM_JED_EXTENSION_INTEGRATION_TITLE');
                $fieldsets['integration']['description'] = Text::_('COM_JED_EXTENSION_INTEGRATION_DESCR');
                $fieldsets['integration']['fields']      = [['download_integration_type', 'download_integration_url']];
                $fieldsets['integration']['hidden']      = [];

                JedHelper::OutputFieldsets($fieldsets, $varied_form);
                $fieldsets = [];

                echo HTMLHelper::_('uitab.endTab');
            }
            echo HTMLHelper::_('uitab.endTabSet');
            $fieldsets['integration2']['title']       = '';
            $fieldsets['integration2']['description'] = '';
            $fieldsets['integration2']['fields']      = [['gpl_license_type','joomla_versions'],['includes','uses_third_party']];
            $fieldsets['integration2']['hidden']      = [];

            $fieldsets['media']['title']       = Text::_('COM_JED_EXTENSION_MEDIA_TITLE');
            $fieldsets['media']['description'] = Text::_('COM_JED_EXTENSION_MEDIA_DESCR');
            $fieldsets['media']['fields']      = ['video', 'logo', 'images'];
            $fieldsets['media']['hidden']      = [];

            $fieldsets['confirm']['title']       = Text::_('COM_JED_GENERAL_CONFIRM_LABEL');
            $fieldsets['integration']['description'] = '';
            $fieldsets['integration']['fields']      = ['uses_updater'];
            $fieldsets['integration']['hidden']      = [];


            JedHelper::OutputFieldsets($fieldsets, $this->form);
            ?>
            <div class="control-group">
                <div class="controls">

					<?php
                    if ($this->canSave) : ?>
                        <button type="submit" class="validate btn btn-primary">
                            <span class="fas fa-check" aria-hidden="true"></span>
							<?php
                            echo Text::_('JSUBMIT'); ?>
                        </button>
					<?php
                    endif; ?>
                    <a class="btn btn-danger"
                       href="<?php
                       echo Route::_('index.php?option=com_jed&task=extensionform.cancel'); ?>"
                       title="<?php
                       echo Text::_('JCANCEL'); ?>">
                        <span class="fas fa-times" aria-hidden="true"></span>
						<?php
                        echo Text::_('JCANCEL'); ?>
                    </a>
                </div>
            </div>

            <input type="hidden" name="option" value="com_jed"/>
            <input type="hidden" name="task"
                   value="extensionform.save"/>
			<?php
            echo HTMLHelper::_('form.token'); ?>
        </form>
	<?php
    endif;
    }?>
</div>
