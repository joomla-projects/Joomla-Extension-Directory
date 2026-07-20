<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Sampledata.JED2
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Menus\Administrator\Table\MenuTable;
use Joomla\Component\Menus\Administrator\Table\MenuTypeTable;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sampledata - Jed2 Plugin
 *
 * Creates a set of "mainmenu" menu items exercising the com_jed site views, including the
 * Top Rated / Most Reviewed / New / Recently Updated / Compatible with J4-J5-J6 presets of the
 * Extensions list view.
 *
 * @since  1.0.0
 */
class PlgSampledataJed2 extends CMSPlugin
{
    /**
     * Database object
     *
     * @var    DatabaseDriver
     *
     * @since  1.0.0
     */
    protected $db;

    /**
     * Affects constructor behavior. If true, language files will be loaded automatically.
     *
     * @var    boolean
     *
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    public function __construct(&$subject, $config = [])
    {
        parent::__construct($subject, $config);
        $this->setApplication(Factory::getApplication());
    }

    /**
     * Get an overview of the proposed sampledata.
     *
     * @return  object  Object containing the name, title, description, icon and steps.
     *
     * @since  1.0.0
     */
    public function onSampledataGetOverview()
    {
        $data              = new stdClass();
        $data->name        = $this->_name;
        $data->title       = Text::_('PLG_SAMPLEDATA_JED2_OVERVIEW_TITLE');
        $data->description = Text::_('PLG_SAMPLEDATA_JED2_OVERVIEW_DESC');
        $data->icon        = 'list';
        $data->steps       = 1;

        return $data;
    }

    /**
     * Creates the "mainmenu" menu items for browsing com_jed extensions (including the
     * Top Rated/Most Reviewed/New/Recently Updated/Compatible-with-J4-J5-J6 presets), searching,
     * logging in/registering, submitting a new extension and the user dashboard.
     *
     * @return  array|void  Will be converted into the JSON response to the module.
     *
     * @since  1.0.0
     */
    public function onAjaxSampledataApplyStep1()
    {
        if ($this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $db = Factory::getDbo();

        $componentIds = [
            'com_jed'     => $this->getComponentId($db, 'com_jed'),
            'com_tickets' => $this->getComponentId($db, 'com_tickets'),
            'com_vel'     => $this->getComponentId($db, 'com_vel'),
            'com_finder'  => $this->getComponentId($db, 'com_finder'),
            'com_users'   => $this->getComponentId($db, 'com_users'),
        ];

        if (!$componentIds['com_jed']) {
            $response            = [];
            $response['success'] = false;
            $response['message'] = Text::_('PLG_SAMPLEDATA_JED2_STEP1_NO_COM_JED');

            return $response;
        }

        $this->ensureMainMenuType($db);

        $messages = [];

        // --- Home -----------------------------------------------------------------------
        $this->createMenuItem($db, [
            'title'        => 'Home',
            'alias'        => 'home2',
            'path'         => 'home2',
            'link'         => 'index.php?option=com_jed&view=categories&id=0',
            'component_id' => $componentIds['com_jed'],
        ], 1, $messages);

        // --- Browse Extensions (parent, plain "#" url) + its 7 children -----------------
        $browseExtensionsId = $this->createMenuItem($db, [
            'title'        => 'Browse Extensions',
            'alias'        => 'browse-extensions',
            'path'         => 'browse-extensions',
            'link'         => '#',
            'type'         => 'url',
            'component_id' => 0,
        ], 1, $messages);

        if ($browseExtensionsId) {
            $presets = [
                [
                    'title' => 'Top Rated',
                    'alias' => 'top-rated',
                    'query' => 'list_fullordering=score_overall+DESC',
                ],
                [
                    'title' => 'Most Reviewed',
                    'alias' => 'most-reviewed',
                    'query' => 'list_fullordering=reviewcount+DESC',
                ],
                [
                    'title' => 'New',
                    'alias' => 'new',
                    'query' => 'list_fullordering=a.created+DESC',
                ],
                [
                    'title' => 'Recently updated',
                    'alias' => 'recently-updated',
                    'query' => 'list_fullordering=a.modified+DESC',
                ],
                [
                    'title' => 'Compatible with J4',
                    'alias' => 'compatible-with-j4',
                    'query' => 'filter_joomla_version=40',
                ],
                [
                    'title' => 'Compatible with J5',
                    'alias' => 'compatible-with-j5',
                    'query' => 'filter_joomla_version=50',
                ],
                [
                    'title' => 'Compatible with J6',
                    'alias' => 'compatible-with-j6',
                    'query' => 'filter_joomla_version=60',
                ],
            ];

            foreach ($presets as $preset) {
                $this->createMenuItem($db, [
                    'title'        => $preset['title'],
                    'alias'        => $preset['alias'],
                    'path'         => 'browse-extensions/' . $preset['alias'],
                    'link'         => 'index.php?option=com_jed&view=extensions&' . $preset['query'],
                    'component_id' => $componentIds['com_jed'],
                ], $browseExtensionsId, $messages);
            }
        }

        // --- Search / Login / Register / New Extension / Dashboard ----------------------
        $this->createMenuItem($db, [
            'title'        => 'Search',
            'alias'        => 'search',
            'path'         => 'search',
            'link'         => 'index.php?option=com_finder&view=search',
            'component_id' => $componentIds['com_finder'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'Login',
            'alias'        => 'login',
            'path'         => 'login',
            'link'         => 'index.php?option=com_users&view=login',
            'component_id' => $componentIds['com_users'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'Register',
            'alias'        => 'register',
            'path'         => 'register',
            'link'         => 'index.php?option=com_users&view=registration',
            'component_id' => $componentIds['com_users'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'New Extension',
            'alias'        => 'new-extension',
            'path'         => 'new-extension',
            'link'         => 'index.php?option=com_jed&view=newextension',
            'component_id' => $componentIds['com_jed'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'Dashboard',
            'alias'        => 'dashboard',
            'path'         => 'dashboard',
            'link'         => 'index.php?option=com_jed&view=dashboard',
            'component_id' => $componentIds['com_jed'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'Tickets',
            'alias'        => 'tickets',
            'path'         => 'tickets',
            'link'         => 'index.php?option=com_tickets&view=tickets',
            'component_id' => $componentIds['com_tickets'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'Ticketform',
            'alias'        => 'ticketform',
            'path'         => 'ticketform',
            'link'         => 'index.php?option=com_tickets&view=ticketform',
            'component_id' => $componentIds['com_tickets'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'Abandoned Extensions',
            'alias'        => 'abandoned-extensions',
            'path'         => 'abandoned-extensions',
            'link'         => 'index.php?option=com_vel&view=abandoneditems',
            'component_id' => $componentIds['com_vel'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'Report an Abandoned Extensions',
            'alias'        => 'report-abandoned-extensions',
            'path'         => 'report-abandoned-extensions',
            'link'         => 'index.php?option=com_vel&view=abandonedreport',
            'component_id' => $componentIds['com_vel'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'List of Vulnerable Extensions',
            'alias'        => 'vulnerable-extensions',
            'path'         => 'vulnerable-extensions',
            'link'         => 'index.php?option=com_vel&view=vulnerabilities',
            'component_id' => $componentIds['com_vel'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'List of Resolved Vulnerabilities',
            'alias'        => 'resolved-vulnerabilities',
            'path'         => 'resolved-vulnerabilities',
            'link'         => 'index.php?option=com_vel&view=vulnerabilities&state=2',
            'component_id' => $componentIds['com_vel'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'Report a Vulnerability',
            'alias'        => 'report-vulnerability',
            'path'         => 'report-vulnerability',
            'link'         => 'index.php?option=com_vel&view=vulnerabilityreport',
            'component_id' => $componentIds['com_vel'],
        ], 1, $messages);

        $this->createMenuItem($db, [
            'title'        => 'Report a Developer Update',
            'alias'        => 'developer-update',
            'path'         => 'developer-update',
            'link'         => 'index.php?option=com_vel&view=developerupdate',
            'component_id' => $componentIds['com_vel'],
        ], 1, $messages);

        foreach ($messages as $message) {
            $this->getApplication()->enqueueMessage($message);
        }

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::_('PLG_SAMPLEDATA_JED2_STEP1_SUCCESS');

        return $response;
    }

    /**
     * Looks up an extension's #__extensions.extension_id by name.
     *
     * @param DatabaseDriver $db   The database driver
     * @param string         $name The extension name (e.g. "com_jed")
     *
     * @return int
     *
     * @since 1.0.0
     */
    private function getComponentId(DatabaseDriver $db, string $name): int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('name') . ' = ' . $db->quote($name));
        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Makes sure the "mainmenu" menu type exists (it does on every normal Joomla site install;
     * this is just a defensive fallback).
     *
     * @param DatabaseDriver $db The database driver
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function ensureMainMenuType(DatabaseDriver $db): void
    {
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__menu_types'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote('mainmenu'));
        $db->setQuery($query);

        if ($db->loadResult()) {
            return;
        }

        $menuType              = new MenuTypeTable($db);
        $menuType->menutype    = 'mainmenu';
        $menuType->title       = 'Main Menu';
        $menuType->description = '';
        $menuType->client_id   = 0;
        $menuType->store();
    }

    /**
     * Creates a single "mainmenu" menu item as a last-child of the given parent.
     *
     * @param DatabaseDriver $db       The database driver
     * @param array          $data     title/alias/path/link/component_id, optionally type (defaults to "component")
     * @param int            $parentId The parent menu item id (1 = menu root)
     * @param array          $messages Collects a human-readable success/failure line for each item
     *
     * @return int The new menu item's id, or 0 on failure
     *
     * @since 1.0.0
     */
    private function createMenuItem(DatabaseDriver $db, array $data, int $parentId, array &$messages): int
    {
        $menuItem                    = new MenuTable($db);
        $menuItem->menutype          = 'mainmenu';
        $menuItem->title             = $data['title'];
        $menuItem->alias             = $data['alias'];
        $menuItem->path              = $data['path'];
        $menuItem->link              = $data['link'];
        $menuItem->type              = $data['type'] ?? 'component';
        $menuItem->published         = 1;
        $menuItem->parent_id         = $parentId;
        $menuItem->client_id         = 0;
        $menuItem->component_id      = $data['component_id'];
        $menuItem->img               = '';
        $menuItem->language          = '*';
        $menuItem->note              = '';
        $menuItem->browserNav        = 0;
        $menuItem->template_style_id = 0;
        $menuItem->home              = 0;
        $menuItem->params            = '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"",'
            . '"menu_image_css":"","menu_text":1,"menu_show":1,"page_title":"","show_page_heading":"",'
            . '"page_heading":"","pageclass_sfx":"","menu-meta_description":"","robots":""}';

        $menuItem->setLocation($parentId, 'last-child');

        if ($menuItem->store()) {
            $messages[] = Text::sprintf('PLG_SAMPLEDATA_JED2_MENUITEM_CREATED', $data['title']);

            return (int) $menuItem->id;
        }

        $messages[] = Text::sprintf('PLG_SAMPLEDATA_JED2_MENUITEM_FAILED', $data['title'], $menuItem->getError());

        return 0;
    }
}
