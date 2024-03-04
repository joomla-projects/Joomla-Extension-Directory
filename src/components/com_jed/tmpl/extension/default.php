<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Jed\Component\Jed\Site\View\Extension\HtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Session\Session;
use Jed\Component\Jed\Administrator\Helper\JedHelper;

/**
 * @var \Jed\Component\Jed\Site\View\Extension\HtmlView $this
 */

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

$user       = Factory::getApplication()->getIdentity();
$userId     = $user->id;
$listOrder  = $this->state->get('list.ordering');
$listDirn   = $this->state->get('list.direction');
$canCreate  = $user->authorise('core.create', 'com_jed');
$canEdit    = $user->authorise('core.edit', 'com_jed');
$canCheckin = $user->authorise('core.manage', 'com_jed');
$canChange  = $user->authorise('core.edit.state', 'com_jed');
$canDelete  = $user->authorise('core.delete', 'com_jed');

// Import CSS
$this->document->getWebAssetManager()
    ->useStyle('com_jed.jazstyle');

?>
<div class="jed-cards-wrapper">
<article class="container mb-5">
    <header class="row gap-2">
        <div class="col d-flex flex-column gap-2 mb-3">
            <h2 class="fs-1 m-0 d-flex flex-row gap-2 align-items-center">
                <?php echo $this->escape($this->item->title) ?>
            </h2>
            <div class="d-flex flex-row gap-3">
                <div class="jed-extension-header__developer">By <a
                            href="#"><?php echo $this->item->created_by_name ?></a></div>
                <div class="stars-wrapper">
                    <?php echo $this->item->score_string ?>
                    <span class="text-muted">
                    <?php echo $this->item->review_string ?>
                </span>
                </div>
            </div>
            <?php echo $this->item->category_hierarchy ?>
        </div>
        <div class="col text-end">
            <?php $canCheckin = Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jed.' . $this->item->id); ?>
            <?php if ($canEdit) : ?>
                <a class="btn btn-sm btn-outline-primary" role="button"
                   href="<?php echo Route::_('index.php?option=com_jed&task=extension.edit&id=' . $this->item->id) ?>">
                    <span class="icon-pencil" aria-hidden="true"></span>
                    <?php echo Text::_("JACTION_EDIT") ?>
                </a>
            <?php endif; ?>
            <?php if (false) : // TODO Only show to the developer??>
            <a class="btn btn-sm btn-outline-warning" role="button"
               href="#">
                <span class="fa fa-life-ring" aria-hidden="true"></span>
                Support
            </a>
            <?php endif ?>
        </div>
    </header>

    <img src="<?php echo $this->item->logo ?>" alt="<?php echo $this->escape($this->item->title) ?>"
         class="rounded img-fluid mx-auto d-block" style="max-height: 525px">

    <div class="row gap-2">
        <div class="col-8 ">
            <?php echo $this->item->intro_text ?>
        </div>
        <div class="col">
            <dl>
                <div class="row">
                    <dt class="col">Version</dt>
                    <dd class="col"><?php echo $this->item->version ?></dd>
                </div>
                <div class="row">
                    <dt class="col">Last updated</dt>
                    <dd class="col">
                        <?php echo HTMLHelper::_('date', $this->item->modified_on, 'M j Y') ?>
                    </dd>
                </div>
                <div class="row">
                    <dt class="col">Date added</dt>
                    <dd class="col">
                        <?php echo HTMLHelper::_('date', $this->item->created_on, 'M j Y') ?>
                    </dd>
                </div>
                <div class="row">
                    <dt class="col">Includes</dt>
                    <dd class="col"><?php echo JedtrophyHelper::getTrophyIncludesStringFull($this->item->includes) ?></dd>
                </div>
                <div class="row">
                    <dt class="col">Compatibility</dt>
                    <dd class="col"><?php echo JedtrophyHelper::getTrophyVersionsStringFull($this->item->joomla_versions) ?></dd>
                </div>
                <div class="row">
                    <div class="col col-lg-8 offset-lg-2">
                        <a href="?option=com_jed&view=reviewform&action=add&extension_id=<?php echo $this->item->id ?>"
                           class="btn btn-outline-success">
                            <span class="fa fa-pencil" aria-hidden="true"></span>
                            Write a review
                        </a>
                    </div>
                </div>
            </dl>
        </div>
    </div>

    <?php echo HTMLHelper::_('uitab.startTabSet', 'supply_option_tabs') ?>
    <?php echo HTMLHelper::_('uitab.addTab', 'supply_option_tabs', 'supply_tab_' . $this->item->supply_type, $this->item->supply_type); ?>
    <div class="jed-wrapper jed-extension margin-bottom">
        <div class="jed-extension__image">
            <?php if ($this->item->logo) : ?>
                <img src="<?php echo $this->item->logo ?>" alt="<?php echo $this->escape($this->item->title) ?>"
                     class="rounded img-fluid mx-auto d-block" style="max-height: 525px">
            <?php endif; ?>
        </div>
        <div class="jed-grid jed-grid--2-1 margin-bottom">
            <div class="jed-grid__item">
                <div class="jed-subitem-intro mb-2">
                    <?php echo $this->item->intro_text ?>
                    <?php if (!empty(trim(strip_tags($this->item->description)))) : ?>
                        <?php HTMLHelper::_('bootstrap.collapse') ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary my-2"
                                data-bs-toggle="collapse" href="#description"
                                aria-expanded="false" aria-controls="description"
                        >
                            Show/hide
                        </button>
                    <?php endif ?>
                </div>

                <div class="jed-subitem-description mb-2 collapse" id="description">
                <?php echo $this->item->description ?>
                </div>

                <p class="button-group">
                    <a href="<?php echo $this->item->homepage_link ?>" class="button button--grey">Website</a>
                    <a href="<?php echo $this->item->demo_link ?>" class="button button--grey">Demo</a>
                    <a href="<?php echo $this->item->documentation_link ?>" class="button button--grey">Documentation</a>
                    <a href="<?php echo $this->item->support_link ?>" class="button button--grey">Support</a>
                    <a href="<?php echo $this->item->license_link ?>" class="button button--grey">License</a>
                </p>
            </div>
            <div class="jed-grid__item">
                <p>
                    <span class="button-group display-block align-right">
                        <a href="#" class="button button--grey">Report</a>
                        <a href="#" class="button button--grey">Share</a>
                    </span>
                </p>
            </div>
        </div>

        <div class="jed-grid jed-grid--1-2">
            <div class="jed-grid__item">
                <h2 class="heading heading--m">Reviews for free version</h2>
                <strong>4.0</strong>
                <div class="stars">
                    <div class="star"><span aria-hidden="true" class="icon-star"></span></div>
                    <div class="star"><span aria-hidden="true" class="icon-star"></span></div>
                    <div class="star"><span aria-hidden="true" class="icon-star"></span></div>
                    <div class="star"><span aria-hidden="true" class="icon-star"></span></div>
                    <div class="star"><span aria-hidden="true" class="icon-star-empty"></span></div>
                </div>
                <a href="#">132 reviews</a>
            </div>

        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
</article>
</div>



<?php //echo LayoutHelper::render('extension.extension-single', $this->item)?>

<?php $canCheckin = Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jed.' . $this->item->id) || $this->item->checked_out == Factory::getApplication()->getIdentity()->id; ?>
    <?php if ($canEdit && $this->item->checked_out == 0) : ?>
    <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_jed&task=extension.edit&id=' . $this->item->id) ?>"><?php echo Text::_("JACTION_EDIT") ?></a>
    <?php elseif ($canCheckin && $this->item->checked_out > 0) : ?>
    <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_jed&task=extension.checkin&id=' . $this->item->id . '&' . Session::getFormToken() . '=1') ?>"><?php echo Text::_("JLIB_HTML_CHECKIN") ?></a>

    <?php endif; ?>

<?php if (Factory::getApplication()->getIdentity()->authorise('core.delete', 'com_jed.extension.' . $this->item->id)) : ?>
    <a class="btn btn-danger" rel="noopener noreferrer" href="#deleteModal" role="button" data-bs-toggle="modal">
        <?php echo Text::_("JACTION_DELETE") ?>
    </a>

    <?php echo HTMLHelper::_(
        'bootstrap.renderModal',
        'deleteModal',
        [
                'title'  => Text::_('JACTION_DELETE'),
                'height' => '50%',
                'width'  => '20%',

                'modalWidth' => '50',
                'bodyHeight' => '100',
                'footer'     => '<button class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button><a href="' . Route::_('index.php?option=com_jed&task=extension.remove&id=' . $this->item->id, false, 2) . '" class="btn btn-danger">' . Text::_('JACTION_DELETE') . '</a>',
            ],
        Text::sprintf('COM_JED_DELETE_CONFIRM', $this->item->id)
    ) ?>

<?php endif; ?>
