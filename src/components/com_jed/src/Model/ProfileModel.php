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
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use stdClass;

/**
 * Methods supporting the developer/company profile view: the developer's own (currently
 * placeholder) metadata plus the list of extensions they own.
 *
 * @since 4.0.0
 */
class ProfileModel extends ListModel
{
    use ExtensionUtilities;

    /**
     * Constructor.
     *
     * @param array $config An optional associative array of configuration settings.
     *
     * @see    JController
     * @since  4.0.0
     * @throws Exception
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'name', 'a.name',
                'catid', 'a.catid',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to autopopulate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param string $ordering  Elements order
     * @param string $direction Order direction
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function populateState($ordering = 'a.name', $direction = 'ASC'): void
    {
        $app = Factory::getApplication();

        $this->setState('profile.owner_id', $app->getInput()->getInt('id', 0));

        parent::populateState($ordering, $direction);
    }

    /**
     * Build an SQL query for the extensions the profile's developer owns.
     *
     * Only active (published) extensions are ever shown here - this is a public profile page,
     * not a moderation view, so pending/unpublished/trashed listings stay hidden regardless of
     * who's looking at it.
     *
     * @return QueryInterface
     *
     * @since  4.0.0
     * @throws Exception
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select('a.*');
        $query->from('#__jed_extensions AS a');

        $query->select('cat.title AS category_title');
        $query->join('INNER', '#__categories AS cat ON cat.id = a.catid');

        // Flag whether the current visitor has bookmarked each extension, for the card's
        // favorite icon.
        $favUserId = (int) (Factory::getApplication()->getIdentity()->id ?? 0);
        $query->select('(fav.id IS NOT NULL) AS is_favorited');
        $query->join('LEFT', '#__jed_favorites AS fav ON fav.extension_id = a.id AND fav.user_id = ' . $db->quote($favUserId));

        $ownerId = (int) $this->getState('profile.owner_id');
        $query->where('a.owner = ' . $db->quote($ownerId));
        // A public profile shows the same listings to everyone, so only the public half of the
        // rule applies here - no owner/maintainer widening, deliberately (8.2: no moderation
        // view). Spelled out rather than delegated to getExtensionVisibilityCondition() for
        // exactly that reason; the four carriers are the same four (4.8).
        $query->where('a.approved = 1');
        $query->where('a.state = 1');
        $query->where('a.blocked = 0');
        $query->where('a.deleted = 0');

        $orderCol  = $this->state->get('list.ordering', 'a.name');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    /**
     * Method to get an array of data items
     *
     * @return mixed An array of data on success, false on failure.
     *
     * @since 4.0.0
     */
    public function getItems(): mixed
    {
        $items = parent::getItems();

        foreach ($items as $item) {
            if (!empty($item->logo)) {
                $item->logo = JedHelper::formatImage($item->logo, ImageSize::SMALL);
            }

            $item->number_of_reviews = (int) $item->score_count;
            $item->score_string      = JedscoreHelper::getStars((float) $item->score_overall);

            if ($item->number_of_reviews == 0) {
                $item->review_string = '';
            } elseif ($item->number_of_reviews == 1) {
                $item->review_string = '<span>' . $item->number_of_reviews . ' review</span>';
            } else {
                $item->review_string = '<span>' . $item->number_of_reviews . ' reviews</span>';
            }

            // The types of Joomla extensions this extension ships with (component/module/plugin)
            // and the Joomla versions it supports, e.g. "Component, Plugin" / "Joomla 5, Joomla 6".
            $item->includes_string = JedtrophyHelper::getTrophyIncludesStringFull((string) $item->extension_types);
            $item->version_string  = JedtrophyHelper::getTrophyVersionsStringFull((string) $item->joomla_versions);

            // The card text: the intro, or a truncated description while a listing has none.
            // string.truncate would cut the stored Markdown mid-token, so the text is rendered
            // and flattened first.
            $item->short_description = JedHelper::cardText($item->intro ?? null, $item->description ?? null);
        }

        return array_values($items);
    }

    /**
     * Loads the developer/company profile metadata for the user given in the request.
     *
     * Throws a 404 if the user doesn't exist or doesn't own at least one active (published)
     * extension - there is no profile page for a user who isn't a JED developer.
     *
     * The logo and website are not backed by real data yet (no such fields exist anywhere in the
     * schema) - they're placeholders until a real source for them is decided on.
     *
     * @return stdClass
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getDeveloper(): stdClass
    {
        $ownerId = (int) $this->getState('profile.owner_id');

        if (!$ownerId) {
            throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 404);
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'name']))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $ownerId, ParameterType::INTEGER);

        $user = $db->setQuery($query)->loadObject();

        if (!$user || !$this->hasActiveExtension($ownerId)) {
            throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 404);
        }

        $developerName = $this->getDeveloperName($ownerId);

        $developer          = new stdClass();
        $developer->id      = $ownerId;
        $developer->name    = $developerName !== '' ? $developerName : $user->name;
        // Placeholder profile picture/website - see the method docblock above.
        $developer->logo    = Uri::root() . 'media/com_jed/images/developer-placeholder.svg';
        $developer->website = 'https://example.com';

        return $developer;
    }

    /**
     * Whether the given user owns at least one active (published) extension.
     *
     * @param int $ownerId The #__jed_extensions.owner user id to check
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private function hasActiveExtension(int $ownerId): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('owner') . ' = :ownerId')
            ->where($db->quoteName('approved') . ' = 1')
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('blocked') . ' = 0')
            ->where($db->quoteName('deleted') . ' = 0')
            ->bind(':ownerId', $ownerId, ParameterType::INTEGER);

        return (bool) $db->setQuery($query)->loadResult();
    }
}
