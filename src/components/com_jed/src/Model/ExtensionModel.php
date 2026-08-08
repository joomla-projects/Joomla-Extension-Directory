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
use Jed\Component\Jed\Administrator\Listing\LinkedExtensions;
use Jed\Component\Jed\Administrator\Listing\ListingAccess;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
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
            if ($table && $table->load($pk)) {
                // The four-carrier state model (4.8, P1-01) decides what this URL answers with.
                // This deliberately does not consult backend permissions - previously
                // "filter.published" was only set for users without core.edit, so staff could
                // open any unpublished listing on the public site.
                $access = JedHelper::resolveListingAccess($table);

                if ($access === ListingAccess::GONE) {
                    throw new Exception(Text::_('COM_JED_EXTENSION_DELETED'), 410);
                }

                if ($access === ListingAccess::NOT_FOUND) {
                    throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 404);
                }

                // Convert the Table to a clean stdClass.
                $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

                // BLOCKED still renders, with the notice in place of the listing - the view
                // reads these two. The reason text is not among them: it is internal (8.7).
                $this->item->listing_access = $access;
                $this->item->block_reason   = JedHelper::getPublicBlockReason($this->item);
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
        $currentUserId              = (int) (Factory::getApplication()->getIdentity()->id ?? 0);
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

        $this->item->tags = (new TagsHelper())->getItemTags('com_jed.extension', $this->item->id);

        // The detail page's outbound buttons, built from the columns that actually carry them.
        // Until P1-07 the template read five properties - homepage_link, demo_link,
        // documentation_link, support_link, license_link - that no query ever produced, so every
        // one of those buttons was a dead anchor on the site's most-visited page.
        $this->item->links = $this->getOutboundLinks($this->item);

        $this->item->screenshots = $this->getScreenshots((int) $this->item->id);

        $this->item->more_by_developer = $this->getMoreByDeveloper(
            (int) $this->item->owner,
            (int) $this->item->id
        );

        $this->item->linked = $this->getLinkedExtensions((int) $this->item->id);

        return $this->item;
    }

    /**
     * The outbound links a listing offers, in display order, skipping the ones it has no URL for.
     *
     * Returned as a list rather than as individual properties so the template cannot render a
     * button for a column that is empty: a link with no URL is simply not in the array.
     *
     * @param object $item The loaded listing.
     *
     * @return array<int, array{key: string, url: string, label: string}>
     *
     * @since 4.0.0
     */
    private function getOutboundLinks(object $item): array
    {
        // `developer_url` is the developer's own site, which is what the "Website" button always
        // meant - the template's `homepage_link` was the JED3 column name, and the migration maps
        // JED3 `homepage_link` onto `developer_url`.
        $map = [
            'website'       => ['developer_url', 'COM_JED_EXTENSION_LINK_WEBSITE'],
            'demo'          => ['demo_url', 'COM_JED_EXTENSION_LINK_DEMO'],
            'documentation' => ['documentation_url', 'COM_JED_EXTENSION_LINK_DOCUMENTATION'],
            'support'       => ['support_url', 'COM_JED_EXTENSION_LINK_SUPPORT'],
            'changelog'     => ['changelog_url', 'COM_JED_EXTENSION_LINK_CHANGELOG'],
            'git'           => ['git_url', 'COM_JED_EXTENSION_LINK_GIT'],
            'license'       => ['license_url', 'COM_JED_EXTENSION_LINK_LICENSE'],
        ];

        $links = [];

        foreach ($map as $key => [$column, $label]) {
            $url = trim((string) ($item->$column ?? ''));

            if ($url === '') {
                continue;
            }

            $links[] = ['key' => $key, 'url' => $url, 'label' => $label];
        }

        return $links;
    }

    /**
     * The listing's screenshots, in the order the developer arranged them.
     *
     * `state` is honoured: an image the JED team has unpublished stays off the page. The table,
     * the admin views and the developer's own upload form have all existed since 4.0 - only the
     * rendering was missing.
     *
     * @param int $extensionId
     *
     * @return array<int, object>
     *
     * @since 4.0.0
     */
    public function getScreenshots(int $extensionId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'filename']))
            ->from($db->quoteName('#__jed_extensions_images'))
            ->where($db->quoteName('extension_id') . ' = :extension_id')
            ->where($db->quoteName('state') . ' = 1')
            ->bind(':extension_id', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC')
            ->order($db->quoteName('id') . ' ASC');

        $rows = $db->setQuery($query)->loadObjectList() ?: [];

        foreach ($rows as $row) {
            $row->thumbnail = JedHelper::formatImage((string) $row->filename, ImageSize::SMALL);
            $row->full      = JedHelper::formatImage((string) $row->filename, ImageSize::LARGE);
        }

        return $rows;
    }

    /**
     * Other listings by the same developer.
     *
     * Keyed on `owner`, which is the same rule the developer's public profile uses - so the two
     * pages cannot disagree about what "by this developer" means. Maintainership deliberately
     * does not widen it (P1-03): a maintainer helps look after a listing, they did not publish it.
     *
     * @param int $ownerId     The listing's owner.
     * @param int $excludeId   The listing being viewed.
     * @param int $limit       How many to show.
     *
     * @return array<int, object>
     *
     * @since 4.0.0
     */
    public function getMoreByDeveloper(int $ownerId, int $excludeId, int $limit = 4): array
    {
        if (!$ownerId) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            // Everything JedHelper::cardData() reads (P1-14). A short select here meant these
            // cards rendered the shared layout with the cost and the last-updated date blank,
            // while the same card on the browse page showed both.
            ->select($db->quoteName([
                'a.id', 'a.name', 'a.alias', 'a.catid', 'a.intro', 'a.description', 'a.logo',
                'a.extension_types', 'a.joomla_versions', 'a.score_overall', 'a.score_count',
                'a.type', 'a.modified', 'a.created',
            ]))
            ->select($db->quoteName('cat.title', 'category_title'))
            ->select($db->quoteName('u.name', 'developer'))
            ->from($db->quoteName('#__jed_extensions', 'a'))
            ->join('INNER', $db->quoteName('#__categories', 'cat'), 'cat.id = a.catid')
            ->join('LEFT', $db->quoteName('#__users', 'u'), 'u.id = a.created_by')
            ->where($db->quoteName('a.owner') . ' = :owner')
            ->where($db->quoteName('a.id') . ' <> :exclude')
            // The public half of the four-carrier rule (4.8), spelled out for the same reason the
            // profile page spells it out: this is not a moderation view.
            ->where($db->quoteName('a.approved') . ' = 1')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.blocked') . ' = 0')
            ->where($db->quoteName('a.deleted') . ' = 0')
            ->bind(':owner', $ownerId, ParameterType::INTEGER)
            ->bind(':exclude', $excludeId, ParameterType::INTEGER)
            ->order($db->quoteName('a.score_count') . ' DESC')
            ->order($db->quoteName('a.name') . ' ASC');

        $rows = $db->setQuery($query, 0, $limit)->loadObjectList() ?: [];

        // Only what JedHelper::cardData() cannot derive itself. Since P1-14 the card takes the
        // row and does the mapping, so a view can no longer omit a signal by not passing it.
        foreach ($rows as $row) {
            $row->logo_url = $row->logo ? JedHelper::formatImage((string) $row->logo, ImageSize::SMALL) : '';
        }

        return $rows;
    }

    /**
     * The linked extensions to render on a detail page (`P1-23`, `P1-07` item 12).
     *
     * Four slots, from two stored columns:
     *
     *  - **parent** - what this listing says it extends. Rendered from `parent_id` alone,
     *    without consulting `parent_confirmed`: describing your own add-on as an add-on *for*
     *    something is a statement about your own product, and it appears on your own page.
     *  - **children** - what extends this listing. This one *does* require
     *    `parent_confirmed = 1`, because it puts other people's listings on this listing's page,
     *    and unconfirmed that is a traffic lever rather than a fact (see LinkedExtensions).
     *  - **variants** - the free/paid counterpart, collected from **both** directions. The
     *    relation is symmetric and stored once, so a listing's counterpart is either the one it
     *    names or the one that names it, and asking only one way loses half the catalogue's
     *    pairs.
     *  - **childCount** - the true total behind a truncated children list. VirtueMart has 268;
     *    the page shows a slice and links to the rest rather than pretending the slice is all.
     *
     * Every branch is filtered through the one shared visibility rule, which is what keeps the
     * acceptance criterion "a variant that is blocked must not be advertised from its sibling's
     * page" true without restating it here. Owners and maintainers still see their own listings,
     * exactly as they do everywhere else on the site - that is the rule, not an exception to it.
     *
     * @param int $extensionId The listing being rendered.
     *
     * @return object{parent: ?object, variants: array, children: array, childCount: int}
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getLinkedExtensions(int $extensionId): object
    {
        $empty = (object) ['parent' => null, 'variants' => [], 'children' => [], 'childCount' => 0];

        if ($extensionId <= 0) {
            return $empty;
        }

        $db      = $this->getDatabase();
        $visible = JedHelper::getExtensionVisibilityCondition($db, 'a');

        // Everything JedHelper::cardData() reads (P1-14) - the same select getMoreByDeveloper()
        // uses, and for the same reason: these render through the shared card layout, and a short
        // select leaves the cost and the last-updated date blank on one page while the identical
        // card on the browse page shows both.
        $select = function () use ($db) {
            return $db->getQuery(true)
                ->select($db->quoteName([
                    'a.id', 'a.name', 'a.alias', 'a.catid', 'a.intro', 'a.description', 'a.logo',
                    'a.extension_types', 'a.joomla_versions', 'a.score_overall', 'a.score_count',
                    'a.type', 'a.modified', 'a.created',
                ]))
                ->select($db->quoteName('cat.title', 'category_title'))
                ->select($db->quoteName('u.name', 'developer'))
                ->from($db->quoteName('#__jed_extensions', 'a'))
                ->join('INNER', $db->quoteName('#__categories', 'cat'), 'cat.id = a.catid')
                ->join('LEFT', $db->quoteName('#__users', 'u'), 'u.id = a.created_by');
        };

        // The parent: a single hop through this listing's own parent_id.
        $parent = $db->setQuery(
            $select()
                ->where($db->quoteName('a.id') . ' = (SELECT ' . $db->quoteName('parent_id')
                    . ' FROM ' . $db->quoteName('#__jed_extensions')
                    . ' WHERE ' . $db->quoteName('id') . ' = :self)')
                ->where($visible)
                ->bind(':self', $extensionId, ParameterType::INTEGER),
            0,
            1
        )->loadObject();

        // Both directions of the variant relation at once. A listing cannot be its own variant -
        // LinkedExtensions rejects that on the way in and the import dropped the 18 JED3 rows
        // that had it - but the guard costs nothing and a self-link would otherwise render the
        // listing as its own alternative.
        $variants = $db->setQuery(
            $select()
                ->where('(' . $db->quoteName('a.variant_of_id') . ' = :self'
                    . ' OR ' . $db->quoteName('a.id') . ' = (SELECT ' . $db->quoteName('variant_of_id')
                    . ' FROM ' . $db->quoteName('#__jed_extensions')
                    . ' WHERE ' . $db->quoteName('id') . ' = :selfb))')
                ->where($db->quoteName('a.id') . ' != :selfc')
                ->where($visible)
                ->bind(':self', $extensionId, ParameterType::INTEGER)
                ->bind(':selfb', $extensionId, ParameterType::INTEGER)
                ->bind(':selfc', $extensionId, ParameterType::INTEGER)
                ->order($db->quoteName('a.name') . ' ASC')
        )->loadObjectList() ?: [];

        // Confirmed add-ons only, best first - with 268 of them on one listing, which slice the
        // page shows is a real editorial choice and "whatever the database returns" is the wrong
        // one.
        $childQuery = $select()
            ->where($db->quoteName('a.parent_id') . ' = :self')
            ->where($db->quoteName('a.parent_confirmed') . ' = 1')
            ->where($db->quoteName('a.id') . ' != :selfb')
            ->where($visible)
            ->bind(':self', $extensionId, ParameterType::INTEGER)
            ->bind(':selfb', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('a.score_overall') . ' DESC')
            ->order($db->quoteName('a.score_count') . ' DESC')
            ->order($db->quoteName('a.name') . ' ASC');

        $children = $db->setQuery($childQuery, 0, LinkedExtensions::INLINE_LIMIT)->loadObjectList() ?: [];

        // Only pay for the total when the list was actually cut short.
        $childCount = \count($children);

        if ($childCount === LinkedExtensions::INLINE_LIMIT) {
            $childCount = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__jed_extensions', 'a'))
                    ->where($db->quoteName('a.parent_id') . ' = :self')
                    ->where($db->quoteName('a.parent_confirmed') . ' = 1')
                    ->where($db->quoteName('a.id') . ' != :selfb')
                    ->where($visible)
                    ->bind(':self', $extensionId, ParameterType::INTEGER)
                    ->bind(':selfb', $extensionId, ParameterType::INTEGER)
            )->loadResult();
        }

        // Only what cardData() cannot derive itself, exactly as getMoreByDeveloper() does it.
        foreach (array_merge($parent ? [$parent] : [], $variants, $children) as $row) {
            $row->logo_url = $row->logo ? JedHelper::formatImage((string) $row->logo, ImageSize::SMALL) : '';
        }

        return (object) [
            'parent'     => $parent,
            'variants'   => $variants,
            'children'   => $children,
            'childCount' => $childCount,
        ];
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
            ->from($db->quoteName('#__jed_reviews', 'a'))
            ->where($db->quoteName('a.extension_id') . ' = :extension_id')
            // Moderated reviews, plus the current user's own so their pending review does not
            // appear to have been lost. Same rule as the reviews list.
            ->where(JedHelper::getReviewVisibilityCondition($db))
            ->bind(':extension_id', $extension_id, ParameterType::INTEGER)
            ->order($db->quoteName('a.created_on') . ' DESC');

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
     * @since 4.0.0
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
            ->where($db->quoteName('state') . ' != -2')
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
     * Take the developer's own listing online or offline.
     *
     * Updates `state` on the live row, and touches nothing else. In particular it does not read
     * or write `blocked`: a blocked listing may still be taken offline and back online by its
     * developer, and doing so leaves the block exactly where it was. That is the point of the two
     * columns being separate (4.8) - if this wrote a single visibility flag, republishing would
     * silently lift the JED team's block.
     *
     * A soft-deleted listing is excluded: the frontend is done with it.
     *
     * @param int $extensionId The extension id.
     * @param int $online      1 for online, 0 for offline.
     *
     * @return void
     *
     * @throws Exception If the listing does not exist or is soft-deleted.
     *
     * @since 4.0.0
     */
    public function setOnlineState(int $extensionId, int $online): void
    {
        $db     = $this->getDatabase();
        $online = $online === 1 ? 1 : 0;

        // Checked separately rather than by counting affected rows: MySQL reports zero rows
        // changed when the new value equals the old one, so "no rows affected" does not mean
        // "no such listing".
        $exists = $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :id')
                ->where($db->quoteName('deleted') . ' = 0')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->loadResult();

        if (!$exists) {
            throw new Exception(Text::_('COM_JED_ITEM_NOT_LOADED'), 404);
        }

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__jed_extensions'))
                ->set($db->quoteName('state') . ' = :state')
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':state', $online, ParameterType::INTEGER)
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Answer a maintainer invitation.
     *
     * Accepting is what turns a named person into a maintainer - until then the row exists but
     * grants nothing, which is the whole point of the state column (8.8, `P1-03` item 4).
     * Declining keeps the row rather than deleting it, so an owner can see the invitation was
     * refused instead of wondering whether it ever went out; re-inviting is a fresh save.
     *
     * The row is matched on the extension **and** the user, so possessing an extension id is
     * never enough to answer somebody else's invitation.
     *
     * @param int  $extensionId The extension id.
     * @param int  $userId      The invited user.
     * @param bool $accept      True to accept, false to decline.
     *
     * @return bool  False when there is no open invitation for this user.
     *
     * @since 4.0.0
     */
    public function respondToMaintainerInvitation(int $extensionId, int $userId, bool $accept): bool
    {
        if ($extensionId <= 0 || $userId <= 0) {
            return false;
        }

        $db      = $this->getDatabase();
        $invited = JedHelper::MAINTAINER_INVITED;

        $exists = (bool) $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_extensions_maintainers'))
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->where($db->quoteName('user_id') . ' = :uid')
                ->where($db->quoteName('state') . ' = :state')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':state', $invited, ParameterType::INTEGER)
        )->loadResult();

        if (!$exists) {
            return false;
        }

        $newState = $accept ? JedHelper::MAINTAINER_ACCEPTED : JedHelper::MAINTAINER_DECLINED;
        $now      = Factory::getDate()->toSql();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__jed_extensions_maintainers'))
            ->set($db->quoteName('state') . ' = :state')
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->where($db->quoteName('user_id') . ' = :uid')
            ->bind(':state', $newState, ParameterType::INTEGER)
            ->bind(':eid', $extensionId, ParameterType::INTEGER)
            ->bind(':uid', $userId, ParameterType::INTEGER);

        if ($accept) {
            $query->set($db->quoteName('accepted_time') . ' = :now')->bind(':now', $now, ParameterType::STRING);
        }

        $db->setQuery($query)->execute();

        return true;
    }

    /**
     * The open maintainer invitations for a user.
     *
     * Feeds the dashboard, which is where an invited person finds out they were named at all.
     *
     * @param int $userId The user.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getMaintainerInvitations(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $db      = $this->getDatabase();
        $invited = JedHelper::MAINTAINER_INVITED;

        return (array) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['m.extension_id', 'm.invited_time', 'm.invited_by']))
                ->select($db->quoteName('e.name', 'extension_name'))
                ->from($db->quoteName('#__jed_extensions_maintainers', 'm'))
                ->innerJoin($db->quoteName('#__jed_extensions', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('m.extension_id'))
                ->where($db->quoteName('m.user_id') . ' = :uid')
                ->where($db->quoteName('m.state') . ' = :state')
                ->where($db->quoteName('e.deleted') . ' = 0')
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':state', $invited, ParameterType::INTEGER)
        )->loadObjectList();
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
