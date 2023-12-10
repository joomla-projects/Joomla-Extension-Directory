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

use Jed\Component\Jed\Administrator\View\Extension\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Actionlogs\Administrator\Helper\ActionlogsHelper;

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
        ->useScript('webcomponent.field-fancy-select');
} catch (Exception $e) {
}

Text::script('COM_JED_EXTENSIONS_ERROR_DURING_SEND_EMAIL', true);
Text::script('COM_JED_EXTENSIONS_MISSING_MESSAGE_ID', true);
Text::script('COM_JED_EXTENSIONS_MISSING_DEVELOPER_ID', true);
Text::script('COM_JED_EXTENSIONS_MISSING_EXTENSION_ID', true);
Text::script('COM_JED_EXTENSIONS_ERROR_SAVING_APPROVE', true);
Text::script('COM_JED_EXTENSIONS_EXTENSION_APPROVED_REASON_REQUIRED', true);
Text::script('COM_JED_EXTENSIONS_ERROR_SAVING_PUBLISH', true);
Text::script('COM_JED_EXTENSIONS_EXTENSION_PUBLISHED_REASON_REQUIRED', true);

$extensionUrl = Uri::root() . 'extension/' . $this->item->alias;
$downloadUrl  = 'index.php?option=com_jed&task=extension.download&id=' . $this->item->id;

Factory::getDocument()
    ->addScriptOptions('joomla.userId', Factory::getUser()->id, false);

?>

<form action="index.php?option=com_jed&view=extension&layout=edit&id=<?php echo (int)$this->item->id; ?>" method="post" name="adminForm" id="extension-form" class="form-validate">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'extensionTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'extensionTab', $fieldset->name, Text::_($fieldset->label)); ?>
                <div class="row">
                    <div class="col-12 col-lg-6">
                        <?php echo $this->form->renderFieldset($fieldset->name); ?>
                    </div>
                </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endforeach; ?>

<!-- Legacy stuff from here on -->

            <?php
            echo HTMLHelper::_(
                'uitab.addTab',
                'extensionTab',
                'downloads',
                Text::_('COM_JED_EXTENSIONS_DOWNLOADS_TAB')
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
                                        echo Text::_('COM_JED_EXTENSIONS_DOWNLOAD_ALTERNATIVE_DOWNLOAD'); ?></h3>
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
                            Text::_('COM_JED_EXTENSIONS_REVIEWS_TAB')
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
                                    'index.php?option=com_jed&view=reviews&filter[extension]=' . $this->item->id,
                                    Text::_('COM_JED_EXTENSIONS_REVIEW_LINK') . ' <span class="icon-new-tab"></span>',
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
                Text::_('COM_JED_EXTENSIONS_COMMUNICATION_TAB')
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
                Text::_('COM_JED_EXTENSIONS_HISTORY_TAB')
            ); ?>
            <div class="row-fluid">
                <div class="span12">
                    <table class="table table-striped table-condensed">
                        <thead>
                        <tr>
                            <td><?php
                                echo Text::_('COM_JED_EXTENSION_HISTORY_DATE'); ?></td>
                            <td><?php
                                echo Text::_('COM_JED_EXTENSION_HISTORY_TYPE'); ?></td>
                            <td><?php
                                echo Text::_('COM_JED_EXTENSION_HISTORY_TEXT'); ?></td>
                            <td><?php
                                echo Text::_('COM_JED_EXTENSION_HISTORY_MEMBER'); ?></td>
                            <td><?php
                                echo Text::_('COM_JED_EXTENSION_HISTORY_USER'); ?></td>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        if (isset($this->item->history)) :
                            foreach ($this->item->history as $history) :
                                ?>
                                <tr><?php
                                ?>
                                <td><?php
                                echo HTMLHelper::_('date', $history->logDate, Text::_('COM_JED_DATETIME_FORMAT')); ?></td><?php
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
            echo HTMLHelper::_('uitab.endTab'); ?>


        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="option" value="com_jed"/>
    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
