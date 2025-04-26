<?php

/**
 * @package       JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Jed\Component\Jed\Site\View\Extension\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * @var HtmlView $this
 */

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');
$isLoggedIn  = JedHelper::isLoggedIn();
$redirectURL = JedHelper::getLoginlink();
if (!$isLoggedIn) {
    try {
        $app = Factory::getApplication();
        $app->enqueueMessage(Text::_('COM_JED_CONTROLPANEL_NO_ACCESS_LABEL'), 'success');
        $app->redirect($redirectURL);
    } catch (Exception $e) {
        echo $e->getMessage();
    }
} else {

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
    $this->document->getWebAssetManager()->useStyle('com_jed.jazstyle');

    ?>

<?php
    echo HTMLHelper::_('uitab.startTabSet', 'controlpanel_tabs');

    echo HTMLHelper::_('uitab.addTab', 'controlpanel_tabs', 'tickets_tab' . "My Tickets", "My Tickets");
    //echo LayoutHelper::render('controlpanel.tickets', $this->tickets);
    echo $this->loadTemplate('tickets');
    echo HTMLHelper::_('uitab.endTab');

    echo HTMLHelper::_('uitab.addTab', 'controlpanel_tabs', 'extensions_tab' . "My Extensions", "My Extensions");
    echo "Hello";
    echo HTMLHelper::_('uitab.endTab');


    echo HTMLHelper::_('uitab.addTab', 'controlpanel_tabs', 'profile_tab' . "My Profile", "My Profile");


    // Load user_profile plugin language
    $lang = $this->getLanguage();
    $lang->load('plg_user_profile', JPATH_ADMINISTRATOR);
    //   echo LayoutHelper::render('controlpanel.profile', $this->profileform);
    echo $this->loadTemplate('userprofile');
    echo HTMLHelper::_('uitab.endTab');


    echo HTMLHelper::_('uitab.endTabSet');


}
//echo LayoutHelper::render('extension.extension-single', $this->item)
