<?php

/**
 * @package    Joomla.Site
 * @subpackage Layout
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

extract($displayData);

/**
 * Layout variables
 * -----------------
 *
 * @var Form    $form       The form instance for render the section
 * @var string  $basegroup  The base group name
 * @var string  $group      Current group name
 * @var array   $buttons    Array of the buttons that will be rendered
 */
?>

<div class="subform-repeatable-group" data-base-name="<?php echo $basegroup; ?>" data-group="<?php echo $group; ?>">
    <?php if (!empty($buttons)) : ?>
        <div class="btn-toolbar text-end">
            <div class="btn-group">
                <?php if (!empty($buttons['add'])) :
                    ?><button type="button" class="group-add btn btn-sm btn-success" aria-label="<?php echo Text::_('JGLOBAL_FIELD_ADD'); ?>"><span class="icon-plus icon-white" aria-hidden="true"></span> </button><?php
                endif; ?>
                <?php if (!empty($buttons['remove'])) :
                    ?><button type="button" class="group-remove btn btn-sm btn-danger" aria-label="<?php echo Text::_('JGLOBAL_FIELD_REMOVE'); ?>"><span class="icon-minus icon-white" aria-hidden="true"></span> </button><?php
                endif; ?>
                <?php if (!empty($buttons['move'])) : ?>
                    <button type="button" class="group-move btn btn-sm btn-primary" aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE'); ?>"><span class="icon-arrows-alt icon-white" aria-hidden="true"></span> </button>
                    <button type="button" class="group-move-up btn btn-sm" aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE_UP'); ?>"><span class="icon-chevron-up" aria-hidden="true"></span> </button>
                    <button type="button" class="group-move-down btn btn-sm" aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE_DOWN'); ?>"><span class="icon-chevron-down" aria-hidden="true"></span> </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $fieldsets                            = [];
$fieldsets['overview']['supply_type']     = $st->supply_type;
$fieldsets['overview']['title']           =  Text::sprintf('COM_JED_EXTENSION_VARIED_TITLE_LABEL', $st->supply_type);
$fieldsets['overview']['description']     = Text::_('COM_JED_EXTENSION_VARIED_TITLE_LABEL_DESCR');
$fieldsets['overview']['fields']          = ['id', ['supply_option_id'], ['title', 'is_default_data'],  'description'];
$fieldsets['overview']['hidden']          = ['id'];

$fieldsets['extensionfile']['supply_type'] = $st->supply_type;
$fieldsets['extensionfile']['title']       = '';
$fieldsets['extensionfile']['description'] = Text::_('COM_JED_EXTENSION_EXTENSIONFILE_LABEL') . '<br/>' . Text::_('COM_JED_EXTENSION_EXTENSIONFILE_EXTRA');
$fieldsets['extensionfile']['fields']      = ['file'];
$fieldsets['extensionfile']['hidden']      = [];
$fieldsets['links']['supply_type']         = $st->supply_type;
$fieldsets['links']['title']               = Text::_('COM_JED_EXTENSION_LINKS_TITLE');
$fieldsets['links']['description']         = Text::_('COM_JED_EXTENSION_LINKS_DESCR');
$fieldsets['links']['fields']              = [['homepage_link', 'download_link'], ['demo_link', 'support_link'], ['documentation_link', 'license_link'], ['translation_link', '']];
$fieldsets['links']['hidden']              = [];

$fieldsets['integration']['supply_type'] = $st->supply_type;
$fieldsets['integration']['title']       = Text::_('COM_JED_EXTENSION_INTEGRATION_TITLE');
$fieldsets['integration']['description'] = Text::_('COM_JED_EXTENSION_INTEGRATION_DESCR');
$fieldsets['integration']['fields']      = [['download_integration_type', 'download_integration_url']];
$fieldsets['integration']['hidden']      = [];

JedHelper::outputFieldsets($fieldsets, $form);
$fieldsets = [];
?>
</div>
