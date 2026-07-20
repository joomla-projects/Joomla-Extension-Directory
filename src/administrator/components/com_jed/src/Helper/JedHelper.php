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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User;
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
     * Gets a list of the actions that can be performed.
     *
     * @return registry
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function getActions($component = '', $section = '', $id = 0)
    {
        if ($component) {
            return parent::getActions($component, $section, $id);
        }
        //$user   = Factory::getUser();

        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        $user   = $app->getSession()->get('user');
        $result = new Registry();

        $assetName = 'com_jed';

        $actions = [
            'core.admin',
            'core.manage',
            'core.create',
            'core.edit',
            'core.edit.own',
            'core.edit.state',
            'core.delete',
        ];

        foreach ($actions as $action) {
            $result->set($action, $user->authorise($action, $assetName));
        }

        return $result;
    }

    /**
     * Returns a span string containing an icon denoting approved status
     *
     * @return registry
     *
     * @since  4.0.0
     * @throws Exception
     */
    public static function getApprovedIcon(int $state): string
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
