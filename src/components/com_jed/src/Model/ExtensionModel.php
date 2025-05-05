<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use DateInterval;
use Exception;
use Jed\Component\Jed\Site\MediaHandling\ImageSize;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Registry\Registry;
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

    public const UPDATE_STATUS_OLD = 1;

    public const UPDATE_STATUS_RECENTLY = 2;

    public const UPDATE_STATUS_WARNING = 3;

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
        } else {
            throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
        }
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

                // Convert the Table to a clean CMSObject.
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
        $this->item->category_hierarchy = $this->getCategoryHierarchy($this->item->primary_category_id);

        // Load Varied Data
        $this->item->varied_data = $this->getVariedData($this->item->id);

        foreach ($this->item->varied_data as $v) {
            if ($v->is_default_data !== 1) {
                continue;
            }
            $this->item->title = $v->title;
            $this->item->alias = $v->alias;

            $this->item->intro_text   = $v->intro_text;
            $this->item->support_link = $v->support_link;
        }

        // Load Scores
        $this->item->scores            = $this->getScores($this->item->id);
        $this->item->number_of_reviews = 0;
        $score                         = 0;
        $supplycounter                 = 0;
        $supplytype                    = '';

        foreach ($this->item->scores as $s) {
            $supplycounter = $supplycounter + 1;
            $supplytype    = match ($s->supply_option_id) {
                1 => 'Free',
                2 => 'Paid',
                3 => 'Cloud',
            };
            $score         = $score + $s->functionality_score;
            $score         = $score + $s->ease_of_use_score;
            $score         = $score + $s->support_score;
            $score         = $score + $s->value_for_money_score;
            $score         = $score + $s->documentation_score;

            $this->item->number_of_reviews = $this->item->number_of_reviews + $s->number_of_reviews;
        }

        $this->item->type         = $supplytype;
        $score                    = $score / $supplycounter;
        $this->item->score        = floor($score / 5);
        $this->item->score_string = JedscoreHelper::getStars($this->item->score);

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
            $this->item->logo = JedHelper::formatImage($this->item->logo, ImageSize::SMALL);
        }

        $this->item->developer_email   = JedHelper::getUserById($this->item->created_by)->email;
        $this->item->developer_company = $this->getDeveloperName($this->item->created_by);

        return $this->item;
    }

    /**
     * Get the id of an item by alias
     *
     * @param string $alias Item alias
     *
     * @return mixed
     * @since  4.0.0
     * @throws Exception
     */
    public function getItemIdByAlias(string $alias)
    {
        $table      = $this->getTable();
        $properties = $table->getProperties();
        $result     = null;
        $aliasKey   = null;

        $aliasKey = JedHelper::getAliasFieldNameByView('extension');

        if (key_exists('alias', $properties)) {
            $table->load(['alias' => $alias]);
            $result = $table->id;
        } elseif (isset($aliasKey) && key_exists($aliasKey, $properties)) {
            $table->load([$aliasKey => $alias]);
            $result = $table->id;
        }

        if (
            empty($result) || JedHelper::isAdminOrSuperUser()
            || $table->get('created_by') == Factory::getApplication()->getIdentity()->id
        ) {
            return $result;
        }

        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
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
     * Get array of review scores for extension
     *
     * @param int $extension_id
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getScores(int $extension_id): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_extension_scores'))
            ->where($db->quoteName('extension_id') . ' = :extension_id')
            ->bind(':extension_id', $extension_id, ParameterType::INTEGER);

        $result = $db->setQuery($query)->loadObjectList();

        foreach ($result as $r) {
            $supply          = match ($r->supply_option_id) {
                1 => 'Free',
                2 => 'Paid',
                3 => 'Cloud',
            };
            $retval[$supply] = $r;
        }

        return $retval;
    }

    /**
     * Get array of supply types for extension
     *
     * @param int $extension_id
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getSupplyTypes(int $extension_id): array
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);
        $query2 = $db->getQuery(true);

        $query->select(
            [
            $db->quoteName('supply_options.id', 'supply_id'),
            $db->quoteName('supply_options.title', 'supply_type'),
            ]
        )
            ->from($db->quoteName('#__jed_extension_supply_options', 'supply_options'));


        $query2->select(
            [
            $db->quoteName('supply_option_id', 'supply_option_id'),
            ]
        )
            ->from($db->quoteName('#__jed_extension_varied_data', 'a'))
            ->where(
                [
                $db->quoteName('extension_id') . ' = ' . (int)$extension_id,
                ]
            );

        $query->where($db->quoteName('supply_options.id') . ' IN (' . $query2 . ')');
        $query->where($db->quoteName('state') . ' = 1');
        return $db->setQuery($query)->loadObjectList();
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
    public function getTable($name = "Extension", $prefix = "Administrator", $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     *
     * @return string
     *
     * @since  version
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
        $modified = Factory::getDate($item->modified_time);

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
        $modified = Factory::getDate($item->core_modified_time->value);

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
     */
    public function noExtensionFoundMsg(): stdClass
    {
        $document = Factory::getApplication()->getDocument();
        $db       = $this->getDatabase();
        $query    = $db->getQuery(true)
            ->select(
                [
                $db->quoteName('e.id'),
                $db->quoteName('state'),
                $db->quoteName('title'),
                $db->quoteName('approved'),
                $db->quoteName('code'),
                $db->quoteName('created_time'),
                $db->quoteName('s.message', 'message'),
                ]
            )
            ->from('#__jed_extensions', 'e')
            ->leftJoin('#__jed_extensions_status AS s ON s.extension_id = e.id')
            ->leftJoin(
                $db->quoteName('#__jed_extensions_status', 's'),
                $db->quoteName('s.extension_id') . ' = ' . $db->quoteName('e.id')
            )
            ->where($db->quoteName('e.id') . ' = :eid ')
            ->order($db->quoteName('s.created_time') . ' DESC')
            ->bind(':eid', $this->item->id, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadObject();
        $msg = [];

        if ($row && (int)$row->core_state === 0) {
            $code = json_decode($row->code);

            if (!empty($code)) {
                $document->setTitle($row->title . ' - Joomla! Extension Directory');
                $msg[] = '<h2>' . $row->title . '</h2>';
                $msg[] = Text::_('COM_JED_ERROR_EXTENSION_UNPUBLISHED_REASON');
                $msg[] = '<ul>';

                foreach ($code as $c) {
                    $msg[] = '<li>' . $c . ': ' . Text::_($c) . '</li>';
                }

                $msg[] = '</ul>';

                $msg[] = '<p>' . Text::_('COM_JED_VIEW_ERROR_CODES_LINK') . '</p>';
            } else {
                $msg[] = Text::_('COM_JED_ERROR_EXTENSION_UNPUBLISHED');
            }

            $msg[] = $row->message;
            $level = 'warning';
        } else {
            $level = 'info';
            $msg[] = Text::_('COM_JED_EXTENSION_XTENSIONS_FOUND_LABEL');
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
            $id = $app->input->get('id');
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

    /**
     * Get varied data for extension, i.e. fields for free, fields for paid
     *
     * @param int      $extension_id
     * @param int|null $supply_option_type
     *
     * @return array
     *
     * @throws Exception
     * @since  4.0.0
     */
    public function getVariedData(int $extension_id, int $supply_option_type = null): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
                    ->select('supply_options.title AS supply_type, a.*')
                    ->from($db->quoteName('#__jed_extension_varied_data', 'a'))
                    ->leftJoin(
                        $db->quoteName('#__jed_extension_supply_options', 'supply_options')
                        . ' ON ' . $db->quoteName('supply_options.id') . ' = ' . $db->quoteName('a.supply_option_id')
                    )
                    ->where($db->quoteName('extension_id') . ' = :extension_id')
                    ->bind(':extension_id', $extension_id, ParameterType::INTEGER);

        if (($supply_option_type ?? 0) > 0) {
            $query
                ->where($db->quoteName('supply_option_id') . ' = :supply_option_type')
                ->bind(':supply_option_type', $supply_option_type, ParameterType::INTEGER);
        }

        $result = $db->setQuery($query)->loadObjectList();

        foreach ($result as $variedDatum) {
            $supply = $variedDatum->supply_type;

            if (!empty($variedDatum->logo)) {
                $variedDatum->logo = \Jed\Component\Jed\Site\Helper\JedHelper::formatImage($variedDatum->logo, ImageSize::LARGE);
            }

            if ($variedDatum->is_default_data == 1 && empty($variedDatum->intro_text)) {
                $split_data = $this->splitDescription($variedDatum->description);

                if (!is_null($split_data)) {
                    $variedDatum->intro_text  = $split_data['intro'];
                    $variedDatum->description = $split_data['body'] . Markdown::defaultTransform($variedDatum->description);
                }
            } else {
                $variedDatum->intro_text  = Markdown::defaultTransform($variedDatum->intro_text);
                $variedDatum->description = Markdown::defaultTransform($variedDatum->description);
            }

            $retval[$supply] = $variedDatum;
        }

        return $retval;
    }
}
