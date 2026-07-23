<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Administrator\View\Extensions\HtmlView $this */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()
    ->addExtensionRegistryFile('com_jed');
$wa->usePreset('com_jed.autoComplete');

HTMLHelper::_('bootstrap.tooltip');

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
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_STATE_LABEL', 'a.state', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-1 d-none d-md-table-cell text-center">
                            <?php echo Text::_('COM_JED_EXTENSION_PENDING_LABEL'); ?>
                        </td>
                        <td scope="col" class="w-1 d-none d-md-table-cell text-center">
                            <?php echo Text::_('COM_JED_EXTENSION_HISTORY_LABEL'); ?>
                        </td>
                        <td scope="col" class="w-20 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_TITLE_LABEL', 'a.name', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                        <?php echo Text::_('JCATEGORY'); ?>
                        </td>

                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                            <?php echo Text::_('COM_JED_EXTENSION_DEVELOPER_LABEL'); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_TYPE_LABEL', 'a.type', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                            <?php echo Text::_('COM_JED_EXTENSION_REVIEWCOUNT_LABEL'); ?>
                        </td>
                        <td scope="col" class="w-5 d-none d-md-table-cell ">
                            <?php echo Text::_('COM_JED_EXTENSION_VERSIONS_LABEL'); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_LAST_UPDATED_LABEL', 'a.modified', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-10 d-none d-md-table-cell ">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_DATE_ADDED_LABEL', 'a.created', $listDirn, $listOrder); ?>
                        </td>
                        <td scope="col" class="w-3 d-none d-lg-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
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
                            <td class="text-center">
                                <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'extensions.', $canChange, 'cb'); ?>
                                <?php echo JedHelper::getApprovedIcon((int) $item->approved); ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $isPending = !empty($item->latest_history_id) && (int) $item->latest_history_id !== (int) $item->entry_version;
                                ?>
                                <?php if ($isPending) : ?>
                                    <a href="<?php echo Route::_('index.php?option=com_jed&view=extension&layout=compare&id=' . $item->id); ?>"
                                       title="<?php echo Text::_('COM_JED_EXTENSION_PENDING_DESC'); ?>"
                                       data-bs-toggle="tooltip">
                                        <span class="icon-refresh text-warning" aria-hidden="true"></span>
                                    </a>
                                <?php else : ?>
                                    <span class="icon-check text-muted"
                                          title="<?php echo Text::_('COM_JED_EXTENSION_UP_TO_DATE_LABEL'); ?>"
                                          data-bs-toggle="tooltip" aria-hidden="true"></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="<?php echo Route::_('index.php?option=com_jed&view=extension&layout=historylist&id=' . $item->id); ?>"
                                   class="jed-history-btn"
                                   data-extension-id="<?php echo (int) $item->id; ?>"
                                   title="<?php echo Text::_('COM_JED_EXTENSION_HISTORY_LABEL'); ?>">
                                    <span class="fa fa-timeline" aria-hidden="true"></span>
                                </a>
                            </td>
                            <td>
                                <div class="pull-left break-word">
                                    <?php if ($item->checked_out) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'extensions.', $canCheckin); ?>
                                    <?php endif; ?>
                                    <?php if ($canEdit) : ?>
                                        <?php echo HTMLHelper::_('link', 'index.php?option=com_jed&task=extension.edit&id=' . $item->id, $item->name); ?>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->name); ?>
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
                                <?php echo $item->developer; ?>
                            </td>
                            <td>
                                <?php echo  $item->type; ?>
                            </td>
                            <td>
                                <?php echo $item->reviewCount; ?>
                            </td>
                            <td>
                                <?php echo (int) $item->versions; ?>
                            </td>
                            <td>
                                <?php
                                echo JedHelper::prettyDate($item->modified);

                                ?>
                            </td>
                            <td>

                                <?php

                                echo JedHelper::prettyDate($item->created);

                                ?>
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

<!-- History Modal -->
<div class="modal fade" id="jed-history-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo Text::_('COM_JED_EXTENSION_HISTORY_LABEL'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body" id="jed-history-modal-body">
                <div class="text-center py-4">
                    <div class="spinner-border text-secondary" role="status">
                        <span class="visually-hidden"><?php echo Text::_('JLOADING'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modalEl  = document.getElementById('jed-history-modal');
    var modalBody = document.getElementById('jed-history-modal-body');
    var bsModal  = bootstrap.Modal.getOrCreateInstance(modalEl);
    var currentExtId = 0;

    function historyUrl(extensionId) {
        return 'index.php?option=com_jed&view=extension&layout=historylist&id=' + extensionId + '&tmpl=component';
    }

    function loadHistory(extensionId) {
        currentExtId = extensionId;
        modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-secondary" role="status"></div></div>';
        fetch(historyUrl(extensionId))
            .then(function (r) { return r.text(); })
            .then(function (html) { modalBody.innerHTML = html; });
    }

    // Open modal on history button click
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.jed-history-btn');
        if (!btn) { return; }
        e.preventDefault();
        loadHistory(btn.dataset.extensionId);
        bsModal.show();
    });

    // Activate version – delegated to modal body (content loaded dynamically)
    modalEl.addEventListener('click', function (e) {
        var link = e.target.closest('.jed-activate-version');
        if (!link) { return; }
        e.preventDefault();

        var extensionId = link.dataset.extensionId;
        var versionId   = link.dataset.versionId;
        var tokenName   = Joomla.getOptions('csrf.token');
        var formData    = new FormData();
        formData.append(tokenName, '1');

        link.closest('tr').style.opacity = '0.4';

        fetch(
            'index.php?option=com_jed&task=extension.activateVersion&extension_id=' + extensionId + '&id=' + versionId,
            { method: 'POST', body: formData }
        ).then(function () {
            loadHistory(extensionId);
        });
    });

    // Clear body on close so stale content is not shown on next open
    modalEl.addEventListener('hidden.bs.modal', function () {
        modalBody.innerHTML = '';
        currentExtId = 0;
    });
}());
</script>
