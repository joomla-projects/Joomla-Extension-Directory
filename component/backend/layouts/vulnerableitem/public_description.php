<?php
/**
 * @package           JED
 *
 * @subpackage        VEL
 *
 * @copyright     (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license           GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access to $displayData file
defined('_JEXEC') or die('Restricted access');

/** @var $displayData array */
$headerlabeloptions = array('hiddenLabel' => true);
$fieldhiddenoptions = array('hidden' => true);
?>

<div class="row vel-header-row">
    <div class="col-md-12 vel-header">
        <h1>Public Description
            <button type="button" class="" id="buildPublicDescription">
                <span class="icon-wand"></span>
            </button>
        </h1>
		<?php echo $displayData->renderField('public_description', null, null, $headerlabeloptions); ?>
    </div>
</div>
<div class="row vel-header-row">
    <div class="col-md-12 vel-header">
        <h1>Alias
            <button type="button" class="" id="buildAlias">
                <span class="icon-wand"></span>
            </button>
        </h1>
		<?php echo $displayData->renderField('alias', null, null, $headerlabeloptions); ?>
    </div>
</div>
   

