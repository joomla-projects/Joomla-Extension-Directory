<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Jed\Component\Tickets\Administrator\Traits\TicketHandlingTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * Review Form model.
 *
 * @since 4.0.0
 */
class ReviewformModel extends FormModel
{
    use TicketHandlingTrait;
    /**
     * The item object
     *
     * @var   mixed
     * @since 4.0.0
     */
    private mixed $item = null;

    /**
     * Data Table
     *
     * @var   string
     * @since 4.0.0
     **/
    private string $dbtable = "#__jed_reviews";
    /**
     * Default ticket id
     *
     * @var   int
     * @since 4.0.0
     **/
    private int $id = -1;



    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form    A Form object on success, false on failure
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form
    {
        // Get the form.
        $form = $this->loadForm(
            'com_jed.review',
            'reviewform',
            [
                'control'   => $formname,
                'load_data' => $loadData,
            ]
        );

        if (!is_object($form)) {
            throw new Exception(Text::_('JERROR_LOADFILE_FAILED'), 500);
        }

        return $form;
    }

    /**
     * Method to get the table
     *
     * @param string $name
     * @param string $prefix  Optional prefix for the table class name
     * @param array  $options
     *
     * @return Table|bool Table if found, bool false on failure
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Review', $prefix = 'Administrator', $options = []): Table|bool
    {

        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed The default data is an empty array.
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_jed.edit.review.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {
            return $data;
        }

        return [];
    }

    /**
     * Method to autopopulate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    protected function populateState(): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_jed.edit.extension.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_jed.edit.extension.id', $id);
        }

        $this->setState('extension.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('extension.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to get an object.
     *
     * @param int|null $id The id of the object to get.
     *
     * @return object|bool Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem(int $id = null)
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('extension.id');
            }

            $user  = $this->getCurrentUser();
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);
            $query->select('id')->from($db->quoteName('#__jed_reviews'))->where($db->quoteName('extension_id') . ' = ' . $db->quote($id))
            ->where($db->quoteName('created_by') . ' = ' . $user->id)
            ->where($db->quoteName('published') . ' != -2');
            $db->setQuery($query);
            $id = $db->loadResult();

            // Get a level row instance.
            $table      = $this->getTable();
            $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

            if ($table !== false && $table->load($id) && !empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();
                if (empty($table->id) || JedHelper::isAdminOrSuperUser() || $table->created_by == $user->id) {
                    // Convert the Table to a clean stdClass.
                    $this->item               = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);
                    $this->item->extension_id = $id;

                    if (isset($this->item->category_id) && is_object($this->item->category_id)) {
                        $this->item->category_id = ArrayHelper::fromObject($this->item->category_id);
                    }
                } else {
                    throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
                }
            }
        }

        return $this->item;
    }

    /**
     * Returns Review ID
     *
     * @return int
     *
     * @since 4.0.0
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Method to save the form data.
     *
     * @param array $data The form data
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function save(array $data): bool
    {

        $id                 = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('extension.id');
        $data['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $isLoggedIn         = JedHelper::isLoggedIn();

        if ($id && $isLoggedIn) {
            /* Editing an existing review - a user may only ever edit their own. */
            $table = $this->getTable();
            $table->load($id);

            if ((int) $table->created_by !== (int) Factory::getApplication()->getIdentity()->id && !JedHelper::isAdminOrSuperUser()) {
                throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
            }

            $data['id'] = $id;
            // An edited review re-enters moderation rather than silently keeping its
            // previous approval.
            $data['published'] = 0;

            if ($table->save($data) === true) {
                $this->id = $table->id;

                return $table->id;
            }
            return false;
        }

        if (!$id && $isLoggedIn) {
            /* Any logged-in user can make a new review */

            $table = $this->getTable();

            if ($table->save($data) === true) {
                $this->id = $table->id;

                $this->triggerTicket(
                    TicketType::Review,
                    $table->id,
                    Text::sprintf('COM_JED_TICKET_NEW_REVIEW_EVENT', $data['title'] ?? $table->id)
                );

                return $table->id;
            }
            return false;
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }
}
