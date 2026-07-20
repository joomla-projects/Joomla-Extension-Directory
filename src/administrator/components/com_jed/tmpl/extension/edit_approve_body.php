<?php

/** @var \Jed\Component\Jed\Administrator\View\Extension\HtmlView $this */
/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

?>
<div class="container-fluid">
    <div class="text-error" id="js-approve-error"></div>
    <?php echo $this->form->renderFieldset('approve'); ?>
</div>
