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

        // $this->item already carries the live score_overall/score_functionality/.../score_count
        // columns straight off #__jed_extensions - kept up to date by ScoreCalculationService,
        // no separate query needed here.
        if ($this->item->score_count == 0) {
            $this->item->review_string = '';
        } elseif ($this->item->score_count == 1) {
            $this->item->review_string = '<span>' . $this->item->score_count . ' review</span>';
        } else {
            $this->item->review_string = '<span>' . $this->item->score_count . ' reviews</span>';
        }

        // Load Reviews
        $this->item->reviews = $this->getReviews($this->item->id);

        // Does the current visitor already have a review for this extension? Used to decide
        // whether the "Write a review" link should route to a blank form or their existing one.
        $currentUserId             = (int) (Factory::getApplication()->getIdentity()->id ?? 0);
        $this->item->user_review_id = $this->getUserReviewId($this->item->id, $currentUserId);

        // Has the current visitor bookmarked this extension? Drives the bookmark icon's initial
        // (server-rendered) state, before any AJAX toggle happens.
        $this->item->is_favorited = $currentUserId ? $this->isFavorited($this->item->id, $currentUserId) : false;

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
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_reviews'))
            ->where($db->quoteName('extension_id') . ' = :extension_id')
            ->where($db->quoteName('published') . ' = 1')
            ->bind(':extension_id', $extension_id, ParameterType::INTEGER)
            ->order($db->quoteName('created_on') . ' DESC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Look up whether the given logged-in user already has a review for this extension.
     *
     * @param int $extension_id
     * @param int $user_id
     *
     * @return int|null The user's existing review id, or null if they don't have one.
     *
     * @since 4.1.0
     */
    public function getUserReviewId(int $extension_id, int $user_id): ?int
    {
        if (!$user_id) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jed_reviews'))
            ->where($db->quoteName('extension_id') . ' = :extension_id')
            ->where($db->quoteName('created_by') . ' = :user_id')
            ->where($db->quoteName('published') . ' != -2')
            ->bind(':extension_id', $extension_id, ParameterType::INTEGER)
            ->bind(':user_id', $user_id, ParameterType::INTEGER);

        $id = $db->setQuery($query)->loadResult();

        return $id !== null ? (int) $id : null;
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
    public function getTable($name = "Extension", $prefix = "Administrator", $options = [])
    {
        return parent::getTable($name, $prefix, $options);
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
}
