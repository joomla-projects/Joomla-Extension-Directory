<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use DateTime;
use Exception;
use Jed\Component\Jed\Administrator\Helper\JedHelper as AdminJedHelper;
use Jed\Component\Jed\Administrator\Listing\ListingAccess;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User;
use Joomla\Component\Mails\Administrator\Model\TemplateModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

use function defined;

/**
 * JED Helper
 *
 * @package JED
 * @since   4.0.0
 */
class JedHelper
{
    /**
     * Gets the current User .
     *
     * @return User\User
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function getUser(): User\User
    {
        return Factory::getApplication()->getIdentity();
    }

    /**
     * Gets a user by ID number.
     *
     * @param $userId
     *
     * @return User\User
     *
     * @since 4.0.0
     */
    public static function getUserById($userId): User\User
    {
        //$user   = Factory::getUser();

        try {
            $container   = Factory::getContainer();
            $userFactory = $container->get('user.factory');

            return $userFactory->loadUserById($userId);
        } catch (Exception) {
            return new User\User();
        }
    }


    /**
     * Sends a Joomla mail template (#__mail_templates) to the given recipient and returns the
     * rendered template row, so the caller can keep a copy of what was sent (e.g. as an
     * outgoing ticket message).
     *
     * @param string    $templateId The #__mail_templates.template_id to send
     * @param User\User $recipient  The user to send the mail to
     *
     * @return object|null The mail template row (subject/body/htmlbody/...), or null if the
     *                      template does not exist
     *
     * @since 4.0.0
     */
    public static function sendMailTemplate(string $templateId, User\User $recipient): ?object
    {
        $app      = Factory::getApplication();
        $language = $app->getLanguage()->getTag();
        /** @var TemplateModel $model */
        $model    = $app->bootComponent('com_mails')->getMVCFactory()->createModel('Template', 'Administrator');
        $model->setState($model->getName() . '.template_id', $templateId);
        $model->setState($model->getName() . '.language', $language);
        $mail     = $model->getItem();

        if ($mail === null) {
            return null;
        }

        $mailtemplate = new MailTemplate($templateId, $language);
        $mailtemplate->addRecipient($recipient->email, $recipient->name);
        $mailtemplate->send();

        return $mail;
    }

    /**
     * isLoggedIn
     *
     * Returns if user is logged-in
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function isLoggedIn(): bool
    {
        try {
            $user = Factory::getApplication()->getIdentity();
        } catch (Exception) {
            return false;
        }

        if ($user->id > 0) {
            return true;
        }
        return false;
    }

    /**
     * Gets the edit permission for a user
     *
     * @param mixed $item The item
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function canUserEdit(mixed $item): bool
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.edit', 'com_jed')) {
            return true;
        }

        // An extension is owned, not authored. Refusing outright rather than falling through to
        // the created_by test below is the point of 8.8.1: created_by does not follow an
        // ownership transfer, so answering that question here would hand the previous owner
        // permanent edit rights. Extensions go through isOwnerOrMaintainer().
        if (\is_object($item) && property_exists($item, 'owner')) {
            return false;
        }

        // A record that does not exist yet is being created, and creating is not editing.
        if (!\is_object($item) || empty($item->id)) {
            return true;
        }

        // An existing record with no authorship column cannot be attributed to anyone, so it is
        // not the current user's. This used to return true - a fail-open on exactly the input
        // the caller could not vouch for.
        if (!isset($item->created_by)) {
            return false;
        }

        return (int) $item->created_by === (int) $user->id;
    }

    /**
     * Resolve a stored image reference to a full, browsable URL.
     *
     * Delegates to the Administrator helper so there is one resolution rule, not two that can
     * drift apart. See that method for the three shapes a stored reference can have.
     *
     * @param string    $filename The stored reference.
     * @param ImageSize $size     The variant wanted.
     *
     * @return string  Full image URL, or an empty string when nothing is stored.
     *
     * @since 4.0.0
     */
    public static function formatImage(string $filename, ImageSize $size = ImageSize::SMALL): string
    {
        return AdminJedHelper::formatImage($filename, $size);
    }

    /**
     * Returns URL for user login
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function getLoginlink(): string
    {
        $redirectUrl    = '&return=' . urlencode(base64_encode(Uri::getInstance()->toString()));
        $joomlaLoginUrl = 'index.php?option=com_users&view=login';

        return $joomlaLoginUrl . $redirectUrl;
    }

    /**
     * Checks whether or not a user is manager or superuser
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function isAdminOrSuperUser(): bool
    {
        try {
            $user = Factory::getApplication()->getIdentity();

            return in_array("8", $user->groups) || in_array("7", $user->groups);
        } catch (Exception $exc) {
            throw new Exception($exc->getMessage(), $exc->getCode());
        }
    }

    /**
     * The state of an accepted maintainer row. Anything else grants nothing.
     *
     * @since 4.0.0
     */
    public const MAINTAINER_INVITED  = 0;
    public const MAINTAINER_ACCEPTED = 1;
    public const MAINTAINER_DECLINED = -1;

    /**
     * Whether the current user owns the given extension.
     *
     * The owner-only half of the 8.8 matrix: soft delete and ownership transfer are the owner's
     * and a maintainer must not reach them. Kept separate from isOwnerOrMaintainer() rather than
     * expressed as a flag, so a call site cannot pick the laxer rule by leaving an argument out.
     *
     * Reads `owner`, never `created_by`. `created_by` is the authorship record and does not
     * follow a transfer - a check keyed on it would leave the previous owner able to delete an
     * extension they no longer own (8.8.1).
     *
     * @param int $extensionId The extension PK in #__jed_extensions.
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function isOwner(int $extensionId): bool
    {
        $userId = (int) self::getUser()->id;

        if (!$userId || $extensionId <= 0) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $owner = (int) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('owner'))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->loadResult();

        return $owner === $userId;
    }

    /**
     * Whether the current user owns or maintains the given extension.
     *
     * The rule for everything both roles may do: edit, publish, change images, answer reviews.
     * Owner OR an **accepted** maintainer row - an invitation that has not been accepted grants
     * nothing, which is the point of having a state on that table at all (P1-03 item 4).
     *
     * Never `created_by`. See isOwner() for why.
     *
     * @param int $extensionId The extension PK in #__jed_extensions.
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function isOwnerOrMaintainer(int $extensionId): bool
    {
        if (self::isOwner($extensionId)) {
            return true;
        }

        $userId = (int) self::getUser()->id;

        if (!$userId || $extensionId <= 0) {
            return false;
        }

        $db       = Factory::getContainer()->get('DatabaseDriver');
        $accepted = self::MAINTAINER_ACCEPTED;

        return (bool) $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_extensions_maintainers'))
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->where($db->quoteName('user_id') . ' = :uid')
                ->where($db->quoteName('state') . ' = :state')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':state', $accepted, ParameterType::INTEGER)
        )->loadResult();
    }

    /**
     * A SQL condition selecting the extensions the current user owns or maintains.
     *
     * The list counterpart of isOwnerOrMaintainer(), for the places that need the *set* rather
     * than a yes/no - the dashboard, "my extensions" pickers. Those used to filter on
     * `created_by = me`, which is wrong twice over: it misses everything the user maintains, and
     * it keeps showing extensions they have transferred away.
     *
     * @param DatabaseInterface $db    The database driver, for quoting.
     * @param string            $alias The table alias used for #__jed_extensions in the query.
     *
     * @return string  A parenthesised SQL condition, or a never-true one when logged out.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function getOwnedOrMaintainedCondition(DatabaseInterface $db, string $alias = 'a'): string
    {
        $userId = (int) self::getUser()->id;

        if ($userId <= 0) {
            return '0';
        }

        $maintained = 'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jed_extensions_maintainers', 'jm')
            . ' WHERE ' . $db->quoteName('jm.extension_id') . ' = ' . $db->quoteName($alias . '.id')
            . ' AND ' . $db->quoteName('jm.user_id') . ' = ' . $userId
            . ' AND ' . $db->quoteName('jm.state') . ' = ' . self::MAINTAINER_ACCEPTED . ')';

        return '(' . $db->quoteName($alias . '.owner') . ' = ' . $userId . ' OR ' . $maintained . ')';
    }

    /**
     * The single visibility rule for extension listings in the frontend.
     *
     * Four independent carriers, all four required (4.8, `P1-01`):
     *
     *     visible ⟺ approved = 1 AND state = 1 AND blocked = 0 AND deleted = 0
     *
     * `approved` and `blocked` belong to the JED team, `state` to the developer, `deleted` to
     * the owner or the team. They are separate columns precisely so that they cannot cancel each
     * other out - mapped onto one column, a developer could republish and thereby lift a block.
     *
     * On top of that, owners and maintainers see their own listings whatever their approval or
     * block status, so they can review a submission before it goes live and can find a blocked
     * listing in order to fix it. Nobody else does, whatever their permissions: backend rights
     * deliberately do not widen this, because `core.edit` used to leak every unpublished listing
     * into the public site. Soft-deleted rows are excluded even from the owner - the frontend is
     * done with them; the backend still shows them read-only.
     *
     * Returned as a SQL fragment rather than applied directly, so the callers can add it to
     * their own query. Defining it in one place is the point - a rule this easy to get subtly
     * wrong must not be copied into six models. Drift here means a blocked extension staying
     * visible in one listing.
     *
     * @param DatabaseInterface $db    The database driver, for quoting.
     * @param string            $alias The table alias used for #__jed_extensions in the query.
     *
     * @return string  A parenthesised SQL condition.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function getExtensionVisibilityCondition(DatabaseInterface $db, string $alias = 'a'): string
    {
        $notDeleted = $db->quoteName($alias . '.deleted') . ' = 0';

        $public = '(' . $db->quoteName($alias . '.approved') . ' = 1'
            . ' AND ' . $db->quoteName($alias . '.state') . ' = 1'
            . ' AND ' . $db->quoteName($alias . '.blocked') . ' = 0)';

        $userId = (int) self::getUser()->id;

        if ($userId <= 0) {
            return '(' . $notDeleted . ' AND ' . $public . ')';
        }

        // Owner OR an accepted maintainer row - never created_by, which does not follow an
        // ownership transfer and would leave the previous owner able to see the listing. An
        // invitation that has not been accepted is not a maintainer yet and shows nothing.
        return '(' . $notDeleted . ' AND (' . $public . ' OR '
            . self::getOwnedOrMaintainedCondition($db, $alias) . '))';
    }

    /**
     * Render a stored description or intro as HTML.
     *
     * Delegates to the Administrator helper so there is one converter configuration, not two
     * that can drift apart. See that method for why raw HTML is stripped and why single
     * newlines are kept as line breaks.
     *
     * @param string|null $text The stored Markdown.
     *
     * @return string  Rendered HTML, safe to output.
     *
     * @since 4.0.0
     */
    public static function renderMarkdown(?string $text): string
    {
        return AdminJedHelper::renderMarkdown($text);
    }

    /**
     * Reduce a stored description or intro to plain text, optionally truncated.
     *
     * @param string|null $text   The stored Markdown.
     * @param int         $length Maximum length, 0 for no limit.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function markdownToText(?string $text, int $length = 0): string
    {
        return AdminJedHelper::markdownToText($text, $length);
    }

    /**
     * The supporting text of an extension card.
     *
     * `intro` is what the card shows, and the import fills it for every legacy listing - but a
     * listing created through the submission form can leave it empty, and a blank card is worse
     * than a truncated one, so the description stands in. Either way the card gets plain text:
     * the slot is two clamped lines, and rendered Markdown would put block markup into it.
     *
     * @param string|null $intro       The listing's intro.
     * @param string|null $description The listing's description, used when the intro is empty.
     *
     * @return string  Plain text with HTML entities encoded, safe to output.
     *
     * @since 4.0.0
     */
    public static function cardText(?string $intro, ?string $description = null): string
    {
        return self::markdownToText(trim((string) $intro) !== '' ? $intro : $description, 200);
    }

    /**
     * Everything one extension card needs, from one listing row.
     *
     * `P1-14` asks for five decision signals on every card and for **one** card layout to carry
     * them. One layout is only half of that: before this, four views each assembled their own
     * argument list from whatever their model happened to have prepared, so the profile page's
     * cards showed the Joomla versions and the browse list's showed a rating, and neither showed
     * both. The layout was shared; the data was not.
     *
     * So the mapping lives here, once. A caller hands over a row and gets a card - which also
     * means a view can no longer quietly omit a signal by not passing it.
     *
     * The study behind this (13.2) found that decision-relevant details surface too late: 65% of
     * respondents had evaluated an extension and only then discovered a limitation - version,
     * missing feature, or cost. All five signals below are already in the row.
     *
     * @param object      $item The listing row, as any of the list models prepare it.
     * @param string|null $link The routed link to the listing; built from the row when omitted.
     *
     * @return array<string, mixed>  Display data for the `cards.extension` layout.
     *
     * @since 4.0.0
     */
    public static function cardData(object $item, ?string $link = null): array
    {
        $versions = (string) ($item->joomla_versions ?? '');
        $includes = (string) ($item->extension_types ?? '');
        $count    = (int) ($item->score_count ?? 0);

        return [
            'id'    => (int) ($item->id ?? 0),
            'link'  => $link ?? Route::_(
                'index.php?option=com_jed&view=extension&catid=' . (int) ($item->catid ?? 0)
                . '&id=' . (int) ($item->id ?? 0)
            ),
            'title' => (string) ($item->name ?? ''),
            // Already run through formatImage() by the models that have one; a raw filename is
            // resolved here so a caller cannot end up with a broken <img>.
            'image' => self::cardImage($item),
            'developer'   => (string) ($item->developer ?? $item->created_by_name ?? ''),
            'description' => self::cardText($item->intro ?? null, $item->description ?? null),

            // 1. Rating, with the count beside it. 4.6 from two reviews is not 4.6 from two
            //    hundred, and a star row on its own does not say which it is.
            'score'        => (float) ($item->score_overall ?? 0),
            'reviewCount'  => $count,

            // 2. Compatibility - the study's single most important decision factor.
            'compatibility' => $versions,

            // 3. What the package contains.
            'includes' => $includes,

            // 4. Cost, named as a late-discovered limitation in the study.
            'type' => (string) ($item->type ?? ''),

            // 5. When it was last touched. A listing that has never been edited since it was
            //    submitted has no `modified`, and "no date" tells a visitor nothing - whereas
            //    "added four years ago" answers the same question they were asking, which is
            //    whether anybody is still looking after this.
            'modified' => (string) ($item->modified ?? ''),
            'created'  => (string) ($item->created ?? ''),

            'category'    => (string) ($item->category_title ?? ''),
            // Never true from the server: the browse pages must stay one document for every
            // visitor so they can be cached (P1-13). favoritestate.js fills the icons in.
            'isFavorited' => false,
        ];
    }

    /**
     * The card image for a row, resolved whether or not the model already did it.
     *
     * @param object $item The listing row.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private static function cardImage(object $item): string
    {
        $logo = (string) ($item->logo_url ?? $item->logo ?? '');

        if ($logo === '' || str_starts_with($logo, 'http') || str_starts_with($logo, '/')) {
            return $logo;
        }

        return self::formatImage($logo, ImageSize::SMALL);
    }

    /**
     * The Joomla versions a listing declares, as label/short pairs.
     *
     * A list rather than a blob of markup, so the card decides how to render them and can put a
     * visible number next to each icon. `JedtrophyHelper::getTrophyVersionsString()` returns
     * finished HTML with the label only in a `title` attribute, and on an empty
     * `joomla_versions` it emits one empty badge - a small grey rectangle meaning nothing, on
     * every listing that has not declared a version.
     *
     * @param string $versions The raw `joomla_versions` column.
     *
     * @return array<int, array{key: string, short: string, label: string}>
     *
     * @since 4.0.0
     */
    public static function versionBadges(string $versions): array
    {
        // Stored as a JSON-ish array of #__jed_joomla_versions ids: ["40","50"].
        $short = [
            '15' => '1.5', '25' => '2.5', '30' => '3', '40' => '4', '41' => '4.1',
            '50' => '5', '51' => '5 (b/c)', '60' => '6', '61' => '6 (b/c)',
        ];

        $badges = [];

        foreach (self::splitStoredList($versions) as $id) {
            if (!isset($short[$id])) {
                continue;
            }

            $label = Text::_('COM_JED_VERSION_' . $id);

            $badges[] = [
                'key'   => $id,
                'short' => htmlspecialchars($short[$id], ENT_QUOTES, 'UTF-8'),
                'label' => htmlspecialchars($label === 'COM_JED_VERSION_' . $id ? 'Joomla ' . $short[$id] : $label, ENT_QUOTES, 'UTF-8'),
            ];
        }

        return $badges;
    }

    /**
     * What a package contains, as key/label pairs.
     *
     * The label is the whole word, not the initial. `JedtrophyHelper::getTrophyIncludesString()`
     * renders `C`, `M`, `P` in coloured badges with the meaning in a `title` attribute - which
     * is a tooltip on a device that has a pointer and nothing at all on one that does not, and
     * fails 13.8 either way.
     *
     * @param string $types The raw `extension_types` column.
     *
     * @return array<int, array{key: string, label: string}>
     *
     * @since 4.0.0
     */
    public static function includeBadges(string $types): array
    {
        $known = ['com' => 'COMPONENT', 'mod' => 'MODULE', 'plugin' => 'PLUGIN', 'lang' => 'LANGUAGE', 'tpl' => 'TEMPLATE', 'lib' => 'LIBRARY', 'pkg' => 'PACKAGE'];

        $badges = [];

        foreach (self::splitStoredList($types) as $type) {
            if (!isset($known[$type])) {
                continue;
            }

            $badges[] = [
                'key'   => htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
                'label' => htmlspecialchars(Text::_('COM_JED_CARD_INCLUDES_' . $known[$type]), ENT_QUOTES, 'UTF-8'),
            ];
        }

        return $badges;
    }

    /**
     * Split one of the JSON-ish list columns into its values.
     *
     * `joomla_versions` and `extension_types` hold `["40","50"]` - valid JSON in the imported
     * stock, but not reliably so across the legacy data, which is why this strips the
     * punctuation rather than calling json_decode() and getting null.
     *
     * @param string $stored The raw column value.
     *
     * @return string[]
     *
     * @since 4.0.0
     */
    private static function splitStoredList(string $stored): array
    {
        $clean = str_replace(['[', ']', '"', "'", ' '], '', trim($stored));

        if ($clean === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $clean)), static fn ($v) => $v !== ''));
    }

    /**
     * "3 months ago", with the exact date for anyone who wants it.
     *
     * Relative because that is the question a visitor is actually asking - not *when* was this
     * updated but *how long ago*, which is the difference between a maintained extension and an
     * abandoned one. The absolute date goes in the `title` and in `<time datetime>` so the
     * precise answer is still one hover or one screen reader away.
     *
     * @param string|null $date A SQL datetime.
     *
     * @return array{relative: string, absolute: string, iso: string}|null  Null when there is no date.
     *
     * @since 4.0.0
     */
    public static function relativeDate(?string $date): ?array
    {
        $value = trim((string) $date);

        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            $then = Factory::getDate($value);
            $now  = Factory::getDate();
        } catch (Exception $e) {
            return null;
        }

        $days = (int) floor(($now->toUnix() - $then->toUnix()) / 86400);

        if ($days < 0) {
            $days = 0;
        }

        $relative = match (true) {
            $days === 0  => Text::_('COM_JED_CARD_UPDATED_TODAY'),
            $days === 1  => Text::_('COM_JED_CARD_UPDATED_YESTERDAY'),
            $days < 31   => Text::plural('COM_JED_CARD_UPDATED_DAYS', $days),
            $days < 365  => Text::plural('COM_JED_CARD_UPDATED_MONTHS', (int) round($days / 30.4)),
            default      => Text::plural('COM_JED_CARD_UPDATED_YEARS', (int) floor($days / 365)),
        };

        return [
            'relative' => $relative,
            'absolute' => HTMLHelper::_('date', $value, Text::_('DATE_FORMAT_LC3')),
            'iso'      => $then->format('Y-m-d'),
        ];
    }

    /**
     * The single visibility rule for reviews in the frontend.
     *
     * A review is public once it has been through moderation (state = 1). On top of that its
     * author always sees their own, so a freshly submitted review does not simply vanish while
     * it waits for approval. Backend permissions do not widen this.
     *
     * Note this keys on `created_by`, unlike the extension rule. That is correct here and not a
     * breach of the owner/maintainer invariant: a review has no owner column, and authorship of
     * the text is exactly what ownership means for a review.
     *
     * @param DatabaseInterface $db    The database driver, for quoting.
     * @param string            $alias The table alias used for #__jed_reviews in the query.
     *
     * @return string  A parenthesised SQL condition.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function getReviewVisibilityCondition(DatabaseInterface $db, string $alias = 'a'): string
    {
        // A review never outlives the visibility of what it reviews. Without this the reviews
        // list exposed the title and body of reviews for listings that are not public, each
        // linking to an extension page that answers 403.
        $extension = 'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jed_extensions', 've')
            . ' WHERE ' . $db->quoteName('ve.id') . ' = ' . $db->quoteName($alias . '.extension_id')
            . ' AND ' . self::getExtensionVisibilityCondition($db, 've') . ')';

        $public = $db->quoteName($alias . '.state') . ' = 1';
        $userId = (int) self::getUser()->id;

        if ($userId > 0) {
            $public = '(' . $public . ' OR ' . $db->quoteName($alias . '.created_by') . ' = ' . $userId . ')';
        }

        return '(' . $extension . ' AND ' . $public . ')';
    }

    /**
     * Whether the current user may see a single review row in the frontend.
     *
     * The row counterpart of getReviewVisibilityCondition().
     *
     * @param object $item The loaded review row.
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function canViewReview(object $item): bool
    {
        $userId = (int) self::getUser()->id;
        $isMine = $userId > 0 && (int) ($item->created_by ?? 0) === $userId;

        if ((int) ($item->state ?? 0) !== 1 && !$isMine) {
            return false;
        }

        // The review is only as visible as the listing it belongs to.
        $extensionId = (int) ($item->extension_id ?? 0);

        if ($extensionId <= 0) {
            return false;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'state', 'approved']))
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = :eid')
            ->bind(':eid', $extensionId, ParameterType::INTEGER);

        $extension = $db->setQuery($query)->loadObject();

        return $extension !== null && self::canViewExtension($extension);
    }

    /**
     * Whether the current user may see a single extension row in the frontend.
     *
     * The row counterpart of getExtensionVisibilityCondition(), for places that only need a
     * yes/no - a listing card, a link. The detail page needs more than yes/no, because a blocked
     * listing is answered with a notice rather than hidden; that path uses
     * {@see resolveListingAccess()} instead.
     *
     * @param object $item The loaded extension row.
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function canViewExtension(object $item): bool
    {
        if ((int) ($item->deleted ?? 0) === 1) {
            return false;
        }

        if (
            (int) ($item->approved ?? 0) === 1
            && (int) ($item->state ?? 0) === 1
            && (int) ($item->blocked ?? 0) === 0
        ) {
            return true;
        }

        return isset($item->id) && self::isOwnerOrMaintainer((int) $item->id);
    }

    /**
     * What the public detail page should do with this listing.
     *
     * Wraps {@see ListingAccess::forItem()} with the owner/maintainer lookup, so callers do not
     * have to know that "privileged" here means owner or maintainer and never `core.edit`.
     *
     * @param object $item The loaded extension row.
     *
     * @return ListingAccess
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function resolveListingAccess(object $item): ListingAccess
    {
        $isPrivileged = isset($item->id) && self::isOwnerOrMaintainer((int) $item->id);

        return ListingAccess::forItem($item, $isPrivileged);
    }

    /**
     * The public wording for a block, or null when the listing is not blocked.
     *
     * Only the reason code's title and its knowledge base article are public. `block_reason_text`
     * is the JED team's internal note and is deliberately not returned here - it is written on
     * the block form, kept in the revision history, and never rendered on the public site.
     *
     * @param object $item The loaded extension row.
     *
     * @return array{code: string, title: string, article_id: int|null}|null
     *
     * @since  4.0.0
     */
    public static function getPublicBlockReason(object $item): ?array
    {
        if ((int) ($item->blocked ?? 0) !== 1) {
            return null;
        }

        $code = (string) ($item->block_reason_code ?? '');

        if ($code === '') {
            return null;
        }

        $db  = Factory::getContainer()->get('DatabaseDriver');
        $row = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['code', 'title', 'article_id']))
                ->from($db->quoteName('#__jed_block_reasons'))
                ->where($db->quoteName('code') . ' = :code')
                ->bind(':code', $code)
        )->loadAssoc();

        if ($row === null) {
            return null;
        }

        return [
            'code'       => (string) $row['code'],
            'title'      => (string) $row['title'],
            'article_id' => $row['article_id'] === null ? null : (int) $row['article_id'],
        ];
    }

    /**
     * Checks if a given date is valid and in a specified format (YYYY-MM-DD)
     *
     * @param string $date Date to be checked
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function isValidDate(string $date): bool
    {
        $date = str_replace('/', '-', $date);

        return (date_create($date)) ? Factory::getDate($date)->format("Y-m-d") : false;
    }

    /**
     * is_blank
     *
     * isEmpty sees a value of 0 as being empty which means that using it to test database option values fails with entries of 0
     *
     * @param $value
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function is_blank($value): bool
    {
        return empty($value) && !is_numeric($value);
    }

    /**
     * reformatTitle
     *
     * A lot of the restored JED 3 titles have extra spacing or missing punctuation. This fixes that for display.
     *
     * @param $l_str
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function reformatTitle($l_str): string
    {
        $loc = str_replace(',', ', ', $l_str);
        $loc = str_replace(' ,', ',', $loc);
        $loc = str_replace('  ', ' ', $loc);

        return trim($loc);
    }

    /**
     * This method advises if the $id of the item belongs to the current user
     *
     * @param int    $id    The id of the item
     * @param string $table The name of the table
     *
     * @return bool             true if the user is the owner of the row, false if not.
     * @since  4.0.0
     * @throws Exception
     */
    public static function userIDItem(int $id, string $table): bool
    {
        // The helper answers "did the current user write this", and that is only a meaningful
        // question for authored records - tickets, ticket messages, reviews. For an extension the
        // question is ownership, which lives in a different column and follows transfers.
        // Refusing the table outright is deliberate: a helper whose correctness depends on which
        // table it is handed will eventually be handed the wrong one, and the failure would be
        // silent - the previous owner keeps access after a transfer (8.8.1).
        if (str_contains($table, 'jed_extensions')) {
            throw new Exception(
                'userIDItem() answers authorship and must not be used for extensions;'
                . ' use JedHelper::isOwner() or isOwnerOrMaintainer() instead.',
                500
            );
        }

        $userId = (int) self::getUser()->id;

        if ($userId <= 0 || $id <= 0) {
            return false;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            return (bool) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName($table))
                    ->where($db->quoteName('id') . ' = :id')
                    ->where($db->quoteName('created_by') . ' = :userId')
                    ->bind(':id', $id, ParameterType::INTEGER)
                    ->bind(':userId', $userId, ParameterType::INTEGER)
            )->loadResult();
        } catch (Exception $exc) {
            throw new Exception($exc->getMessage(), $exc->getCode());
        }
    }

    /**
     * This method returns whether an alias is available for the view
     *
     * @param string $view The name of the view
     *
     * @return string
     * @since  4.0.0
     */
    public static function getAliasFieldNameByView(string $view): string
    {
        return match ($view) {
            'extension', 'extensionform', 'review', 'reviewform' => 'alias',
            default                                              => "",
        };
    }

    /**
     * if User is logged in then can save data
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public static function canSave(): bool
    {
        try {
            $user = Factory::getApplication()->getIdentity();
            if ($user->id <> null) {
                //user must be logged in
                return true;
            }
        } catch (Exception $exc) {
            throw new Exception($exc->getMessage(), $exc->getCode());
        }

        return false;
    }

    /**
     * outputFieldsets
     *
     * Outputs custom form field from array
     *
     * @param array $fieldsets
     * @param Form  $form
     * @param bool  $validate
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function outputFieldsets(array $fieldsets, Form $form, bool $validate = true): bool
    {
        $fscount = 0;
        foreach ($fieldsets as $fscat => $fs) {
            Log::add($fscat);
            $fscount = $fscount + 1;

            if ($fs['title'] <> '') {
                if ($fscount > 1) {
                    echo '</fieldset>';
                }
                if (key_exists('supply_type', $fs)) {
                    $st = '_' . $fs['supply_type'];
                } else {
                    $st = '';
                }

                echo '<fieldset class="extensionform' . $st . '"><legend>' . $fs['title'] . '</legend>';
            }
            if ($fs['description'] <> '') {
                echo $fs['description'];
            }
            $fields       = $fs['fields'];
            $hiddenFields = $fs['hidden'];
            foreach ($fields as $field) {
                if (is_array($field)) {
                    // Split into two columns
                    echo '<div class="row"><div class="col-md-6">';
                    if (in_array($field[0], $hiddenFields)) {
                        if (!$validate) {
                            $form->setFieldAttribute($field[0], 'required', 'false');
                            $form->setFieldAttribute($field[0], 'validate', '');
                        }

                        $form->setFieldAttribute($field[0], 'type', 'hidden');
                    }
                    echo $form->renderField($field[0], null, null, ['class' => 'control-wrapper-' . $field[0]]);
                    echo '</div>';
                    echo '<div class="col-md-6">';
                    if (!$validate) {
                        $form->setFieldAttribute($field[1], 'required', 'false');
                        $form->setFieldAttribute($field[1], 'validate', '');
                    }

                    if (in_array($field[1], $hiddenFields)) {
                        $form->setFieldAttribute($field[1], 'type', 'hidden');
                    }
                    echo $form->renderField($field[1], null, null, ['class' => 'control-wrapper-' . $field[1]]);
                    echo '</div></div>';
                } else {
                    if (in_array($field, $hiddenFields)) {
                        $form->setFieldAttribute($field, 'type', 'hidden');
                    }
                    if (! $validate) {
                        $form->setFieldAttribute($field, 'required', 'false');
                        $form->setFieldAttribute($field, 'validate', '');
                    }
                    //var_dump($field);
                    echo $form->renderField($field, null, null, ['class' => 'control-wrapper-' . $field]);
                }
            }
        }
        echo '</fieldset>';
        return true;
    }

    /**
     * Get Extension Title from Database and return
     *
     * @param int $extensionId
     *
     * @return string
     *
     * @since 4.0.0
     */
    /**
     * The title of a review, for the report ticket's subject line.
     *
     * The counterpart to getExtensionTitle(): a report about a review should name the review,
     * not only the extension it belongs to, or a moderator reading the ticket queue cannot tell
     * two reports of the same extension apart.
     *
     * @param int $reviewId The review id.
     *
     * @return string  Empty when the review does not exist.
     *
     * @since 4.0.0
     */
    public static function getReviewTitle(int $reviewId): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        return (string) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('title'))
                ->from($db->quoteName('#__jed_reviews'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $reviewId, ParameterType::INTEGER)
        )->loadResult();
    }

    public static function getExtensionTitle(int $extensionId): string
    {
        // Create a new query object.
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('name')->from($db->quoteName('#__jed_extensions'))->where('id=' . $extensionId);
        // Reset the query using our newly populated query object.
        $db->setQuery($query);

        // Load the results as a stdClass object.
        return (string) $db->loadResult();
    }

    /**
     * Prettyfy a Date
     *
     * @param string $datestr A String Date
     *
     * @since 4.0.0
     **/
    public static function prettyDate(mixed $datestr): string
    {

        try {
            $d = new DateTime($datestr);

            return $d->format("d M y H:i");
        } catch (Exception) {
            return 'Sorry an error occured';
        }
    }

    /**
     * Prettyfy a Date into short format
     *
     * @param string $datestr A String Date
     *
     * @since 4.0.0
     **/
    public static function prettyShortDate(mixed $datestr): string
    {

        try {
            $d = new DateTime($datestr);

            return $d->format("d M y");
        } catch (Exception) {
            return 'Sorry an error occured';
        }
    }
}
