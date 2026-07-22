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

use DateInterval;
use Exception;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Michelf\Markdown;
use stdClass;

/**
 * Jed model.
 *
 * @since 4.0.0
 */
class ExtensionModel extends ItemModel
{
    use ExtensionUtilities;

    public const int UPDATE_STATUS_OLD = 1;

    public const int UPDATE_STATUS_RECENTLY = 2;

    public const int UPDATE_STATUS_WARNING = 3;

    /**
     * Interval during which an extension is considered new: 2 weeks
     *
     * @var   string (DateInterval argument)
     * @since 4.0.0
     */
    protected string $isNewInterval = 'P2W';

    /**
     * Interval during which an extension is considered old: 4 years
     *
     * @var   string (DateInterval argument)
     * @since 4.0.0
     */
    protected string $isOldInterval = 'P4Y';

    /**
     * Interval during which an extension is considered updated: 1 year
     *
     * @var   string (DateInterval argument)
     * @since 4.0.0
     */
    protected string $isRecentlyUpdatedInterval = 'P1Y';

    /**
     * Data Table
     *
     * @since 4.0.0
     **/
    private string $dbtable = "#__jed_extensions";

    /**
     * @var mixed  Item data
     *
     * @since 4.0.0
     */
    protected mixed $item = null;

    /**
     * Method to check in an item.
     *
     * @param int|null $id The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function checkin(int $id = null): bool
    {
        $id = (!empty($id)) ? $id : (int)$this->getState('extension.id');
        if ($id || JedHelper::userIDItem($id, $this->dbtable) || JedHelper::isAdminOrSuperUser()) {
            if ($id) {
                // Initialise the table
                $table = $this->getTable();

                // Attempt to check the row in.
                if (method_exists($table, 'checkin')) {
                    if (!$table->checkin($id)) {
                        return false;
                    }
                }
            }

            return true;
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }

    /**
     * Method to check out an item for editing.
     *
     * @param int|null $id The id of the row to check out.
     *
     * @return bool True on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function checkout(int $id = null): bool
    {
        $id = (!empty($id)) ? $id : (int)$this->getState('extension.id');

        if (!$id && !JedHelper::userIDItem($id, $this->dbtable) && !JedHelper::isAdminOrSuperUser()) {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }

        if ($id) {
            // Initialise the table
            $table = $this->getTable();

            // Get the current user object.
            $user = Factory::getApplication()->getIdentity();

            // Attempt to check the row out.
            if (method_exists($table, 'checkout') && !$table->checkout($user->id, $id)) {
                return false;
            }
        }

        return true;
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
                $pk = $this->getState('extension.id');
            }

            // Get a level row instance.
            $table = $this->getTable();

            // Attempt to load the row.
            if ($table && $table->load($pk)) { // Check published state.
                if (
                    ($published = $this->getState('filter.published')) && isset($table->state)
                    && $table->state != $published
                ) {
                    throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 403);
                }

                // Convert the Table to a clean stdClass.
                $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);
            }

            if (empty($this->item)) {
                throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 404);
            }
        }


        if (isset($this->item->created_by)) {
            $this->item->created_by_name = JedHelper::getUserById($this->item->created_by)->name;
        }

        if (isset($this->item->modified_by)) {
            $this->item->modified_by_name = JedHelper::getUserById($this->item->modified_by)->name;
        }

        // Load Category Hierarchy
        $this->item->category_hierarchy = $this->getCategoryHierarchy($this->item->catid);

        // Load Scores
        //$this->item->scores            = $this->getScores($this->item->id);
        //        $this->item->number_of_reviews = 0;
        //        $score                         = 0;
        //        $supplycounter                 = 0;
        //        $supplytype                    = '';
        //
        //        foreach ($this->item->scores as $s) {
        //            $supplycounter = $supplycounter + 1;
        //            $supplytype    = match ($s->supply_option_id) {
        //                1 => 'Free',
        //                2 => 'Paid',
        //                3 => 'Cloud',
        //            };
        //            $score         = $score + $s->functionality_score;
        //            $score         = $score + $s->ease_of_use_score;
        //            $score         = $score + $s->support_score;
        //            $score         = $score + $s->value_for_money_score;
        //            $score         = $score + $s->documentation_score;
        //
        //            $this->item->number_of_reviews = $this->item->number_of_reviews + $s->number_of_reviews;
        //        }

        //        $this->item->type         = $supplytype;
        //        $score                    = $score / $supplycounter;
        //        $this->item->score        = floor($score / 5);
        //        $this->item->score_string = JedscoreHelper::getStars($this->item->score);

        if ($this->item->number_of_reviews == 0) {
            $this->item->review_string = '';
        } elseif ($this->item->number_of_reviews == 1) {
            $this->item->review_string = '<span>' . $this->item->number_of_reviews . ' review</span>';
        } elseif ($this->item->number_of_reviews > 1) {
            $this->item->review_string = '<span>' . $this->item->number_of_reviews . ' reviews</span>';
        }

        // Load Reviews
        $this->item->reviews = $this->getReviews($this->item->id);

        if (!empty($this->item->logo)) {
            $this->item->logo_large = JedHelper::formatImage($this->item->logo, ImageSize::LARGE);
            $this->item->logo       = JedHelper::formatImage($this->item->logo, ImageSize::SMALL);
        }

        $this->item->developer_email   = JedHelper::getUserById($this->item->created_by)->email;
        //$this->item->developer_company = $this->getDeveloperName($this->item->created_by);

        return $this->item;
    }

    /**
     * Gets array of all reviews for extension
     *
     * @param int $extension_id
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getReviews(int $extension_id): array
    {
        $ret = [
            'Free'  => [],
            'Paid'  => [],
            'Cloud' => [],
        ];

        $db = $this->getDatabase();

        foreach (['Free' => 1, 'Paid' => 2, 'Cloud' => 3] as $key => $supplyId) {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__jed_reviews'))
                ->where($db->quoteName('extension_id') . ' = :extension_id')
                ->where($db->quoteName('supply_option_id') . ' = :supply_id')
                ->bind(':supply_id', $supplyId, ParameterType::INTEGER)
                ->bind(':extension_id', $extension_id, ParameterType::INTEGER);

            $ret[$key] = $db->setQuery($query)->loadObjectList() ?: [];
        }

        return $ret;
    }

    /**
     * Whether the given user has bookmarked this extension.
     *
     * @param int $extension_id
     * @param int $user_id
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function isFavorited(int $extension_id, int $user_id): bool
    {
        if (!$user_id) {
            return false;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__jed_favorites'))
            ->where($db->quoteName('extension_id') . ' = :extension_id')
            ->where($db->quoteName('user_id') . ' = :user_id')
            ->bind(':extension_id', $extension_id, ParameterType::INTEGER)
            ->bind(':user_id', $user_id, ParameterType::INTEGER);

        return (bool) $db->setQuery($query)->loadResult();
    }

    /**
     * Adds or removes a bookmark for the given user/extension pair, whichever applies.
     *
     * @param int $extension_id
     * @param int $user_id
     *
     * @return bool The new favorited state (true = just added, false = just removed).
     *
     * @since 4.0.0
     * @throws Exception
     */
    public function toggleFavorite(int $extension_id, int $user_id): bool
    {
        if (!JedHelper::isLoggedIn() || !$user_id) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 401);
        }

        $db = $this->getDatabase();

        if ($this->isFavorited($extension_id, $user_id)) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__jed_favorites'))
                ->where($db->quoteName('extension_id') . ' = :extension_id')
                ->where($db->quoteName('user_id') . ' = :user_id')
                ->bind(':extension_id', $extension_id, ParameterType::INTEGER)
                ->bind(':user_id', $user_id, ParameterType::INTEGER);
            $db->setQuery($query)->execute();

            return false;
        }

        $created = Factory::getDate()->toSql();
        $query   = $db->getQuery(true)
            ->insert($db->quoteName('#__jed_favorites'))
            ->columns($db->quoteName(['user_id', 'extension_id', 'created']))
            ->values(':user_id, :extension_id, :created')
            ->bind(':user_id', $user_id, ParameterType::INTEGER)
            ->bind(':extension_id', $extension_id, ParameterType::INTEGER)
            ->bind(':created', $created, ParameterType::STRING);
        $db->setQuery($query)->execute();

        return true;
    }

    /**
     * Get an instance of Table class
     *
     * @param string $name    Name of the Table class to get an instance of.
     * @param string $prefix  Prefix for the table class name. Optional.
     * @param array  $options Array of configuration values for the Table object. Optional.
     *
     * @return Table|bool Table if success, false on failure.
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = "ExtensionHistory", $prefix = "Administrator", $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     *
     * @return string
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getUpdateStatus(): string
    {
        if ($this->isOld()) {
            return self::UPDATE_STATUS_OLD;
        }

        if ($this->isRecentlyUpdated()) {
            return self::UPDATE_STATUS_RECENTLY;
        }

        return self::UPDATE_STATUS_WARNING;
    }

    /**
     * @return bool
     * @since  4.0.0
     * @throws Exception
     */
    public function isOld(): bool
    {
        $item      = $this->getItem();
        $dateLimit = Factory::getDate();
        $dateLimit->sub(new DateInterval($this->isOldInterval));
        $modified = Factory::getDate($item->modified);

        return $modified < $dateLimit;
    }

    /**
     * @return bool
     * @since  4.0.0
     * @throws Exception
     */
    public function isRecentlyUpdated(): bool
    {
        $item      = $this->getItem();
        $dateLimit = Factory::getDate();
        $dateLimit->sub(new DateInterval($this->isRecentlyUpdatedInterval));
        $modified = Factory::getDate($item->modified);

        return $modified > $dateLimit;
    }

    /**
     * Our prefilters will stop unpublished/unapproved extensions from being shown in the list results
     * But if the user accesses the URL directly then we want to show the correct message.  This could
     * be either that it does not exist in the db, or that it is unpublished, if so we should show the
     * correct unpublished message
     *
     * @return stdClass
     * @since  4.0.0
     * @throws Exception
     */
    public function noExtensionFoundMsg(): stdClass
    {
        $document = Factory::getApplication()->getDocument();
        $db       = $this->getDatabase();
        $query    = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('id'),
                    $db->quoteName('state'),
                    $db->quoteName('name'),
                    $db->quoteName('approved'),
                    $db->quoteName('approved_reason'),
                    $db->quoteName('approved_notes'),
                ]
            )
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = :eid')
            ->bind(':eid', $this->item->id, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadObject();
        $msg = [];

        if ($row && (int) $row->state === 0) {
            $document->setTitle($row->name . ' - Joomla! Extension Directory');
            $msg[] = '<h2>' . $row->name . '</h2>';

            if (!empty($row->approved_reason)) {
                $msg[] = Text::_('COM_JED_ERROR_EXTENSION_UNPUBLISHED_REASON');
                $msg[] = '<p>' . $row->approved_reason . '</p>';
            } else {
                $msg[] = Text::_('COM_JED_ERROR_EXTENSION_UNPUBLISHED');
            }

            if (!empty($row->approved_notes)) {
                $msg[] = $row->approved_notes;
            }

            $level = 'warning';
        } else {
            $level = 'info';
            $msg[] = Text::_('COM_JED_EXTENSION_NOT_FOUND_LABEL');
        }

        return (object)[
            'msg'   => implode('', $msg),
            'level' => $level,
        ];
    }

    /**
     * Method to auto-populate the model state.
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
            $id = $app->getUserState('com_jed.edit.extension.id');
        } else {
            $id = $app->getInput()->get('id');
            $app->setUserState('com_jed.edit.extension.id', $id);
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

        if (!$id && !JedHelper::userIDItem($id, $this->dbtable) && !JedHelper::isAdminOrSuperUser()) {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }

        $table->load($id);
        $table->state = $state;

        return $table->store();
    }
}
