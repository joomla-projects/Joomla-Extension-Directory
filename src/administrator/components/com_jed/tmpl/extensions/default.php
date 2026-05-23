<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
use Jed\Component\Jed\Administrator\View\Extensions\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * $model->setUseExceptions(true)
*/

/**
*
 *
 * @var Joomla\CMS\WebAsset\WebAssetManager $wa
*/
try {
    $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
} catch (Exception $e) {
}
$wa->getRegistry()
    ->addExtensionRegistryFile('com_jed');
$wa->usePreset('com_jed.autoComplete')
    ->addInlineScript(
        <<<JS
    window.addEventListener('DOMContentLoaded', () => {
        jed.filterDeveloperAutocomplete();
    });
JS
    );

$user      = $this->getCurrentUser();
$userId    = $user->id;
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>
<form id="adminForm" action="<?php echo Route::_('index.php?option=com_jed&view=extensions'); ?>" method="post" name="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                <table class="table itemList" id="extensionList">
                    <caption class="visually-hidden">
                        <?php echo Text::_('COM_JED_EXTENSION_TABLE_CAPTION_LABEL'); ?>,
                        <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                        <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                    </caption>
                    <thead>
                    <tr>
                        <td class="w-1 ">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </td>
                        <td scope="col" class="w-1  d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JPUBLISHED', 'extensions.published', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-1  d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_APPROVED_LABEL', 'extensions.approved', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-20 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_TITLE_LABEL', 'extensions.title', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_CATEGORY_LABEL', 'categories.title', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_LAST_UPDATED_LABEL', 'extensions.modified_on', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_DATE_ADDED_LABEL', 'extensions.created_on', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_DEVELOPER_LABEL', 'users.name', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_TYPE_LABEL', 'extensions.type', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_REVIEWCOUNT_LABEL', 'extensions.reviewcount', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-3 d-none d-lg-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'extensions.id', $listDirn, $listOrder); ?>
                        </td>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($this->items as $i => $item) :
                        $ordering   = ($listOrder === 'extension.id');
                        $canCreate  = $user->authorise('core.create', 'com_jed.extension.' . $item->id);
                        $canEdit    = $user->authorise('core.edit', 'com_jed.extension.' . $item->id);
                        $canCheckin = $user->authorise(
                            'core.manage',
                            'com_checkin'
                        ) || $item->checked_out === $userId || $item->checked_out === 0;
                        $canEditOwn = $user->authorise(
                            'core.edit.own',
                            'com_jed.extension.' . $item->id
                        ) && $item->created_by === $userId;
                        $canChange = $user->authorise('core.edit.state', 'com_jed.extension.' . $item->id) && $canCheckin;
                        ?>
                        <tr>
                            <td>
                                <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                            </td>
                            <td class="center" width="50">
                                <?php
                                switch ($item->published) {
                                    // Rejected
                                case '-1':
                                    $icon = 'unpublish';
                                    break;
                                        // Approved
                                case '1':
                                    $icon = 'publish';
                                    break;
                                        // Awaiting response
                                case '2':
                                    $icon = 'expired';
                                    break;
                                        // Pending
                                case '0':
                                default:
                                    $icon = 'pending';
                                    break;
                                }
                                echo '<span class="icon-' . $icon . '" aria-hidden="true"></span>';
                                ?>
                            </td>
                            <td>
                                <?php
                                switch ($item->approved) {
                                    // Rejected
                                case '-1':
                                    $icon = 'unpublish';
                                    break;
                                // Approved
                                case '1':
                                    $icon = 'publish';
                                    break;
                                // Awaiting response
                                case '2':
                                    $icon = 'expired';
                                    break;
                                // Pending
                                case '0':
                                default:
                                    $icon = 'pending';
                                    break;
                                }
                                echo '<span class="icon-' . $icon . '" aria-hidden="true"></span>';
                                ?>
                            </td>
                            <td>
                                <div class="pull-left break-word">
                                    <?php if ($item->checked_out) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'extensions.', $canCheckin); ?>
                                    <?php endif; ?>
                                    <?php if ($canEdit) : ?>
                                        <?php echo HTMLHelper::_('link', 'index.php?option=com_jed&task=extension.edit&id=' . $item->id, $item->title); ?>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->title); ?>
                                    <?php endif; ?>
                                    <span class="small break-word">
                                        <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php echo $item->category; ?>
                            </td>
                            <td>
                                <?php
                                if (!is_null($item->modified_on)) {
                                    echo HTMLHelper::_(
                                        'date',
                                        $item->modified_on,
                                        Text::_('COM_JED_GENERAL_DATETIME_FORMAT')
                                    );
                                }
                                ?>
                            </td>
                            <td>

                                <?php echo HTMLHelper::_('date', $item->created_on, Text::_('COM_JED_GENERAL_DATETIME_FORMAT')); ?>
                            </td>
                            <td>
                                <?php echo $item->developer; ?>
                            </td>
                            <td>
                                <?php echo  $item->type; ?>
                            </td>
                            <td>
                                <?php echo $item->reviewCount; ?>
                            </td>
                            <td>
                                <?php echo $item->id; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>
                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
