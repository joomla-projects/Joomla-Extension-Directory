<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Helper;

use Joomla\Database\DatabaseInterface;
use stdClass;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The `com_jed.extension` row in `#__content_types`.
 *
 * Core's Tags feature is driven entirely from this row: the "Taggable" behaviour plugin looks the
 * type alias up here to find the table class, and `TagsHelper::getTagItemsQuery()` INNER JOINs
 * `#__content_types` and `#__ucm_content` to build a tag listing page. Without the row an extension
 * cannot be tagged, and with the row but no `#__ucm_content` rows the tag pages render empty.
 *
 * The definition lives here rather than in `script.php` because it has two callers: the installer
 * writes it on every install/update, and the JED3 migration needs it in place before it can write
 * `#__contentitem_tag_map` (`P1-16`). One writer, one definition - the alternative was the same
 * 40 lines of JSON in two files, drifting apart on the first change.
 *
 * @since 4.1.0
 */
class ContentTypeHelper
{
    /**
     * The type alias `#__jed_extensions` is tagged under.
     *
     * @var   string
     *
     * @since 4.1.0
     */
    public const EXTENSION_TYPE_ALIAS = 'com_jed.extension';

    /**
     * Register the `com_jed.extension` content type, or bring an existing row up to date.
     *
     * @param   DatabaseInterface  $db  The database to write to.
     *
     * @return  integer  The `type_id` of the row.
     *
     * @since   4.1.0
     */
    public static function ensureExtensionContentType(DatabaseInterface $db): int
    {
        $alias = self::EXTENSION_TYPE_ALIAS;

        $query = $db->getQuery(true)
            ->select($db->quoteName('type_id'))
            ->from($db->quoteName('#__content_types'))
            ->where($db->quoteName('type_alias') . ' = :alias')
            ->bind(':alias', $alias);

        $typeId = (int) $db->setQuery($query)->loadResult();

        // These two mirror the exact shape Joomla's own content types use (see #__content_types
        // rows for e.g. com_content.article/com_contact.contact) - "table" describes the
        // component's own table class plus the generic Corecontent table used to store the UCM
        // row; "field_mappings" maps #__jed_extensions columns onto the generic "core_*" UCM
        // fields (unmapped ones are "null" - #__jed_extensions has no equivalent).
        $table = [
            'special' => [
                'dbtable' => '#__jed_extensions',
                'key'     => 'id',
                'type'    => 'ExtensionTable',
                'prefix'  => 'Jed\\Component\\Jed\\Administrator\\Table\\',
                'config'  => 'array()',
            ],
            'common' => [
                'dbtable' => '#__ucm_content',
                'key'     => 'ucm_id',
                'type'    => 'Corecontent',
                'prefix'  => 'Joomla\\CMS\\Table\\',
                'config'  => 'array()',
            ],
        ];

        $fieldMappings = [
            'common' => [
                'core_content_item_id' => 'id',
                'core_title'           => 'name',
                'core_state'           => 'state',
                'core_alias'           => 'alias',
                'core_created_time'    => 'created',
                'core_modified_time'   => 'modified',
                'core_body'            => 'description',
                'core_hits'            => 'null',
                'core_publish_up'      => 'null',
                'core_publish_down'    => 'null',
                'core_access'          => 'null',
                'core_params'          => 'null',
                'core_featured'        => 'null',
                'core_metadata'        => 'null',
                'core_language'        => 'null',
                'core_images'          => 'logo',
                'core_urls'            => 'null',
                'core_version'         => 'null',
                'core_ordering'        => 'null',
                'core_metakey'         => 'null',
                'core_metadesc'        => 'null',
                'core_catid'           => 'catid',
                'asset_id'             => 'null',
            ],
            'special' => new stdClass(),
        ];

        $row = (object) [
            'type_title'              => 'Extension',
            'type_alias'              => $alias,
            'table'                   => json_encode($table),
            'rules'                   => '',
            'field_mappings'          => json_encode($fieldMappings),
            'router'                  => 'Jed\\Component\\Jed\\Site\\Helper\\RouteHelper::getArticleRoute',
            'content_history_options' => '',
        ];

        if ($typeId) {
            $row->type_id = $typeId;
            $db->updateObject('#__content_types', $row, 'type_id');

            return $typeId;
        }

        $db->insertObject('#__content_types', $row, 'type_id');

        return (int) $row->type_id;
    }
}
