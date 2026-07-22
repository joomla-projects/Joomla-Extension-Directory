<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Site\View\Reviewform\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_jed');
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('com_jed.reviewForm-showHideEntryForm')
    ->useScript('com_jed.reviewForm-changeRequired');
HTMLHelper::_('bootstrap.tooltip');

$user    = $this->getCurrentUser();
$canEdit = JedHelper::canUserEdit($this->item);

$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();

echo LayoutHelper::render('review.guidelines', $this->extension_details);

?>
    <div id="reviewForm" style="display: none">
        <div class="review-edit front-end-edit">
                <form id="form-review"
                      action="<?php echo Route::_('index.php?option=com_jed&task=reviewform.save'); ?>"
                      method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

                    <?php
                    foreach ($this->form->getFieldsets() as $fieldset) {
                        echo $this->form->renderFieldset($fieldset->name);
                    }
                    ?>


                    <div class="control-group">
                        <div class="controls">

                            <?php if ($this->canSave) : ?>
                                <button type="submit" class="validate btn btn-primary"
                                        onclick="mfTest()">
                                    <span class="fas fa-check" aria-hidden="true"></span>
                                    <?php echo Text::_('JSUBMIT'); ?>
                                </button>
                            <?php endif; ?>
                            <a class="btn btn-danger"
                               href="<?php echo Route::_('index.php?option=com_jed&task=reviewform.cancel'); ?>"
                               title="<?php echo Text::_('JCANCEL'); ?>">
                                <span class="fas fa-times" aria-hidden="true"></span>
                                <?php echo Text::_('JCANCEL'); ?>
                            </a>
                          <?php /*  <button class="btn btn-info"
                                    onclick="mfTest()"
                            >
                                <span class="fas fa-times" aria-hidden="true"></span>
                                TEST
                            </button>
                            */?>
                        </div>
                    </div>

                    <input type="hidden" name="option" value="com_jed"/>
                    <input type="hidden" name="task"
                           value="reviewform.save"/>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
        </div>
    </div>

<?php
echo LayoutHelper::render('review.report', $this->extension_details);

?>
