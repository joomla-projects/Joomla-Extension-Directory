<?php
/**
 * @package    JED
 *
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<button type="button" class="btn" data-bs-dismiss="modal">
	<?php echo Text::_('JCANCEL'); ?>
</button>
<button type="button" class="btn btn-success" onclick="jed.submitApprovedState(<?php echo $this->item->id; ?>);">
	<?php echo Text::_('JAPPLY'); ?>
</button>
