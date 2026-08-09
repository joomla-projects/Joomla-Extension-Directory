<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Sampledata.JED
 *
 * @copyright   (C) 2023 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Table\Asset;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Event\SampleData\GetOverviewEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Database\DatabaseDriver;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sampledata - Jed Plugin
 *
 * @since  4.0.0
 */
class PlgSampledataJed extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Database object
     *
     * @var    DatabaseDriver
     *
     * @since  3.8.0
     */
    protected $db;

    /**
     * Affects constructor behavior. If true, language files will be loaded automatically.
     *
     * @var    boolean
     *
     * @since  3.8.0
     */
    protected $autoloadLanguage = true;

    public function __construct(&$subject, $config = [])
    {
        $this->setDatabase(Factory::getDBO());
        parent::__construct($subject, $config);
        $this->setApplication(Factory::getApplication());
    }

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onSampledataGetOverview'    => 'onSampledataGetOverview',
            'onAjaxSampledataApplyStep1' => 'onAjaxSampledataApplyStep1',
            'onAjaxSampledataApplyStep2' => 'onAjaxSampledataApplyStep2',
            'onAjaxSampledataApplyStep3' => 'onAjaxSampledataApplyStep3',
            'onAjaxSampledataApplyStep4' => 'onAjaxSampledataApplyStep4',
            'onAjaxSampledataApplyStep5' => 'onAjaxSampledataApplyStep5',
            'onAjaxSampledataApplyStep6' => 'onAjaxSampledataApplyStep6',
        ];
    }

    /**
     * Get an overview of the proposed sampledata.
     *
     * @param   GetOverviewEvent  $event  Event instance.
     *
     * @return  void
     *
     * @since  3.8.0
     */
    public function onSampledataGetOverview(GetOverviewEvent $event): void
    {
        $data              = new stdClass();
        $data->name        = $this->_name;
        $data->title       = Text::_('PLG_SAMPLEDATA_JED_OVERVIEW_TITLE');
        $data->description = Text::_('PLG_SAMPLEDATA_JED_OVERVIEW_DESC');
        $data->icon        = 'money';
        $data->steps       = 6;

        $event->addResult($data);
    }

    /**
     * Make sure we don't overwrite current admin user, move them to user_id=5
     *
     * @param   AjaxEvent  $event  Event instance.
     *
     * @return  void
     *
     * @since  3.8.0
     */
    public function onAjaxSampledataApplyStep1(AjaxEvent $event): void
    {
        if ($this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $this->moveCurrentUserToId5();

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::_('PLG_SAMPLEDATA_JED_STEP1_SUCCESS');

        $event->addResult($response);
    }

    /**
     * The sample data (extensions, reviews, tickets, ...) references user id 5 as its
     * author/owner throughout, so the currently logged-in admin is moved there. This only
     * runs when it's actually safe: a site can already have more than one Super User, and
     * blindly renumbering based on "the" member of that group used to silently delete
     * whichever other Super User account collided with id 5. Skip entirely rather than
     * touch/lose an existing account.
     *
     * @return  void
     *
     * @since  4.0.0
     */
    private function moveCurrentUserToId5(): void
    {
        $app           = $this->getApplication();
        $db            = $this->getDatabase();
        $identity      = $app->getIdentity();
        $currentUserId = (int) $identity->id;

        if ($currentUserId === 5 || $currentUserId < 1) {
            return;
        }

        $db->setQuery('SELECT id FROM #__users WHERE id = 5');

        if ($db->loadResult()) {
            // id 5 already belongs to a different, pre-existing account - leave it alone.
            return;
        }

        $db->setQuery('UPDATE #__users SET id = 5 WHERE id = ' . $currentUserId);
        $db->execute();

        $db->setQuery('UPDATE #__user_usergroup_map SET user_id = 5 WHERE user_id = ' . $currentUserId);
        $db->execute();

        $this->followRenumberedUserInSession($currentUserId);
    }

    /**
     * The session only stores the user id (see User::__sleep()), so after renumbering the account
     * User::__wakeup() no longer finds the old id on the next request and silently degrades the
     * identity to a guest. In the backend that turns every following wizard step into an
     * unauthorized request, so step 2 already fails before it starts. Point the running session at
     * the new id instead.
     *
     * @param   int  $previousUserId  The id the current user had before being moved to 5.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function followRenumberedUserInSession(int $previousUserId): void
    {
        $app     = $this->getApplication();
        $db      = $this->getDatabase();
        $session = $app->getSession();

        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById(5);

        $session->set('user', $user);
        $app->loadIdentity($user);

        // The session metadata row is keyed on the old id as well.
        $db->setQuery('UPDATE #__session SET userid = 5 WHERE userid = ' . $previousUserId);
        $db->execute();
    }

    /**
     * First step to enter the sampledata. Tags
     *
     * @param   AjaxEvent  $event  Event instance.
     *
     * @return  void
     *
     * @since  3.8.0
     */
    public function onAjaxSampledataApplyStep2(AjaxEvent $event): void
    {
        if ($this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $this->importFile(__DIR__ . '/sql/step2.sql');
        $db = $this->getDatabase();

        $db->setQuery('SELECT id FROM #__assets WHERE name = \'com_jed\'');
        $component_asset_id = $db->loadResult();
        $db->setQuery('DELETE FROM `#__assets` WHERE parent_id = \'' . $component_asset_id . '\';');
        $db->execute();
        $db->setQuery('INSERT INTO `#__assets` (parent_id, LEVEL, NAME, title, rules) SELECT ' . $component_asset_id . ' AS parent_id, level + 1 AS LEVEL, CONCAT(\'com_jed.category.\', id) AS NAME, title, \'{}\' AS rules FROM `#__categories` WHERE extension = \'com_jed\'');
        $db->execute();

        $db->setQuery('SELECT id FROM #__assets WHERE name = \'com_jed.category.9\'');
        $asset_id = $db->loadResult();

        $db->setQuery('UPDATE #__categories SET asset_id = id + ' . ($asset_id - 8) . ' WHERE id > 8');
        $db->execute();

        $table = new Asset($db, $this->getDispatcher());
        $table->rebuild();

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::_('PLG_SAMPLEDATA_JED_STEP2_SUCCESS');

        $event->addResult($response);
    }

    /**
     * Second step to enter the sampledata. Banners
     *
     * @param   AjaxEvent  $event  Event instance.
     *
     * @return  void
     *
     * @since  3.8.0
     */
    public function onAjaxSampledataApplyStep3(AjaxEvent $event): void
    {
        if ($this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $this->importFile(__DIR__ . '/sql/step3.sql');

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::_('PLG_SAMPLEDATA_JED_STEP3_SUCCESS');

        $event->addResult($response);
    }

    /**
     * Third step to enter the sampledata. Content 1/2
     *
     * @param   AjaxEvent  $event  Event instance.
     *
     * @return  void
     *
     * @since  3.8.0
     */
    public function onAjaxSampledataApplyStep4(AjaxEvent $event): void
    {
        if ($this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $this->importFile(__DIR__ . '/sql/step4.sql');

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::_('PLG_SAMPLEDATA_JED_STEP4_SUCCESS');

        $event->addResult($response);
    }

    /**
     * Fourth step to enter the sampledata. Content 2/2
     *
     * @param   AjaxEvent  $event  Event instance.
     *
     * @return  void
     *
     * @since  4.0.0
     */
    public function onAjaxSampledataApplyStep5(AjaxEvent $event): void
    {
        if ($this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $this->importFile(__DIR__ . '/sql/step5.sql');

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::_('PLG_SAMPLEDATA_JED_STEP5_SUCCESS');

        $event->addResult($response);
    }

    /**
     * Fifth step to enter the sampledata. Contacts
     *
     * @param   AjaxEvent  $event  Event instance.
     *
     * @return  void
     *
     * @since  3.8.0
     */
    public function onAjaxSampledataApplyStep6(AjaxEvent $event): void
    {
        if ($this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $this->importFile(__DIR__ . '/sql/step6.sql');

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::_('PLG_SAMPLEDATA_JED_STEP6_SUCCESS');

        $event->addResult($response);
    }

    protected function importFile($file)
    {
        $return = true;

        // Get the contents of the schema file.
        if (!($buffer = file_get_contents($file))) {
            Factory::getApplication()->enqueueMessage(Text::_('INSTL_SAMPLE_DATA_NOT_FOUND'), 'error');

            return false;
        }

        // Get an array of queries from the schema and process them.
        $queries = $this->splitQueries($buffer);

        foreach ($queries as $query) {
            // Trim any whitespace.
            $query = trim((string) $query);

            // If the query isn't empty and is not a MySQL or PostgreSQL comment, execute it.
            if (!empty($query) && ($query[0] != '#') && ($query[0] != '-')) {
                // Execute the query.
                $this->getDatabase()->setQuery($query);

                try {
                    $this->getDatabase()->execute();
                } catch (\RuntimeException $e) {
                    Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

                    $return = false;
                }
            }
        }

        return $return;
    }

    /**
     * Method to split up queries from a schema file into an array.
     *
     * @param   string  $query  SQL schema.
     *
     * @return  array  Queries to perform.
     *
     * @since   3.1
     */
    protected function splitQueries($query)
    {
        $buffer    = [];
        $queries   = [];
        $in_string = false;

        // Trim any whitespace.
        $query = trim($query);

        // Remove comment lines.
        $query = preg_replace("/\n\#[^\n]*/", '', "\n" . $query);

        // Remove PostgreSQL comment lines.
        $query = preg_replace("/\n\--[^\n]*/", '', "\n" . $query);

        // Find function.
        $funct = explode('CREATE OR REPLACE FUNCTION', (string) $query);

        // Save sql before function and parse it.
        $query = $funct[0];

        // Parse the schema file to break up queries.
        for ($i = 0; $i < strlen($query) - 1; $i++) {
            if ($query[$i] == ';' && !$in_string) {
                $queries[] = substr($query, 0, $i);
                $query     = substr($query, $i + 1);
                $i         = 0;
            }

            if ($in_string && ($query[$i] == $in_string) && $buffer[1] != "\\") {
                $in_string = false;
            } elseif (!$in_string && ($query[$i] == '"' || $query[$i] == "'") && (!isset($buffer[0]) || $buffer[0] != "\\")) {
                $in_string = $query[$i];
            }

            if (isset($buffer[1])) {
                $buffer[0] = $buffer[1];
            }

            $buffer[1] = $query[$i];
        }

        // If the is anything left over, add it to the queries.
        if (!empty($query)) {
            $queries[] = $query;
        }

        // Add function part as is.
        for ($f = 1, $fMax = count($funct); $f < $fMax; $f++) {
            $queries[] = 'CREATE OR REPLACE FUNCTION ' . $funct[$f];
        }

        return $queries;
    }
}
