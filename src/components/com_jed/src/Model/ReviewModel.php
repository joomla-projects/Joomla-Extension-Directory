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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Registry\Registry;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;

/**
 * Jed model.
 *
 * @since 4.0.0
 */
class ReviewModel extends ItemModel
{
    use TicketHandlingTrait;

    public const int PROCESSING_WINDOW = 30;
    /**
     * Log how the score is generated.
     *
     * @var array
     *
     * @since 4.0.0
     */
    public array $log = [];
    /**
     * Admin test mode - outputs the log
     *
     * @var bool
     *
     * @since 4.0.0
     */
    public bool $testMode = false;
    /**
     * Set to false in review import from jed_migrate.
     * Determines if the extension review score should be calculated
     * when the model is stored
     *
     * @var bool
     *
     * @since 4.0.0
     */
    public bool $doScore = true;
    /**
     * Message to show if user can not add a review
     *
     * @var string
     *
     * @since 4.0.0
     */
    public string $accessMsg = '';
    /**
     * Owner id - used in review query
     *
     * @var int|null
     *
     * @since 4.0.0
     */
    protected ?int $owner_id = null;
    /**
     * Fields to score on
     *
     * @var array
     *
     * @since 4.0.0
     */
    protected array $score_fields;
    /**
     * Fields to score on
     *
     * @var array
     *
     * @since 4.0.0
     */
    protected array $ratings = ['functionality', 'ease_of_use', 'support', 'documentation', 'value_for_money'];
    /**
     * Count all reviews regardless of language filter
     *
     * @var int
     *
     * @since 4.0.0
     */
    protected int $totalAll = 0;

    /**
     * The item object
     *
     * @var   object
     * @since 4.0.0
     */
    public $item;

    protected int $defaultLimit = 10;

    // 30 Minutes of Processing Window
    /**
     * Data Table
     *
     * @var   string
     * @since 4.0.0
     **/
    private string $dbtable = "#__jed_reviews";

    /**
     * Method to delete an item
     *
     * @param int $id Element id
     *
     * @return bool
     * @since  4.0.0
     * @throws Exception
     */
    public function delete(int $id): bool
    {
        $table = $this->getTable();

        if (empty($result) || JedHelper::isAdminOrSuperUser() || $table->created_by == Factory::getApplication()->getIdentity()->id) {
            return $table->delete($id);
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }

    /**
     * Soft-deletes a review the current user wrote (or, for staff, any review) by setting
     * `published = -2`, rather than removing the row - so the same extension/user pair remains
     * available for a fresh review later on.
     *
     * @param int $id The review id
     *
     * @return bool
     * @since  4.1.0
     * @throws Exception
     */
    public function softDeleteOwn(int $id): bool
    {
        $table = $this->getTable();

        if (!$table->load($id)) {
            throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 404);
        }

        if ((int) $table->created_by !== (int) Factory::getApplication()->getIdentity()->id && !JedHelper::isAdminOrSuperUser()) {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }

        $table->published = -2;

        return $table->store();
    }

    /**
     * Lets an extension owner/maintainer retract their own developer response
     * (developer_response_published = -2). The response text itself is kept, just hidden.
     *
     * @param int $id The review id
     *
     * @return bool
     * @since  4.1.0
     * @throws Exception
     */
    public function deleteOwnResponse(int $id): bool
    {
        $table = $this->getTable();

        if (!$table->load($id)) {
            throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 404);
        }

        if (!JedHelper::isOwnerOrMaintainer((int) $table->extension_id) && !JedHelper::isAdminOrSuperUser()) {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }

        $table->developer_response_published = -2;

        return $table->store();
    }

    /**
     * Saves an extension owner's/maintainer's response to a review, resetting it into
     * moderation - mirrors how a new review itself always starts unpublished.
     *
     * @param int    $id   The review id
     * @param string $text The response text
     *
     * @return bool
     * @since  4.1.0
     * @throws Exception
     */
    public function saveResponse(int $id, string $text): bool
    {
        $table = $this->getTable();

        if (!$table->load($id)) {
            throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 404);
        }

        if (!JedHelper::isOwnerOrMaintainer((int) $table->extension_id)) {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }

        $table->developer_response           = $text;
        $table->developer_responded_on       = Factory::getDate()->toSql();
        $table->developer_response_published = 0;

        $result = $table->store();

        if ($result) {
            $this->triggerTicket(
                TicketType::DeveloperResponse,
                $id,
                Text::sprintf('COM_JED_TICKET_NEW_DEVELOPER_RESPONSE_EVENT', $table->title ?? $id)
            );
        }

        return $result;
    }

    /**
     * Method to get an object.
     *
     * @param int $pk The id of the object to get.
     *
     * @return mixed    Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem($pk = null): mixed
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($pk)) {
                $pk = $this->getState('review.id');
            }

            // Get a level row instance.
            $table = $this->getTable();

            // Attempt to load the row.
            if ($table && $table->load($pk)) {
                if (empty($result) || JedHelper::isAdminOrSuperUser()) {
                    // Check published state.
                    if ($published = $this->getState('filter.published')) {
                        if (isset($table->state) && $table->state != $published) {
                            throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 403);
                        }
                    }

                    // Convert the Table to a clean stdClass.
                    $properties     = get_object_vars($table);
                    $this->item     = ArrayHelper::toObject($properties);

                    if (property_exists($this->item, 'params')) {
                        $registry           = new Registry($this->item->params);
                        $this->item->params = $registry->toArray();
                    }
                } else {
                    throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
                }
            }
        }


        /* if (isset($this->item->extension_id) && $this->item->extension_id != '') {
            if (is_object($this->item->extension_id)) {
                $this->item->extension_id = ArrayHelper::fromObject($this->item->extension_id);
            }

            $values = (is_array($this->item->extension_id)) ? $this->item->extension_id : explode(
                ',',
                $this->item->extension_id
            );

            $textValue = [];

            foreach ($values as $value) {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true);

                $query
                    ->select('`je`.`title`')
                    ->from($db->quoteName('#__jed_extensions', 'je'))
                    ->where($db->quoteName('id') . ' = ' . $db->quote($value));

                $db->setQuery($query);
                $results = $db->loadObject();

                if ($results) {
                    $textValue[] = $results->title;
                }
            }

            $this->item->extension_id = !empty($textValue) ? implode(', ', $textValue) : $this->item->extension_id;
        } */

        if (isset($this->item->created_by)) {
            $this->item->created_by_name = JedHelper::getUserById($this->item->created_by)->name;
        }

        return $this->item;
    }


    /**
     * Get an instance of Table class
     *
     * @param string $name    Name of the Table class to get an instance of.
     * @param string $prefix  Prefix for the table class name. Optional.
     * @param array  $options Array of configuration values for the Table object. Optional.
     *
     * @return Table|bool Table if success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Review', $prefix = 'Administrator', $options = []): Table|bool
    {
        return parent::getTable($name, $prefix, $options);
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
        $app  = Factory::getApplication();
        $user = Factory::getApplication()->getIdentity();

        // Check published state
        if ((!$user->authorise('core.edit.state', 'com_jed')) && (!$user->authorise('core.edit', 'com_jed'))) {
            $this->setState('filter.published', 1);
            $this->setState('filter.archived', 2);
        }

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_jed.edit.review.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_jed.edit.review.id', $id);
        }

        $this->setState('review.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('review.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Publish the element
     *
     * @param int $id    Item id
     * @param int $state Publish state
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function publish(int $id, int $state): bool
    {
        $table = $this->getTable();
        if ($id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            $table->load($id);
            $table->state = $state;

            return $table->store();
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }

    /**
     * Constructor
     *
     * @param array $config An array of configuration options (name, state, dbo, table_path, ignore_request).
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->score_fields = [
            'functionality'   => Text::_('COM_JED_REVIEWS_FUNCTIONALITY_LABEL'),
            'ease_of_use'     => Text::_('COM_JED_REVIEWS_EASE_OF_USE_LABEL'),
            'support'         => Text::_('COM_JED_GENERAL_SUPPORT_LABEL'),
            'documentation'   => Text::_('COM_JED_EXTENSION_DOCUMENTATION_LABEL'),
            'value_for_money' => Text::_('COM_JED_REVIEWS_VALUE_FOR_MONEY_LABEL'),
        ];
    }
}
