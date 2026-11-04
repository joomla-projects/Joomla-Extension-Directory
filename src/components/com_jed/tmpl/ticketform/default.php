<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
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

/** @var \Jed\Component\Jed\Site\View\Ticketform\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
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
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();
    } catch (Exception) {
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
    $default_values['exension_varied_name']    = $this->item->vr ?? 0;
    $default_values['ticket_category_type']    = $this->item->ticket_category_type;
    $default_values['allocated_group']         = $this->item->allocated_group; //Any
    $default_values['internal_notes']          = '';
    $default_values['uploaded_files_preview']  = '';
    $default_values['uploaded_files_location'] = '';
    $default_values['allocated_to']            = 0;

    $this->form->bind($default_values);


    $fieldsets['overview']['title']       = 'Enter Ticket Details';
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
    $fieldsets['overview']['hidden']      = ['id', 'linked_item_type', 'linked_item_id','ticket_origin', 'ticket_status', 'allocated_group','internal_notes','uploaded_files_preview','uploaded_files_location','allocated_to', 'parent_id', 'created_on','reported_varied_name','myextension_varied_name'];

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

                //echo "<pre>";print_r($fieldsets);echo "</pre>";

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
