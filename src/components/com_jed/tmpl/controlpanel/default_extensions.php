<?php

/**
 * @package JED
 *
 * @subpackage Extensions
 *
 * @copyright   (C) 2006 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
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
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');


$user        = $this->getCurrentUser();
$userId      = $user->id;
$listOrder   = $this->state->get('list.ordering');
$listDirn    = $this->state->get('list.direction');
$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();

$canCreate = $isLoggedIn;
//echo "<pre>";print_r($this->extension_items);echo "</pre>";

// Import CSS
//$wa = $this->getDocument()->getWebAssetManager();
//$wa->useStyle('com_jed.list');

?>

    <form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
          name="extensionsForm" id="extensionsForm">
        <div class="container"><div class="row">
        <div class="col-10"><?php echo '<fieldset class="myextensions"><legend>' . Text::_('COM_JED_EXTENSIONS_LIST_HEADER') . '</legend>' . Text::_('COM_JED_EXTENSIONS_LIST_DESCR') . '</fieldset>'; ?></div>
        <div class="col-2">
        <a href="index.php?option=com_jed&view=extensionform" class="btn btn-primary pull-right">Submit Extension</a></div>
            </div></div>
        <?php if (!empty($this->filterForm)) {
          //  echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]);
        } ?>
        <div class="table-responsive">
            <table class="table table-striped" id="ticketList">
                <thead>
                <tr>

                    <th class='left'>
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_EXTENSION_NAME_LABEL', 'a.`name`', $listDirn, $listOrder); ?>
                    </th>
                    <th class='left'>
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_VERSION_LABEL', 'a.`version`', $listDirn, $listOrder); ?>
                    </th>
                    <th class='left'>
                        <?php echo HTMLHelper::_('searchtools.sort', 'JCATEGORY', 'category_title', $listDirn, $listOrder); ?>
                    </th>
                    <th class='left'>
                        <?php echo HTMLHelper::_('searchtools.sort', 'Supply Type', 'a.`ticket_subject`', $listDirn, $listOrder); ?>
                    </th>

                    <th class='left'>
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_GENERAL_CREATED_ON_LABEL', 'a.`created_on`', $listDirn, $listOrder); ?>
                    </th>

                    <th class='left'>
                        <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.`ticket_status`', $listDirn, $listOrder); ?>
                    </th>

                    <?php if ($canEdit) : ?>
                        <th class="center">
                            <?php echo Text::_('COM_JED_GENERAL_ACTIONS_LABEL'); ?>
                        </th>
                    <?php endif; ?>

                </tr>
                </thead>
                <tfoot>
                <tr>
                    <td colspan="<?php echo isset($this->ticket_items[0]) ? count(get_object_vars($this->ticket_items[0])) : 10; ?>">
                        <?php echo $this->pagination->getListFooter(); ?>
                    </td>
                </tr>
                </tfoot>
                <tbody>
                <?php foreach ($this->extension_items as $i => $item) : ?>
                    <?php $canEdit = $this->getCurrentUser()->id == $item->created_by; ?>

                    <tr class="row<?php echo $i % 2; ?>">


                        <td>
                            <?php if ($canEdit) : ?>
                            <a href="<?php echo Route::_('index.php?option=com_jed&task=extensionform.edit&id=' . (int) $item->ext_id); ?>">
                            <?php endif; ?>
                            <?php  echo $item->title; ?>
                        </td>
                        <td>
                            <?php echo $item->version; ?>
                        </td>
                        <td>
                            <?php echo $item->category_title; ?>
                        </td>
                        <td>
                            <?php echo $item->supply_option_title; ?>
                        </td>

                        <td>

                            <?php try {
                                $d = new DateTime($item->created_on);
                                echo $d->format("d M y H:i");
                            } catch (Exception $e) {
                            }
                            ?>
                        </td>


                        <td>

                            <?php  echo $item->published ? 'Published' : 'Unpublished'; ?>
                        </td>





                        <?php if ($canEdit) : ?>
                            <td class="center">
                                <a
                                    href="<?php echo Route::_('index.php?option=com_jed&task=ticket.edit&id=' . $item->ext_id, false, 2); ?>"
                                    class="btn btn-mini" type="button"><i class="icon-edit"></i></a>


                            </td>
                        <?php endif; ?>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>


        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="boxchecked" value="0"/>
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>"/>
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>"/>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
