<?php
/**
 * @package     Jed\Component\Jed\Administrator\Traits
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

defined('_JEXEC') or die('Restricted access');

/**
 * @var array $displayData
 */
$i = 0;
?>
<div class="d-flex flex-row gap-1 align-items-center">
	<span aria-hidden="true" class="icon-tag"></span>
	<?php foreach ($displayData['categories'] as $cat): ?>
		<?php $i++ ?>
		<a href="<?= \Joomla\CMS\Router\Route::_(sprintf('index.php?option=com_jed&view=extensions&id=%d&catid=%d', $cat->id, $cat->parent_id)) ?>">
			<?= htmlentities($cat->title) ?>
		</a>
		<?php if ($i != count($displayData['categories'])): ?>
		<span class="text-muted">&bull;</span>
		<?php endif ?>
	<?php endforeach; ?>
</div>
