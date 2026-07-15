<?php

/** @var \Jed\Component\Jed\Administrator\View\Extension\HtmlView $this */
/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Read-only view of an extension: the same data as tmpl/extension/edit.php, grouped into the
 * same tabs/fieldsets, but rendered as plain labels/values instead of form fields.
 */
// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

if (!function_exists('jedDisplayValue')) {
    /**
     * Format a single field's value as plain, read-only markup.
     *
     * @since 4.0.0
     */
    function jedDisplayValue(string $fieldname, mixed $value): string
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
                return jedDisplayOptionList($value, $extensionTypeOptions);

            case 'joomla_versions':
                return jedDisplayOptionList($value, $joomlaVersionOptions, false);

            case 'approved':
            case 'requires_registration':
            case 'uses_updater':
            case 'popular':
                return Text::_($yesNoOptions[(int) $value] ?? 'JNO');

            case 'catid':
                return jedDisplayCategory((int) $value);

            case 'owner':
            case 'created_by':
            case 'modified_by':
                return jedDisplayUser((int) $value);

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

                return '<img src="' . htmlspecialchars(JedHelper::formatImage((string) $value, ImageSize::LARGE), ENT_QUOTES, 'UTF-8') . '" alt="" class="jed-view-image">';

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
}

if (!function_exists('jedDisplayOptionList')) {
    /**
     * Render a (possibly multi-value) field against a value => label option map.
     *
     * @since 4.0.0
     */
    function jedDisplayOptionList(mixed $value, array $options, bool $translate = true): string
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
}

if (!function_exists('jedDisplayUser')) {
    /**
     * Resolve a user id to a display name.
     *
     * @since 4.0.0
     */
    function jedDisplayUser(int $userId): string
    {
        if (!$userId) {
            return '&#8212;';
        }

        $name = JedHelper::getUserById($userId)->name;

        return $name !== '' ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : '&#8212;';
    }
}

if (!function_exists('jedDisplayCategory')) {
    /**
     * Resolve a category id to its title.
     *
     * @since 4.0.0
     */
    function jedDisplayCategory(int $catid): string
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
}

// Field types/names that should not get a plain label/value row (subforms have their own
// dedicated read-only sections below; these others carry no meaningful display value).
$jedSkipFieldsetTypes  = ['subform', 'hidden', 'spacer'];
// "categories" is rendered separately further down (from $this->categories, with resolved
// titles); the field's own item value is just a list of catids and would otherwise duplicate it.
$jedSkipFieldsetFields = ['id', 'checked_out', 'checked_out_time', 'categories'];

?>
<style>
    .jed-view-row { padding: .4rem 0; border-bottom: 1px solid var(--bs-border-color, #eee); }
    .jed-view-row dt { font-weight: 600; }
    .jed-view-row dd { margin-bottom: 0; }
    .jed-view-html { max-width: 60rem; }
    .jed-view-image { max-width: 240px; max-height: 240px; object-fit: contain; display: block; }
    .jed-view-gallery { display: flex; flex-wrap: wrap; gap: 1rem; }
    .jed-view-card {
        flex: 0 0 160px; max-width: 160px;
        border: 1px solid var(--bs-border-color, #dee2e6); border-radius: .375rem;
        padding: .5rem; text-align: center;
    }
    .jed-view-thumb { width: 100%; height: 100px; object-fit: cover; border-radius: .25rem; display: block; }
    .jed-view-filename { display: block; font-size: .75rem; word-break: break-word; margin-top: .25rem; }
</style>

<div class="main-card">
    <h2><?php echo $this->escape($this->item->name ?? ''); ?></h2>

    <?php
    $fieldsets          = $this->form->getFieldsets();
    $firstFieldsetName  = reset($fieldsets) ? reset($fieldsets)->name : '';

    echo HTMLHelper::_('uitab.startTabSet', 'extensionViewTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]);

    foreach ($fieldsets as $fieldset) :
        echo HTMLHelper::_('uitab.addTab', 'extensionViewTab', $fieldset->name, Text::_($fieldset->label));
        ?>
        <div class="row">
            <div class="col-12 col-lg-8">
                <?php foreach ($this->form->getFieldset($fieldset->name) as $field) :
                    if (
                        in_array(strtolower((string) $field->type), $jedSkipFieldsetTypes, true)
                        || in_array($field->fieldname, $jedSkipFieldsetFields, true)
                    ) {
                        continue;
                    }

                    $fieldValue = $this->item->{$field->fieldname} ?? null;
                    ?>
                    <div class="row jed-view-row">
                        <dt class="col-sm-4 col-lg-3"><?php echo $field->label; ?></dt>
                        <dd class="col-sm-8 col-lg-9"><?php echo jedDisplayValue($field->fieldname, $fieldValue); ?></dd>
                    </div>
                <?php endforeach; ?>

                <?php if ($fieldset->name === 'media') : ?>
                    <div class="jed-view-gallery mt-3">
                        <?php foreach ($this->images as $image) : ?>
                            <div class="jed-view-card">
                                <img src="<?php echo $this->escape(JedHelper::formatImage((string) $image->filename, ImageSize::SMALL)); ?>" alt="" class="jed-view-thumb" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($this->images)) : ?>
                            <p class="text-muted"><?php echo Text::_('COM_JED_EXTENSION_NO_IMAGES'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($fieldset->name === 'files') : ?>
                    <ul class="list-unstyled mt-3">
                        <?php foreach ($this->files as $file) : ?>
                            <li><span class="icon-file-alt" aria-hidden="true"></span>
                                <?php echo $this->escape($file->originalFile ?: $file->file); ?></li>
                        <?php endforeach; ?>
                        <?php if (empty($this->files)) : ?>
                            <li class="text-muted">&#8212;</li>
                        <?php endif; ?>
                    </ul>
                <?php elseif ($fieldset->name === 'maintainers') : ?>
                    <ul class="list-unstyled mt-3">
                        <?php foreach ($this->maintainers as $maintainer) : ?>
                            <li><?php echo $this->escape($maintainer->name ?: $maintainer->username); ?></li>
                        <?php endforeach; ?>
                        <?php if (empty($this->maintainers)) : ?>
                            <li class="text-muted">&#8212;</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($fieldset->name === $firstFieldsetName && !empty($this->categories)) : ?>
                    <div class="row jed-view-row">
                        <dt class="col-sm-4 col-lg-3"><?php echo Text::_('COM_JED_EXTENSION_CATEGORIES_LABEL'); ?></dt>
                        <dd class="col-sm-8 col-lg-9">
                            <?php echo implode(', ', array_map(fn ($c) => $this->escape($c->title), $this->categories)); ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        echo HTMLHelper::_('uitab.endTab');
    endforeach;

    echo HTMLHelper::_('uitab.endTabSet');
    ?>
</div>
