<?php

/**
 * @package       JED
 *
 * @subpackage    TICKETS
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');

// Load admin language file
$lang = Factory::getApplication()->getLanguage();
$lang->load('com_jed', JPATH_SITE);

$user    = $this->getCurrentUser();
$canEdit = JedHelper::canUserEdit($this->item);

$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();

echo LayoutHelper::render('ticket.new_ticket_header', $this->item);

if (!$isLoggedIn) {
    try {
        $app = Factory::getApplication();
    } catch (Exception $e) {
    }

    $app->enqueueMessage(Text::_('COM_JED_TICKET_NO_ACCESS'), 'success');
    $app->redirect($redirectURL);
} else {
    $default_values['ticket_origin'] = 0; // Registered User
    $default_values['ticket_status'] = 0; // NEW
    $default_values['parent_id']     = -1;
    //$default_values['ip_address']   = $_SERVER['REMOTE_ADDR'];
    $default_values['created_on']              = Factory::getDate()->toSql();
    $default_values['linked_item_type']        = $this->item->linked_item_type;
    $default_values['linked_item_id']          = $this->item->linked_item_id;
    $default_values['exension_varied_name']    = $this->item->vr;
    $default_values['allocated_group']         = 1; //Any
    $default_values['internal_notes']          = '';
    $default_values['uploaded_files_preview']  = '';
    $default_values['uploaded_files_location'] = '';
    $default_values['allocated_to']            = 0;

    $this->form->bind($default_values);

    $fieldsets['overview']['title']       = '';
    $fieldsets['overview']['description'] = '';
    $fieldsets['overview']['fields']      = [
        'ticket_category_type',
        'id',
        'linked_item_type',
        'reported_varied_name',
        'myextension_varied_name',
        'linked_item_id',
        'created_on',
        'parent_id',
        'ticket_origin',
        'ticket_subject',
        'ticket_text',
        'ticket_status',
        'allocated_group',
        'internal_notes',
        'uploaded_files_preview',
        'uploaded_files_location',
        'allocated_to',
    ];
    $fieldsets['overview']['hidden']      = ['id', 'linked_item_type', 'linked_item_id','ticket_origin', 'ticket_status', 'allocated_group','internal_notes','uploaded_files_preview','uploaded_files_location','allocated_to', 'parent_id', 'created_on'];

    /*
    if ($this->item->state == 1) {
        $state_string = 'Publish';
        $state_value  = 1;
    } else {
        $state_string = 'Unpublish';
        $state_value  = 0;
    }
    $canState = $this->getCurrentUser()->authorise('core.edit.state', 'com_jed');

    */

    ?>

    <div class="ticket-edit front-end-edit">
        <?php
        if (!$canEdit) : ?>
            <h3>
                <?php
                throw new Exception(Text::_('COM_JED_GENERAL_ERROR_MESSAGE_NOT_AUTHORISED'), 403); ?>
            </h3>
        <?php else : ?>
            <form id="form-ticket"
                  action="<?php
                    echo Route::_('index.php?option=com_jed&task=ticketform.save'); ?>"
                  method="post" class="form-validate form-horizontal" enctype="multipart/form-data">
                <?php
                $fscount = 0;
                foreach ($fieldsets as $fs) {
                    $fscount = $fscount + 1;
                    if ($fs['title'] <> '') {
                        if ($fscount > 1) {
                            echo '</fieldset>';
                        }

                        echo '<fieldset class="ticketform"><legend>' . $fs['title'] . '</legend>';
                    }
                    if ($fs['description'] <> '') {
                        echo $fs['description'];
                    }
                    $fields       = $fs['fields'];
                    $hiddenFields = $fs['hidden'];
                    foreach ($fields as $field) {
                        if (in_array($field, $hiddenFields)) {
                            $this->form->setFieldAttribute($field, 'type', 'hidden');
                        }
                        echo $this->form->renderField($field, null, null, ['class' => 'control-wrapper-' . $field]);
                    }
                }
                ?>
                <?php
            /* echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'ticket']); ?>
                           <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'ticket', Text::_('COM_JED_TAB_TICKET', true)); ?>
                           <?php echo $this->form->renderField('id'); ?>

                           <?php echo $this->form->renderField('ticket_origin'); ?>

                           <?php echo $this->form->renderField('ticket_category_type'); ?>

                           <?php echo $this->form->renderField('ticket_subject'); ?>

                           <?php echo $this->form->renderField('ticket_text'); ?>

                           <?php echo $this->form->renderField('internal_notes'); ?>

                           <?php echo $this->form->renderField('uploaded_files_preview'); ?>

                           <?php echo $this->form->renderField('uploaded_files_location'); ?>

                           <?php echo $this->form->renderField('allocated_group'); ?>

                           <?php echo $this->form->renderField('allocated_to'); ?>

                           <?php echo $this->form->renderField('linked_item_type'); ?>

                           <?php echo $this->form->renderField('linked_item_id'); ?>

                           <?php echo $this->form->renderField('ticket_status'); ?>

                           <?php echo $this->form->renderField('parent_id'); ?>

                           <?php echo HTMLHelper::_('uitab.endTab'); ?>
                           <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'Publishing', Text::_('COM_JED_TAB_PUBLISHING', true)); ?>
                           <div class="control-group">
                               <?php if (!$canState) : ?>
                                   <div class="control-label"><?php echo $this->form->getLabel('state'); ?></div>
                                   <div class="controls"><?php echo $state_string; ?></div>
                                   <input type="hidden" name="jform[state]" value="<?php echo $state_value; ?>"/>
                               <?php else : ?>
                                   <div class="control-label"><?php echo $this->form->getLabel('state'); ?></div>
                                   <div class="controls"><?php echo $this->form->getInput('state'); ?></div>
                               <?php endif; ?>
                           </div>

                           <?php echo $this->form->renderField('created_by'); ?>

                           <?php echo $this->form->renderField('created_on'); ?>

                           <?php echo $this->form->renderField('modified_by'); ?>

                           <?php echo $this->form->renderField('modified_on'); ?>

                           <?php echo HTMLHelper::_('uitab.endTab'); */ ?>
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
                            echo Route::_('index.php?option=com_jed&task=ticketform.cancel'); ?>"
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
                       value="ticketform.save"/>
                <?php
                echo HTMLHelper::_('form.token'); ?>
            </form>
                <?php
        endif; ?>
    </div>
    <?php
}
?>
