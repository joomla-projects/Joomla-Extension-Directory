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
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User;
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
        $language = Factory::getApplication()->getLanguage()->getTag();
        $mail     = MailTemplate::getTemplate($templateId, $language);

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
        $permission = false;
        $user       = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.edit', 'com_jed')) {
            $permission = true;
        } else {
            if (isset($item->created_by)) {
                if ($item->created_by == $user->id) {
                    $permission = true;
                }
            } else {
                $permission = true;
            }
        }

        return $permission;
    }

    /**
     * Function to format JED Extension Images
     *
     * Resolves a filename stored on #__jed_extensions.logo/overview_image or
     * #__jed_extensions_images.filename to a full, browsable URL. Depending on the
     * component's "use_cdn" setting, images are either served from the local
     * images/extensions folder or from the configured CDN base URL.
     *
     * @param string    $filename The image filename (or already-absolute URL)
     * @param ImageSize $size     Size of image, small|large (currently informational only,
     *                            no resized variants are generated)
     *
     * @return string  Full image url
     *
     * @since 4.0.0
     */
    public static function formatImage(string $filename, ImageSize $size = ImageSize::SMALL): string
    {
        if (!$filename) {
            return '';
        }

        if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
            return $filename;
        }

        $params   = ComponentHelper::getParams('com_jed');
        $filename = ltrim($filename, '/\\');

        if ($params->get('use_cdn', 0)) {
            $cdnUrl = rtrim((string) $params->get('cdn_url', ''), '/');

            return $cdnUrl . '/' . $filename;
        }

        return Uri::root() . 'images/extensions/' . $filename;
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
     * Checks whether the current user is the owner of the given extension (#__jed_extensions.owner)
     * or listed as one of its maintainers (#__jed_extensions_maintainers) - the same check
     * ExtensionformModel::isAuthorised() uses to decide who may edit an extension, factored out
     * here so review/dashboard code can reuse it without duplicating the query.
     *
     * @param int $extensionId The extension PK in #__jed_extensions.
     *
     * @return bool
     *
     * @since  4.1.0
     * @throws Exception
     */
    public static function isOwnerOrMaintainer(int $extensionId): bool
    {
        $userId = (int) self::getUser()->id;

        if (!$userId) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $ownerQuery = $db->getQuery(true)
            ->select($db->quoteName('owner'))
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = :eid')
            ->bind(':eid', $extensionId, ParameterType::INTEGER);

        if ((int) $db->setQuery($ownerQuery)->loadResult() === $userId) {
            return true;
        }

        $maintainerQuery = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__jed_extensions_maintainers'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->where($db->quoteName('user_id') . ' = :uid')
            ->bind(':eid', $extensionId, ParameterType::INTEGER)
            ->bind(':uid', $userId, ParameterType::INTEGER);

        return (bool) $db->setQuery($maintainerQuery)->loadResult();
    }

    /**
     * The single visibility rule for extension listings in the frontend.
     *
     * A listing is public when the JED team has approved it AND the developer has it online.
     * On top of that, owners and maintainers always see their own listings, so they can review
     * a submission before it goes live - but nobody else does, whatever their permissions.
     * Backend permissions deliberately do not widen this: the frontend is the public site, and
     * `core.edit` used to leak every unpublished listing into it.
     *
     * Returned as a SQL fragment rather than applied directly, so the callers can add it to
     * their own query. Defining it in one place is the point - a rule this easy to get subtly
     * wrong must not be copied into four models.
     *
     * @param DatabaseInterface $db    The database driver, for quoting.
     * @param string            $alias The table alias used for #__jed_extensions in the query.
     *
     * @return string  A parenthesised SQL condition.
     *
     * @since  4.1.0
     * @throws Exception
     */
    public static function getExtensionVisibilityCondition(DatabaseInterface $db, string $alias = 'a'): string
    {
        $public = '(' . $db->quoteName($alias . '.state') . ' = 1 AND '
            . $db->quoteName($alias . '.approved') . ' = 1)';

        $userId = (int) self::getUser()->id;

        if ($userId <= 0) {
            return $public;
        }

        // Owner OR a maintainer row - never created_by, which does not follow an ownership
        // transfer and would leave the previous owner able to see the listing.
        $own = $db->quoteName($alias . '.owner') . ' = ' . $userId;

        $maintained = 'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jed_extensions_maintainers', 'vm')
            . ' WHERE ' . $db->quoteName('vm.extension_id') . ' = ' . $db->quoteName($alias . '.id')
            . ' AND ' . $db->quoteName('vm.user_id') . ' = ' . $userId . ')';

        return '(' . $public . ' OR ' . $own . ' OR ' . $maintained . ')';
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
     * @since 4.1.0
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
     * @since 4.1.0
     */
    public static function markdownToText(?string $text, int $length = 0): string
    {
        return AdminJedHelper::markdownToText($text, $length);
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
     * @since  4.1.0
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
     * @since  4.1.0
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
     * The row counterpart of getExtensionVisibilityCondition(), for the detail view where the
     * record has already been loaded.
     *
     * @param object $item The loaded extension row.
     *
     * @return bool
     *
     * @since  4.1.0
     * @throws Exception
     */
    public static function canViewExtension(object $item): bool
    {
        if ((int) ($item->state ?? 0) === 1 && (int) ($item->approved ?? 0) === 1) {
            return true;
        }

        return isset($item->id) && self::isOwnerOrMaintainer((int) $item->id);
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
        try {
            $user = Factory::getApplication()->getIdentity();
            $db   = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);
            $query->select("id")->from($db->quoteName($table))->where("id = " . $db->escape($id))->where("created_by = " . $user->id);

            $db->setQuery($query);

            $results = $db->loadObject();
            if ($results) {
                return true;
            }
            return false;
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
