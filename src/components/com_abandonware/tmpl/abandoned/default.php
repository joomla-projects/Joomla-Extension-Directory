<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Jed\Component\Abandonware\Site\View\Abandoned\HtmlView $this */

$listOrder = $this->state->get('list.ordering', 'a.abandoned_time');
$listDirn  = $this->state->get('list.direction', 'DESC');
?>

<div class="com-abandonware-list">
    <div class="page-header">
        <h1><?php echo $this->escape($this->params->get('page_heading', Text::_('COM_ABANDONWARE_LIST_HEADING'))); ?></h1>
    </div>

    <?php
    /*
     * The explanation is not decoration. This list says something about other people's products,
     * and a visitor who does not know what "abandoned" means here will read it as "broken" or
     * "unsafe" - neither of which it says. It states the process that produced each entry: a
     * signal, a contact attempt, a grace period, a decision.
     */
    ?>
    <div class="alert alert-info">
        <p class="mb-0"><?php echo Text::_('COM_ABANDONWARE_LIST_INTRO'); ?></p>
    </div>

    <form action="<?php echo htmlspecialchars(Uri::getInstance()->toString(), ENT_QUOTES, 'UTF-8'); ?>" method="post" name="adminForm" id="adminForm">
        <?php if (!empty($this->filterForm)) : ?>
            <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this, 'options' => ['filterButton' => false]]); ?>
        <?php endif; ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <?php echo Text::_('COM_ABANDONWARE_LIST_EMPTY'); ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <caption class="visually-hidden"><?php echo Text::_('COM_ABANDONWARE_LIST_HEADING'); ?></caption>
                    <thead>
                    <tr>
                        <th scope="col">
                            <?php echo HTMLHelper::_('grid.sort', 'COM_ABANDONWARE_HEADING_EXTENSION', 'a.extension_name', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col"><?php echo Text::_('COM_ABANDONWARE_HEADING_DEVELOPER'); ?></th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('grid.sort', 'COM_ABANDONWARE_HEADING_MARKED', 'a.abandoned_time', $listDirn, $listOrder); ?>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($this->items as $item) : ?>
                        <tr>
                            <th scope="row">
                                <a href="<?php echo Route::_('index.php?option=com_abandonware&view=case&id=' . (int) $item->id); ?>">
                                    <?php echo $this->escape($item->extension_name) ?: Text::_('COM_ABANDONWARE_UNNAMED_EXTENSION'); ?>
                                </a>
                                <?php if ($item->extension_version) : ?>
                                    <span class="text-muted small"><?php echo $this->escape($item->extension_version); ?></span>
                                <?php endif; ?>
                            </th>
                            <td><?php echo $this->escape($item->developer_name); ?></td>
                            <td><?php echo HTMLHelper::_('date', $item->abandoned_time, Text::_('DATE_FORMAT_LC3')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>

        <input type="hidden" name="filter_order" value="<?php echo $this->escape($listOrder); ?>"/>
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->escape($listDirn); ?>"/>
        <input type="hidden" name="task" value=""/>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <p class="mt-3">
        <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_abandonware&view=report'); ?>">
            <?php echo Text::_('COM_ABANDONWARE_BUTTON_REPORT_ONE'); ?>
        </a>
    </p>
</div>
