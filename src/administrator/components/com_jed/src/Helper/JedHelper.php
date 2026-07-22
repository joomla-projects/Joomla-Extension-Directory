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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

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
     * Format a single extension field's value as plain, read-only markup. Shared
     * between the read-only extension view (`tmpl/extension/default.php`) and the
     * history compare view (`tmpl/extension/compare.php`).
     *
     * @param string $fieldname The `#__jed_extensions`/`#__jed_extensions_history` column name.
     * @param mixed  $value     The raw stored value.
     *
     * @return string
     *
     * @since 4.1.0
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
                return Text::_($yesNoOptions[(int) $value] ?? 'JNO');

            case 'catid':
                return self::displayCategory((int) $value);

            case 'owner':
            case 'created_by':
            case 'modified_by':
                return self::displayUser((int) $value);

            case 'approved_time':
            case 'created':
            case 'modified':
                return empty($value) ? '&#8212;' : HTMLHelper::_('date', $value, Text::_('COM_JED_GENERAL_DATETIME_FORMAT'));

            case 'intro':
            case 'description':
                return '<div class="jed-view-html">' . (string) $value . '</div>';

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
            case 'overview_image':
                if (empty($value)) {
                    return '&#8212;';
                }

                return '<img src="' . htmlspecialchars(self::formatImage((string) $value, ImageSize::LARGE), ENT_QUOTES, 'UTF-8') . '" alt="" class="jed-view-image">';

            case 'approved_notes':
            case 'approved_reason':
            case 'internal_note':
                return empty($value) ? '&#8212;' : nl2br(htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));

            default:
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                return $value === null || $value === '' ? '&#8212;' : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }
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
     * @since 4.1.0
     */
    public static function displayOptionList(mixed $value, array $options, bool $translate = true): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : array_filter(explode(',', $value));
        }

        $value = (array) $value;

        if (empty($value)) {
            return '&#8212;';
        }

        $labels = array_map(
            static function ($v) use ($options, $translate) {
                $label = $options[$v] ?? $v;

                return $translate ? Text::_($label) : $label;
            },
            $value
        );

        return implode(', ', $labels);
    }

    /**
     * Resolve a user id to a display name.
     *
     * @param int $userId The user id.
     *
     * @return string
     *
     * @since 4.1.0
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
     * @since 4.1.0
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

        $query->select($field)->from($table)->where('id = ' . $pk);

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
