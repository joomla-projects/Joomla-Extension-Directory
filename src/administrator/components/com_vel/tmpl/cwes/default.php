<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Administrator\View\Cwes\HtmlView $this */
?>
<form action="<?php echo Route::_('index.php?option=com_vel&view=cwes'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
                <p class="text-muted"><?php echo Text::_('COM_VEL_CWES_INTRO'); ?></p>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
                <?php else : ?>
                    <table class="table table-striped">
                        <caption class="visually-hidden"><?php echo Text::_('COM_VEL_CWES_TITLE'); ?></caption>
                        <thead>
                        <tr>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_VEL_FIELD_CWE_ID_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('JSTATUS'); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_('COM_VEL_HEADING_USED'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $cwe) : ?>
                            <tr>
                                <td class="font-monospace"><a href="https://cwe.mitre.org/data/definitions/<?php echo (int) substr((string) $cwe->cwe_id, 4); ?>.html" target="_blank" rel="noopener"><?php echo $this->escape($cwe->cwe_id); ?></a></td>
                                <td><?php echo $this->escape($cwe->name); ?></td>
                                <td><?php echo $this->escape($cwe->status); ?></td>
                                <td><?php echo (int) $cwe->used; ?></td>
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
