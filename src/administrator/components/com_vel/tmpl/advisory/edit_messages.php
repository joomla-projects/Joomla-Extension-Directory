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

use Joomla\CMS\Layout\LayoutHelper;

/** @var \Jed\Component\Vel\Administrator\View\Advisory\HtmlView $this */

// The case thread: participants, messages, reply box. One layout, shared with the report view.
echo LayoutHelper::render('vel.thread', [
    'advisoryId' => (int) $this->item->id,
    'reportId'   => 0,
    'can'        => $this->can,
    'returnTask' => 'advisory.edit',
], JPATH_ADMINISTRATOR . '/components/com_vel/layouts');
