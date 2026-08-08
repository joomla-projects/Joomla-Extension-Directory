<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects


use DateTime;
use Exception;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use League\CommonMark\CommonMarkConverter;

/**
 * JED Helper
 *
 * @package JED
 * @since   4.0.0
 */
class JedHelper extends ContentHelper
{
    /**
     * Add config toolbar to admin pages
     *
     * @since 4.0.0
     */
    public static function addConfigToolbar(Toolbar $bar): void
    {
        $bar->linkButton('tickets')->text(Text::_('COM_JED_TITLE_TICKETS'))->url('index.php?option=com_tickets&view=tickets')->icon('fa fa-ticket-alt');
        $bar->linkButton('vulnerable')->text('Vulnerable Items')->url('index.php?option=com_jed&view=velvulnerableitems')->icon('fa fa-bug');
        $bar->linkButton('extensions')->text('Extensions')->url('index.php?option=com_jed&view=extensions')->icon('fa fa-puzzle-piece');


        /*
         * Only for finally moving live to test
         */
        $bar->linkButton('copyjed3data')->text('COM_JED_TITLE_COPY_JED3_DATA')->icon('fa fa-link')->url('index.php?option=com_jed&view=copyjed3data');
    }

    /**
     * Resolve a stored image reference to a full, browsable URL.
     *
     * The value on #__jed_extensions.logo / overview_image or #__jed_extensions_images.filename
     * comes in one of three shapes, and each is served differently:
     *
     *  - An absolute URL, which is returned untouched.
     *  - A path below the site root, e.g. "images/jed_extensions/7/images/1754-logo.png". This
     *    is what ImagePipeline writes, so the requested variant exists as a sibling file and is
     *    served when it is there. It is not there when the upload was already smaller than the
     *    variant's box, in which case the original is the right answer anyway.
     *  - A bare filename, e.g. "56e27fa7736c8.png". That is a JED3 reference: 13,149 logos and
     *    33,873 screenshots came across as filenames with no path, and the files themselves
     *    live on the JED3 CDN rather than in this installation. With "use_cdn" on they are
     *    served from "cdn_url"; with it off, from the local images/extensions folder, which is
     *    where a bulk copy would put them. There are no variants for these either way.
     *
     * @param string    $filename The stored reference.
     * @param ImageSize $size     The variant wanted. Falls back to the original when that
     *                            variant was not generated.
     *
     * @return string  Full image URL, or an empty string when nothing is stored.
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

        $filename = ltrim($filename, '/\\');

        if (str_contains($filename, '/')) {
            $variant = $size->applyTo($filename);

            if ($variant !== $filename && is_file(JPATH_ROOT . '/' . $variant)) {
                return Uri::root() . $variant;
            }

            return Uri::root() . $filename;
        }

        $params = ComponentHelper::getParams('com_jed');

        if ($params->get('use_cdn', 0)) {
            return rtrim((string) $params->get('cdn_url', ''), '/') . '/' . $filename;
        }

        return Uri::root() . 'images/extensions/' . $filename;
    }

    /**
     * Returns a span string containing an icon (and tooltip) denoting whether an
     * extension has been approved. `approved` is a plain 0/1 flag.
     *
     * @param int $approved 1 if approved, 0 otherwise.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function getApprovedIcon(int $approved): string
    {
        if ($approved === 1) {
            return '<span class="icon-check text-success" title="' . Text::_('COM_JED_EXTENSION_APPROVED_LABEL')
                . '" data-bs-toggle="tooltip" aria-hidden="true"></span>';
        }

        return '<span class="icon-times text-danger" title="' . Text::_('COM_JED_EXTENSION_APPROVED_LABEL_PENDING')
            . '" data-bs-toggle="tooltip" aria-hidden="true"></span>';
    }

    /**
     * Render a stored description or intro as HTML.
     *
     * Listing texts are Markdown. The JED3 stock they were imported from is plain text with
     * blank lines between paragraphs, which is already valid Markdown, and the import strips
     * the handful of rows that carried HTML - so there is exactly one format to render.
     *
     * Two settings matter:
     *
     *  - `html_input = strip` means raw HTML in a description is removed rather than passed
     *    through. Descriptions are developer-supplied, so this is the boundary that keeps
     *    them from injecting markup, and it holds even if HTML gets into the column later.
     *  - `soft_break = <br>` keeps single newlines visible. About 1,100 of the imported
     *    descriptions use single newlines for what are effectively bullet lines, and stock
     *    Markdown would run them together into one paragraph.
     *
     * @param string|null $text The stored Markdown.
     *
     * @return string  Rendered HTML, safe to output.
     *
     * @since 4.0.0
     */
    public static function renderMarkdown(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        static $converter = null;

        if ($converter === null) {
            // CommonMark lives in com_jed's own vendor directory, which JedComponent includes
            // when the component boots. This helper is called from places where that has not
            // happened: a module on a com_content page, a CLI harness, a task plugin. Each of
            // those got a fatal `Class "League\CommonMark\CommonMarkConverter" not found` - and
            // in the module's case that meant the site's home page returned a 500 as soon as the
            // module was published. Loading it here makes the helper self-sufficient, which is
            // what a static helper has to be; the autoloader is idempotent.
            if (!class_exists(CommonMarkConverter::class)) {
                include_once __DIR__ . '/../../vendor/autoload.php';
            }

            $converter = new CommonMarkConverter([
                'html_input'         => 'strip',
                'allow_unsafe_links' => false,
                'renderer'           => ['soft_break' => "<br>\n"],
            ]);
        }

        return (string) $converter->convert($text);
    }

    /**
     * Reduce a stored description or intro to plain text.
     *
     * For places that need a short, unformatted excerpt - card summaries, meta descriptions -
     * where rendered Markdown would inject block markup into an inline context.
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
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags(self::renderMarkdown($text))));

        if ($length > 0 && mb_strlen($plain) > $length) {
            $plain = mb_substr($plain, 0, $length);
            $plain = rtrim(mb_substr($plain, 0, mb_strrpos($plain, ' ') ?: $length)) . '…';
        }

        return $plain;
    }

    /**
     * Format a single extension field's value as plain, read-only markup. Shared
     * between the read-only extension view (`tmpl/extension/default.php`) and the
     * history compare view (`tmpl/extension/compare.php`).
     *
     * @param string $fieldname The `#__jed_extensions`/`#__jed_extensions_history` column name.
     * @param mixed  $value     The raw stored value.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function displayFieldValue(string $fieldname, mixed $value): string
    {
        $yesNoOptions = [0 => 'JNO', 1 => 'JYES'];

        $stateOptions = [
            1  => 'JPUBLISHED',
            0  => 'JUNPUBLISHED',
            2  => 'JARCHIVED',
            -2 => 'JTRASHED',
        ];

        $typeOptions = [
            'free'     => 'COM_JED_GENERAL_TYPE_LABEL_FREE',
            'paid'     => 'COM_JED_GENERAL_TYPE_LABEL_PAID',
            'freemium' => 'COM_JED_GENERAL_TYPE_LABEL_FREEMIUM',
            'cloud'    => 'COM_JED_GENERAL_TYPE_LABEL_CLOUD',
        ];

        $extensionTypeOptions = [
            'com'      => 'COM_JED_EXTENSION_COMPONENT_LABEL',
            'mod'      => 'COM_JED_EXTENSION_MODULE_LABEL',
            'plugin'   => 'COM_JED_EXTENSION_PLUGIN_LABEL',
            'specific' => 'COM_JED_EXTENSION_SPECIFIC_LABEL',
        ];

        $joomlaVersionOptions = [
            '30' => '3.0',
            '40' => '4.0',
            '41' => '4.1',
            '42' => '4.2',
            '43' => '4.3',
            '44' => '4.4',
            '50' => '5.0',
            '60' => '6.0',
        ];

        switch ($fieldname) {
            case 'state':
                return Text::_($stateOptions[(int) $value] ?? 'JUNPUBLISHED');

            case 'type':
                return Text::_($typeOptions[(string) $value] ?? '');

            case 'extension_types':
                return self::displayOptionList($value, $extensionTypeOptions);

            case 'joomla_versions':
                return self::displayOptionList($value, $joomlaVersionOptions, false);

            case 'approved':
            case 'requires_registration':
            case 'uses_updater':
            case 'popular':
            case 'blocked':
            case 'deleted':
                return Text::_($yesNoOptions[(int) $value] ?? 'JNO');

            case 'catid':
                return self::displayCategory((int) $value);

            case 'owner':
            case 'created_by':
            case 'modified_by':
            case 'blocked_by':
            case 'deleted_by':
                return $value === null ? '&#8212;' : self::displayUser((int) $value);

            case 'approved_time':
            case 'created':
            case 'modified':
            case 'blocked_time':
            case 'deleted_time':
                return empty($value) ? '&#8212;' : HTMLHelper::_('date', $value, Text::_('COM_JED_GENERAL_DATETIME_FORMAT'));

            case 'intro':
            case 'description':
                return '<div class="jed-view-html">' . self::renderMarkdown((string) $value) . '</div>';

            case 'download_url':
            case 'support_url':
            case 'demo_url':
            case 'documentation_url':
            case 'git_url':
            case 'internal_download_url':
            case 'update_url':
            case 'video':
                if (empty($value)) {
                    return '&#8212;';
                }

                return '<a href="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
                    . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</a>';

            case 'logo':
                if (empty($value)) {
                    return '&#8212;';
                }

                return '<img src="' . htmlspecialchars(self::formatImage((string) $value, ImageSize::LARGE), ENT_QUOTES, 'UTF-8') . '" alt="" class="jed-view-image">';

            case 'approved_notes':
            case 'approved_reason':
            case 'internal_note':
                return empty($value) ? '&#8212;' : nl2br(htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));

            case 'tags':
                return self::displayTags($value);

            default:
                return self::displayScalar($value);
        }
    }

    /**
     * Render the tags of an extension as a plain, comma separated list of titles.
     *
     * The value arrives in whichever shape the caller happened to have: the admin
     * `ExtensionModel` hands over a populated `TagsHelper` (whose public `$tags` property holds
     * the ids), a form round-trip hands over an array of ids, and a raw table read hands over a
     * comma separated string. All three are reduced to ids here and resolved to titles in one
     * query. Ids that no longer resolve are shown as-is rather than dropped, so a dangling tag
     * assignment stays visible instead of silently disappearing from the view.
     *
     * @param mixed $value A TagsHelper, an array of ids or tag objects, or a CSV string of ids.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function displayTags(mixed $value): string
    {
        // A populated TagsHelper keeps the ids on its public "tags" property.
        if ($value instanceof TagsHelper) {
            $value = $value->tags;
        }

        if (is_string($value)) {
            $value = array_filter(explode(',', $value), static fn ($v) => trim((string) $v) !== '');
        }

        $ids    = [];
        $labels = [];

        foreach ((array) $value as $entry) {
            // A tag row from TagsHelper::getItemTags() carries its own title already.
            if (is_object($entry)) {
                if (isset($entry->title) && $entry->title !== '') {
                    $labels[] = (string) $entry->title;
                    continue;
                }

                $entry = $entry->id ?? $entry->tag_id ?? null;
            }

            if (is_numeric($entry)) {
                $ids[] = (int) $entry;
            } elseif (is_string($entry) && $entry !== '') {
                $labels[] = $entry;
            }
        }

        if ($ids) {
            // The whole database interaction is guarded: this helper exists to render a row, and
            // must never be the reason a read-only view fails to load.
            try {
                $db    = Factory::getContainer()->get(DatabaseInterface::class);
                $query = $db->getQuery(true)
                    ->select($db->quoteName('title'))
                    ->from($db->quoteName('#__tags'))
                    ->whereIn($db->quoteName('id'), $ids);

                $titles = $db->setQuery($query)->loadColumn() ?: [];
            } catch (\Throwable $e) {
                Log::add('Unable to resolve tag titles: ' . $e->getMessage(), Log::WARNING, 'jerror');

                $titles = [];
            }

            // Keep unresolved ids visible rather than pretending the assignment is not there.
            $labels = array_merge($labels, $titles ?: array_map('strval', $ids));
        }

        if (!$labels) {
            return '&#8212;';
        }

        return implode(', ', array_map(
            static fn ($label) => htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            $labels
        ));
    }

    /**
     * Render any remaining field value as escaped text.
     *
     * This is the fallback for every column without an explicit case above, so it has to cope
     * with whatever a field type puts on the item - including objects. Casting an object with no
     * `__toString()` is a fatal `Error`, not a notice, so an unhandled one would take the whole
     * read-only view down rather than spoiling a single row. Anything that cannot be rendered
     * sensibly degrades to a dash.
     *
     * @param mixed $value The raw stored value.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private static function displayScalar(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '&#8212;';
        }

        if (is_bool($value)) {
            return Text::_($value ? 'JYES' : 'JNO');
        }

        if (is_array($value)) {
            $parts = array_filter(
                array_map(static fn ($item) => self::stringifyValue($item), $value),
                static fn ($item) => $item !== ''
            );

            return $parts
                ? htmlspecialchars(implode(', ', $parts), ENT_QUOTES, 'UTF-8')
                : '&#8212;';
        }

        $string = self::stringifyValue($value);

        return $string === '' ? '&#8212;' : htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Reduce a single value to a string without ever throwing.
     *
     * Objects are tried in order of how likely they are to carry something meaningful for a
     * human: an explicit string conversion, then the usual title/name/value properties, then a
     * JSON encoding. Nested arrays and objects are flattened one level, which is as deep as any
     * field value in this component goes.
     *
     * @param mixed $value A single value of any type.
     *
     * @return string  The rendered value, or an empty string when there is nothing to show.
     *
     * @since 4.0.0
     */
    private static function stringifyValue(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return $value === true ? '1' : '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            if ($value instanceof TagsHelper) {
                return implode(', ', array_map('strval', (array) ($value->tags ?? [])));
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            if ($value instanceof DateTime) {
                return $value->format('Y-m-d H:i:s');
            }

            foreach (['title', 'name', 'value', 'label'] as $property) {
                if (isset($value->$property) && is_scalar($value->$property)) {
                    return (string) $value->$property;
                }
            }

            if ($value instanceof Registry) {
                return $value->toString('JSON');
            }

            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            $parts = array_filter(
                array_map(
                    static fn ($item) => is_scalar($item) ? (string) $item : '',
                    $value
                ),
                static fn ($item) => $item !== ''
            );

            if ($parts) {
                return implode(', ', $parts);
            }

            // Nothing scalar to show. A structure that still holds something is worth rendering as
            // JSON for the admin; one that flattened to nothing becomes a dash upstream.
            if (!$value) {
                return '';
            }

            $encoded = json_encode($value);

            return $encoded === false ? '' : $encoded;
        }

        return '';
    }

    /**
     * Render a (possibly multi-value) field against a value => label option map.
     *
     * @param mixed $value     The raw stored value (JSON-ish array string, CSV string, or array).
     * @param array $options   Map of stored value => language key (or plain label if $translate is false).
     * @param bool  $translate Whether to run each label through Text::_().
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function displayOptionList(mixed $value, array $options, bool $translate = true): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : array_filter(explode(',', $value));
        }

        // An object here is a value shape this map cannot key on - reduce it to its own
        // properties so the entries at least render, instead of failing on the implode below.
        if (is_object($value)) {
            $value = $value instanceof TagsHelper ? (array) ($value->tags ?? []) : get_object_vars($value);
        }

        $value = (array) $value;

        if (empty($value)) {
            return '&#8212;';
        }

        $labels = array_filter(
            array_map(
                static function ($v) use ($options, $translate) {
                    // Only a scalar can index the option map. Anything else is stringified first,
                    // so a stray object cannot reach implode() and fatal the whole view.
                    $key = is_scalar($v) ? $v : self::stringifyValue($v);

                    if ($key === '') {
                        return '';
                    }

                    $label = $options[$key] ?? $key;

                    return $translate ? Text::_((string) $label) : (string) $label;
                },
                $value
            ),
            static fn ($label) => $label !== ''
        );

        if (!$labels) {
            return '&#8212;';
        }

        return implode(', ', array_map(
            static fn ($label) => htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            $labels
        ));
    }

    /**
     * Resolve a user id to a display name.
     *
     * @param int $userId The user id.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function displayUser(int $userId): string
    {
        if (!$userId) {
            return '&#8212;';
        }

        $name = self::getUserById($userId)->name;

        return $name !== '' ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : '&#8212;';
    }

    /**
     * Resolve a category id to its title.
     *
     * @param int $catid The `#__categories` id.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function displayCategory(int $catid): string
    {
        static $cache = [];

        if (!$catid) {
            return '&#8212;';
        }

        if (!array_key_exists($catid, $cache)) {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('title'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = :catid')
                ->bind(':catid', $catid, ParameterType::INTEGER);
            $cache[$catid] = $db->setQuery($query)->loadResult();
        }

        return $cache[$catid] ? htmlspecialchars($cache[$catid], ENT_QUOTES, 'UTF-8') : '&#8212;';
    }

    /**
     * Gets the files attached to an item
     *
     * @param int    $pk    The item's id
     *
     * @param string $table The table's name
     *
     * @param string $field The field's name
     *
     * @return array  The files
     *
     * @since 4.0.0
     */
    public static function getFiles(int $pk, string $table, string $field): array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select($field)->from($db->quoteName($table))->where('id = ' . $pk);

        $db->setQuery($query);

        return explode(',', (string) $db->loadResult());
    }

    /**
     * Returns a span string containing an icon denoting published status
     *
     * @return registry
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function getPublishedIcon(int $state): string
    {
        $icon = match ((string) $state) {
            '-1' => 'unpublish',
            // Approved
            '1' => 'publish',
            // Awaiting response
            '2'     => 'expired',
            default => 'pending',
        };

        return '<span class="icon-' . $icon . '" aria-hidden="true"></span>';
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
        try {//$user   = Factory::getUser();
            $container   = Factory::getContainer();
            $userFactory = $container->get('user.factory');

            return $userFactory->loadUserById($userId);
        } catch (Exception) {
            return new User\User();
        }
    }

    /**
     * Checks whether or not a user is manager or super user
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function isAdminOrSuperUser(): bool
    {
        try {
            $user = self::getUser();

            return in_array("8", $user->groups) || in_array("7", $user->groups);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Gets the current User .
     *
     * @return User\User
     *
     * @since 4.0.0
     */
    public static function getUser(): User\User
    {
        try {
            /* @var $app \Joomla\CMS\Application\SiteApplication */
            $app = Factory::getApplication();
            return $app->getSession()->get('user');
        } catch (Exception) {
            return new User\User();
        }
    }

    /**
     * Lock form fields
     *
     * This takes a form and marks all fields as readonly/disabled
     *
     * @param $form     form of fields
     * @param $excluded array of fields not to lock
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function lockFormFields(Form $form, array $excluded): bool
    {
        $fields = $form->getFieldset();
        foreach ($fields as $field) :
            if (!in_array($field->getAttribute('name'), $excluded)) {
                $form->setFieldAttribute($field->getAttribute('name'), 'disabled', 'true');
                //    $form->setFieldAttribute($field->getAttribute('name'), 'class', 'readonly');
                //  $form->setFieldAttribute($field->getAttribute('name'), 'readonly', 'true');
            }
        endforeach;

        return true;
    }

    /**
     * Prettyfy a Data
     *
     * @param string $datestr A String Date
     *
     * @since 4.0.0
     **/
    public static function prettyDate(mixed $datestr): string
    {
        if (! is_null($datestr)) {
            try {
                $d = new DateTime($datestr);

                return $d->format("d M y H:i");
            } catch (Exception) {
                return 'Sorry an error occured';
            }
        } else {
            return "";
        }
    }

    /**
     * outputFieldsets
     *
     * Outputs custom form field from array
     *
     * @param array $fieldsets
     * @param Form  $form
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function outputFieldsets(array $fieldsets, Form $form): bool
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
                        $form->setFieldAttribute($field[0], 'type', 'hidden');
                    }
                    echo $form->renderField($field[0], null, null, ['class' => 'control-wrapper-' . $field[0]]);
                    echo '</div>';
                    echo '<div class="col-md-6">';
                    if (in_array($field[1], $hiddenFields)) {
                        $form->setFieldAttribute($field[1], 'type', 'hidden');
                    }
                    echo $form->renderField($field[1], null, null, ['class' => 'control-wrapper-' . $field[1]]);
                    echo '</div></div>';
                } else {
                    if (in_array($field, $hiddenFields)) {
                        $form->setFieldAttribute($field, 'type', 'hidden');
                    }
                    echo $form->renderField($field, null, null, ['class' => 'control-wrapper-' . $field]);
                }
            }
        }
        echo '</fieldset>';
        return true;
    }
}
