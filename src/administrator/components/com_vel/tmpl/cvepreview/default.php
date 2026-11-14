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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Jed\Component\Vel\Administrator\View\Cvepreview\HtmlView $this */
?>
<div class="com-vel-cvepreview">
    <?php if ($this->json === '') : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_VEL_CVEPREVIEW_NOTHING'); ?></div>
    <?php else : ?>
        <?php if ($this->problems === []) : ?>
            <div class="alert alert-success"><?php echo Text::_('COM_VEL_CVEPREVIEW_VALID'); ?></div>
        <?php else : ?>
            <div class="alert alert-warning">
                <strong><?php echo Text::_('COM_VEL_CVEPREVIEW_INVALID'); ?></strong>
                <ul class="mb-0">
                    <?php foreach ($this->problems as $problem) : ?>
                        <li><?php echo $this->escape(Text::sprintf(...(array) $problem)); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="mb-2 d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo Route::_('index.php?option=com_vel&task=cve.generate&id=' . $this->id . '&shape=record&' . Session::getFormToken() . '=1'); ?>"><?php echo Text::_('COM_VEL_BUTTON_CVE_DOWNLOAD_RECORD'); ?></a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo Route::_('index.php?option=com_vel&task=cve.generate&id=' . $this->id . '&shape=cna&' . Session::getFormToken() . '=1'); ?>"><?php echo Text::_('COM_VEL_BUTTON_CVE_DOWNLOAD_CNA'); ?></a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('vel-cve-json').textContent)"><?php echo Text::_('COM_VEL_BUTTON_COPY'); ?></button>
        </div>

        <pre id="vel-cve-json" class="bg-light border p-3 small" style="max-height: 70vh; overflow: auto;"><?php echo $this->escape($this->json); ?></pre>
    <?php endif; ?>
</div>
