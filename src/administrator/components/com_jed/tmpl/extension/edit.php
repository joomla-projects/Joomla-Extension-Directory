<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Model\ReviewModel;
use Jed\Component\Jed\Administrator\View\Extension\HtmlView;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Actionlogs\Administrator\Helper\ActionlogsHelper;

$headerlabeloptions = ['hiddenLabel' => true, 'readonly' => true];
$fieldhiddenoptions = ['hidden' => true];
/**
*
 *
 * @var HtmlView $this
*/

HTMLHelper::_('script', 'com_jed/jed.js', ['version' => 'auto', 'relative' => true]);

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

Text::script('COM_JED_EXTENSION_ERROR_DURING_SEND_EMAIL_LABEL', true);
Text::script('COM_JED_EXTENSION_MISSING_MESSAGE_ID_LABEL', true);
Text::script('COM_JED_EXTENSION_MISSING_DEVELOPER_ID', true);
Text::script('COM_JED_EXTENSION_MISSING_EXTENSION_ID_LABEL', true);
Text::script('COM_JED_EXTENSION_ERROR_SAVING_APPROVE_LABEL', true);
Text::script('COM_JED_EXTENSION_EXTENSION_APPROVED_REASON_REQUIRED_LABEL', true);
Text::script('COM_JED_EXTENSION_ERROR_SAVING_PUBLISH_LABEL', true);
Text::script('COM_JED_EXTENSION_EXTENSION_PUBLISHED_REASON_REQUIRED_LABEL', true);

$extensionUrl = Uri::root() . 'extension/' . $this->extension->alias;
$downloadUrl  = 'index.php?option=com_jed&task=extension.download&id=' . $this->extension->id;

Factory::getDocument()
    ->addScriptOptions('joomla.userId', Factory::getUser()->id, false);

?>

<form action="index.php?option=com_jed&view=extension&layout=edit&id=<?php echo (int)$this->extension->id; ?>" method="post" name="adminForm" id="extension-form" class="form-validate">

    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php
        echo HTMLHelper::_('uitab.startTabSet', 'extensionTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]);

        foreach ($this->form->getFieldsets() as $fieldset) :
            echo HTMLHelper::_('uitab.addTab', 'extensionTab', $fieldset->name, Text::_($fieldset->label));
            ?>
                <div class="row">
                    <div class="col-12 col-lg-6">
                        <?php  echo $this->form->renderFieldset($fieldset->name); ?>
                    </div>
                </div>
            <?php
                echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endforeach; ?>
<?php
foreach ($this->extension->varied as $st) {
    echo HTMLHelper::_('uitab.addTab', 'extensionTab', 'varied-' . $st->supply_option_id, Text::_($st->supply_option_type));
    $varied_form                          = $this->extension->varied_form;
    $varied_form->bind($st);
    $fieldsets                            = [];
    $fieldsets['overview']['supply_type'] = $st->supply_type;
    $fieldsets['overview']['title']       =  '';
    $fieldsets['overview']['description'] = '';
    $fieldsets['overview']['fields']   = ['id', 'supply_option_id', ['title', 'is_default_data'],  'description'];
    $fieldsets['overview']['hidden']   = ['id', 'supply_option_id'];


    $fieldsets['links']['supply_type'] = $st->supply_type;
    $fieldsets['links']['title']       = Text::_('COM_JED_EXTENSION_LINKS_TITLE');
    $fieldsets['links']['description'] = '';
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
echo HTMLHelper::_('uitab.addTab', 'viewExtensionTab', 'viewextensionreviews', Text::_('Reviews', true));
?>

            <div class="container">
                <div class="row">
                    <?php

                    echo HTMLHelper::_('bootstrap.startAccordion', 'ticket_help_reviews_group', $slidesOptions);

                    $slideid = 0;
                    foreach ($this->extension->reviews as $rtype) {
                        foreach ($rtype as $review) {
                            $review = (object)$review;
                            if ($review->published === 1) {
                                $ico = '<span class="fas fa-bolt"></span>';
                            } else {
                                $ico = '';
                            }
                            echo HTMLHelper::_(
                                'bootstrap.addSlide',
                                'extension_' . $type . '_reviews_group',
                                $type . ' ' . $review->id . ' - ' . $review->title . '&nbsp;' .
                                JedHelper::prettyDate($review->created_on) . '&nbsp;',
                                'extension_' . $type . '_reviews_group' . '_slide' . ($slideid++)
                            );
                            $review_model = new ReviewModel();
                            $linked_form = $review_model->getForm($review, false, 'review');
                            $linked_form->bind($review);
                            ?>
                            <div class="col-md-4 ticket-header">
        <h1>Status - <?php echo $linked_form->renderField('published', null, null, $headerlabeloptions); ?>
                    &nbsp;&nbsp;<button id="btn_save_published" type="button" class="">
                        <span class="icon-save"></span>
                    </button>
                    </h1>
                    <p id="jform_review_status_updated" style="display:none">Status Updated</p>

                </div>
                <div class="row ticket-header-row">
                   
                    <div class="col-md-4  ticket-header">

                        <h1>Version - <?php echo $review->version; ?></h1>

                    </div>
                    <div class="col-md-4  ticket-header">

                        <h1>Type - <?php echo $review->supply_type; ?></h1>

                    </div>
                    <div class="col-md-4  ticket-header">

                        <h1>Reviewer - <?php echo $review->created_by_name; ?></h1>

                    </div>

                </div>
                <P>&nbsp;</P>
                <div class="row ticket-header-row">
                    <div class="col-md-6   ticket-header">
                            <?php echo $linked_form->renderField('title', null, null); ?>
                    </div>
                    <div class="col-md-6   ticket-header">

                            <?php echo $linked_form->renderField('alias', null, null); ?>
                    </div>

                </div>
                <div class="row ticket-header-row">
                    <div class="col-md-12   ticket-header">
                            <?php echo $linked_form->renderField('body', null, null); ?>
                    </div>
                    <div class="col-md-12   ticket-header">
                            <?php echo $linked_form->renderField('used_for', null, null); ?>
                    </div>
                </div>
                <div class="row ticket-header-row">
                    <div class="col-md-2   ticket-header">
                        <h1><?php echo Text::_('COM_JED_REVIEWS_FUNCTIONALITY_LABEL') . ' - ' . $review->functionality; ?></h1>
                    </div>
                    <div class="col-md-10   ticket-header">
                            <?php echo $linked_form->renderField('functionality_comment', null, null, $headerlabeloptions); ?>
                    </div>
                </div>
                <div class="row ticket-header-row">
                    <div class="col-md-2   ticket-header">
                        <h1><?php echo Text::_('COM_JED_REVIEWS_EASE_OF_USE_LABEL') . ' - ' . $review->ease_of_use; ?></h1>
                    </div>
                    <div class="col-md-10   ticket-header">
                            <?php echo $linked_form->renderField('ease_of_use_comment', null, null, $headerlabeloptions); ?>
                    </div>
                </div>
                <div class="row ticket-header-row">
                    <div class="col-md-2   ticket-header">
                        <h1><?php echo Text::_('COM_JED_GENERAL_SUPPORT_LABEL') . ' - ' . $review->support; ?></h1>
                    </div>
                    <div class="col-md-10   ticket-header">
                            <?php echo $linked_form->renderField('support_comment', null, null, $headerlabeloptions); ?>
                    </div>
                </div>
                <div class="row ticket-header-row">
                    <div class="col-md-2   ticket-header">
                        <h1><?php echo Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL') . ' - ' . $review->documentation; ?></h1>
                    </div>
                    <div class="col-md-10   ticket-header">
                            <?php echo $linked_form->renderField('documentation_comment', null, null, $headerlabeloptions); ?>
                    </div>
                </div>
                <div class="row ticket-header-row">
                    <div class="col-md-2   ticket-header">
                        <h1><?php echo Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL') . ' - ' . $review->value_for_money; ?></h1>
                    </div>
                    <div class="col-md-10   ticket-header">
                            <?php echo $linked_form->renderField('value_for_money_comment', null, null, $headerlabeloptions); ?>
                    </div>
                </div>
                <div class="row ticket-header-row">
                    <div class="col-md-2   ticket-header">
                        <h1><?php echo Text::_('COM_JED_REVIEWS_OVERALL_SCORE_LABEL') . ' - ' . $review->overall_score; ?></h1>
                    </div>
                    <div class="col-md-10   ticket-header">
                        <h1>Created on - <?php echo $review->created_on; ?>&nbsp;&nbsp;IP Address
                            - <?php echo $review->ip_address; ?></h1>
                    </div>
                </div>
                            <?php
                            echo HTMLHelper::_('bootstrap.endSlide');
                        }
                    }
                    echo HTMLHelper::_('bootstrap.endAccordion');

                    ?>


                </div>


        <?php
        echo HTMLHelper::_('uitab.endTab');
/*for ($this->item->varied)
            echo HTMLHelper::_('uitab.endTab');

            foreach ($this->extension->varied_data as $vr) {
                $varied_form = $this->extensionvarieddatum_form;

                $varied_form->bind($vr);
                echo HTMLHelper::_('uitab.addTab', 'extensionTab', 'viewextensionsupply_tab_' . $vr->supply_type, Text::_($vr->supply_type, true) . '&nbsp;' . Text::_('COM_JED_GENERAL_VERSION_LABEL', true));
                echo $varied_form->renderFieldset('info');

                echo $varied_form->renderField('tags');
                echo $varied_form->renderField('state');
                echo $varied_form->renderField('created_by');

                echo HTMLHelper::_('uitab.endTab');
            }
            //      echo "<pre>";print_r($this->extension);echo "</pre>";exit();
            ?>
<!-- Legacy stuff from here on -->

            <?php
            echo HTMLHelper::_(
                'uitab.addTab',
                'extensionTab',
                'downloads',
                Text::_('COM_JED_EXTENSION_DOWNLOADS_TAB_LABEL')
            ); ?>
            <div class="row-fluid">
                <div class="span12">
                    <div class="form-horizontal">
                        <?php
                                    echo $this->form->renderField('downloadIntegrationType'); ?>
                        <?php
                                    echo $this->form->renderField('requiresRegistration'); ?>
                        <?php
                                    echo $this->form->renderField('downloadIntegrationUrl'); ?>
                        <h3><?php
                                        echo Text::_('COM_JED_EXTENSION_DOWNLOAD_ALTERNATIVE_DOWNLOAD_LABEL'); ?></h3>
                        <?php
                                    echo $this->form->renderField('downloadIntegrationType1'); ?>
                        <?php
                                    echo $this->form->renderField('downloadIntegrationType2'); ?>
                        <?php
                                    echo $this->form->renderField('downloadIntegrationType3'); ?>
                        <?php
                                    echo $this->form->renderField('downloadIntegrationType4'); ?>
                    </div>
                </div>
            </div>
            <?php
                        echo HTMLHelper::_('uitab.endTab'); ?>

            <?php
                        echo HTMLHelper::_(
                            'uitab.addTab',
                            'extensionTab',
                            'reviews',
                            Text::_('COM_JED_TITLE_REVIEWS')
                        ); ?>
            <div class="row-fluid">
                <div class="span12">
                    <div class="form-horizontal">
                        <?php
                                    echo $this->form->renderFieldset('reviews'); ?>
                    </div>
                    <?php
                                echo HTMLHelper::_(
                                    'link',
                                    'index.php?option=com_jed&view=reviews&filter[extension]=' . $this->extension->id,
                                    Text::_('COM_JED_EXTENSION_REVIEW_LINK_LABEL') . ' <span class="icon-new-tab"></span>',
                                    'target="_blank"'
                                );
                                ?>
                </div>
            </div>
            <?php
            echo HTMLHelper::_('uitab.endTab'); ?>

            <?php
            echo HTMLHelper::_(
                'uitab.addTab',
                'extensionTab',
                'communication',
                Text::_('COM_JED_EXTENSION_COMMUNICATION_TAB')
            ); ?>
            <div class="row-fluid">
                <div class="span12">
                    <div class="form-horizontal">
                        <?php
                        echo $this->form->renderFieldset('communication'); ?>
                        <div class="control-group">
                            <div class="control-label">
                            </div>
                            <div class="controls">
                                <button class="btn btn-success js-messageType js-sendMessage" onclick="jed.sendMessage(); return false;">
                                    <?php
                                    echo Text::_('COM_JED_SEND_EMAIL'); ?>
                                </button>

                                <button class="btn btn-success js-messageType js-storeNote" style="display: none;" onclick="jed.storeNote(); return false;">
                                    <?php
                                    echo Text::_('COM_JED_STORE_NOTE'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            echo HTMLHelper::_('uitab.endTab'); ?>

            <?php
            echo HTMLHelper::_(
                'uitab.addTab',
                'extensionTab',
                'history',
                Text::_('COM_JED_EXTENSION_HISTORY_TAB')
            ); ?>
            <div class="row-fluid">
                <div class="span12">
                    <table class="table table-striped table-condensed">
                        <thead>
                        <tr>
                            <td><?php
                                echo Text::_('COM_JED_EXTENSION_HISTORY_DATE_LABEL'); ?></td>
                            <td><?php
                                echo Text::_('COM_JED_GENERAL_TYPE_LABEL'); ?></td>
                            <td><?php
                                echo Text::_('COM_JED_EXTENSION_MESSAGE_LABEL'); ?></td>
                            <td><?php
                                echo Text::_('COM_JED_EXTENSION_HISTORY_MEMBER'); ?></td>
                            <td><?php
                                echo Text::_('COM_JED_EXTENSION_HISTORY_USER'); ?></td>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        if (isset($this->extension->history)) :
                            foreach ($this->extension->history as $history) :
                                ?>
                                <tr><?php
                                ?>
                                <td><?php
                                echo HTMLHelper::_('date', $history->logDate, Text::_('COM_JED_GENERAL_DATETIME_FORMAT')); ?></td><?php
?>
                                <td><?php
                                                                echo Text::_('COM_JED_EXTENSION_HISTORY_LOG_' . $history->type); ?></td><?php

if ($history->type === 'mail') {
    ?>
                                    <td>
    <?php
    echo $history->subject; ?>
                                    <?php
                                    echo $history->body; ?>
                                    </td><?php
                                    ?>
                                    <td><?php
                                    echo $history->memberName; ?></td><?php
?>
                                    <td><?php
                                        echo HTMLHelper::_('link', 'index.php?option=com_users&task=user.edit&id=' . $history->developerId, $history->developerName); ?> &lt;<?php
        echo $history->developerEmail; ?>&gt;</td><?php
}
if ($history->type === 'note') {
    ?>
                                    <td>
    <?php
    echo $history->body; ?>
                                    </td><?php
                                    ?>
                                    <td><?php
                                    echo $history->memberName; ?></td><?php
?>
                                    <td><?php
                                        echo HTMLHelper::_('link', 'index.php?option=com_users&task=user.edit&id=' . $history->developerId, $history->developerName); ?></td><?php
} elseif ($history->type === 'actionLog') {
    ?>
                                    <td><?php
                                    echo ActionlogsHelper::getHumanReadableLogMessage($history); ?></td><?php
?>
                                    <td><?php
                                        echo $history->name; ?></td><?php
?>
                                    <td></td><?php
}
?></tr><?php
                            endforeach;
                        endif;
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            echo HTMLHelper::_('uitab.endTab'); */?>


        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="option" value="com_jed"/>
    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
