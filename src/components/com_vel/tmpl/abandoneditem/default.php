<?php

/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;

/** @var \Jed\Component\Vel\Site\View\Abandoneditem\HtmlView $this */

?>

<div class="page-header">
    <h2 itemprop="headline"><?php echo $this->escape($this->item->extension_name); ?></h2>
</div>
<dl class="row">
    <dt class="col-3">Developer</dt>
    <dd class="col-9"><?php echo $this->escape($this->item->developer_name); ?></dd>

    <dt class="col-3">Version</dt>
    <dd class="col-9"><?php echo $this->escape($this->item->extension_version); ?></dd>

    <dt class="col-3">Extension URL</dt>
    <dd class="col-9"><?php echo $this->escape($this->item->extension_url); ?></dd>

    <dt class="col-3">Reported By</dt>
    <dd class="col-9"><?php echo $this->escape($this->item->reporter_fullname); ?></dd>

    <dt class="col-3">Date Submitted</dt>
    <dd class="col-9"><?php echo HTMLHelper::_('date', $this->item->date_submitted, 'd M Y'); ?></dd>
</dl>
<div itemprop="articleBody">
    <p><?php echo nl2br($this->escape($this->item->abandoned_reason)); ?></p>
</div>
