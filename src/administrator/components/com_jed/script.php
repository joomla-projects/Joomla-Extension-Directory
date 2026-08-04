<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Component\Fields\Administrator\Table\FieldTable;
use Joomla\Database\DatabaseInterface;

/**
 * Script file of JED Component
 *
 * @since 4.0.0
 */
class Com_JedInstallerScript
{
    /**
     * Minimum Joomla version to check
     *
     * @var   string
     * @since 4.0.0
     */
    private string $minimumJoomlaVersion = '4.0';

    /**
     * Minimum PHP version to check
     *
     * @var   string
     * @since 4.0.0
     */
    private string $minimumPHPVersion = '8.1.0';

    /**
     * Method to install the extension
     *
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 4.0.0
     */
    public function install(InstallerAdapter $parent): bool
    {
        echo Text::_('COM_JED_INSTALLERSCRIPT_INSTALL');

        return true;
    }

    /**
     * Function called after extension installation/update/removal procedure commences
     *
     * @param string           $type   The type of change (install, update or discover_install, not uninstall)
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 4.0.0
     */
    public function postflight(
        string $type,
        InstallerAdapter $parent
    ): bool {
        $this->addUserCustomFields();
        $this->ensureExtensionContentType();

        echo Text::_('COM_JED_INSTALLERSCRIPT_POSTFLIGHT');

        return true;
    }

    /**
     * Registers the "com_jed.extension" content type in #__content_types, if it isn't there
     * already. This is what lets #__jed_extensions support Joomla's core Tags feature: the
     * "Taggable" behaviour plugin (always active, part of the standard "behaviour" plugin group)
     * looks the type alias up here to store/retrieve tag mappings for a table, and tagging an item
     * always upserts a matching #__ucm_content/#__ucm_base row, which needs to know how to map
     * #__jed_extensions' own columns onto the generic set of "core_*" fields it stores.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function ensureExtensionContentType(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $alias = 'com_jed.extension';

        $query = $db->getQuery(true)
            ->select($db->quoteName('type_id'))
            ->from($db->quoteName('#__content_types'))
            ->where($db->quoteName('type_alias') . ' = :alias')
            ->bind(':alias', $alias);

        $typeId = $db->setQuery($query)->loadResult();

        // These two mirror the exact shape Joomla's own content types use (see
        // #__content_types rows for e.g. com_content.article/com_contact.contact) - "table"
        // describes the component's own table class plus the generic Corecontent table used to
        // store the UCM row; "field_mappings" maps #__jed_extensions columns onto the generic
        // "core_*" UCM fields (unmapped ones are "null" - #__jed_extensions has no equivalent).
        $table = [
            'special' => [
                'dbtable' => '#__jed_extensions',
                'key'     => 'id',
                'type'    => 'ExtensionTable',
                'prefix'  => 'Jed\\Component\\Jed\\Administrator\\Table\\',
                'config'  => 'array()',
            ],
            'common'  => [
                'dbtable' => '#__ucm_content',
                'key'     => 'ucm_id',
                'type'    => 'Corecontent',
                'prefix'  => 'Joomla\\CMS\\Table\\',
                'config'  => 'array()',
            ],
        ];

        $fieldMappings = [
            'common'  => [
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
            $row->type_id = (int) $typeId;
            $db->updateObject('#__content_types', $row, 'type_id');

            return;
        }

        $db->insertObject('#__content_types', $row);
    }

    /**
     * Adds the "developer_name" and "suspicious" custom fields to the user (com_users.user)
     * context, if they don't already exist.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function addUserCustomFields(): void
    {
        $this->addCustomFieldIfMissing([
            'context'       => 'com_users.user',
            'title'         => 'Developer Name',
            'name'          => 'developer_name',
            'label'         => 'Developer Name',
            'description'   => 'The name of the developer of the extension.',
            'type'          => 'text',
            'default_value' => '',
            'state'         => 1,
            'access'        => 1,
            'required'      => 0,
            'language'      => '*',
            'params'        => [
                // Editable in both site and administrator.
                'show_on' => '',
            ],
        ]);

        $this->addCustomFieldIfMissing([
            'context'       => 'com_users.user',
            'title'         => 'Suspicious',
            'name'          => 'suspicious',
            'label'         => 'Suspicious',
            'description'   => 'Whether the extension is suspicious.',
            'type'          => 'checkboxes',
            'default_value' => '',
            'state'         => 1,
            'access'        => 1,
            'required'      => 0,
            'language'      => '*',
            'params'        => [
                // Editable in the administrator only, i.e. not editable on the frontend.
                'show_on' => '2',
            ],
            'fieldparams'   => [
                'options' => [
                    ['name' => 'JYES', 'value' => '1'],
                ],
            ],
        ]);
    }

    /**
     * Creates the given custom field unless a field with the same context and name already exists.
     *
     * @param array $data The field data (see #__fields columns)
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function addCustomFieldIfMissing(array $data): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context')
            ->where($db->quoteName('name') . ' = :name')
            ->bind(':context', $data['context'])
            ->bind(':name', $data['name']);

        if ($db->setQuery($query)->loadResult()) {
            return;
        }

        $table = new FieldTable($db);
        $table->setUseExceptions(true);

        try {
            $table->bind($data);
            $table->check();
        } catch (\Exception $e) {
            Log::add(
                sprintf('Could not create the "%s" custom field: %s', $data['name'], $e->getMessage()),
                Log::WARNING,
                'jerror'
            );

            return;
        }

        // FieldTable::check() runs the name through a URL-safe filter, which turns underscores
        // into dashes. Restore the exact name we were asked to create.
        $table->name = $data['name'];

        try {
            $table->store();
        } catch (\Exception $e) {
            Log::add(
                sprintf('Could not create the "%s" custom field: %s', $data['name'], $e->getMessage()),
                Log::WARNING,
                'jerror'
            );
        }
    }

    /**
     * Function called before extension installation/update/removal procedure commences
     *
     * @param string           $type   The type of change (install, update or discover_install, not uninstall)
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function preflight(
        string $type,
        InstallerAdapter $parent
    ): bool {
        if ($type !== 'uninstall') {
            // Check for the minimum PHP version before continuing
            if (!empty($this->minimumPHPVersion) && version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
                Log::add(
                    Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion),
                    Log::WARNING,
                    'jerror'
                );

                return false;
            }

            // Check for the minimum Joomla version before continuing
            if (!empty($this->minimumJoomlaVersion) && version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
                Log::add(
                    Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                    Log::WARNING,
                    'jerror'
                );

                return false;
            }
        }

        echo Text::_('COM_JED_INSTALLERSCRIPT_PREFLIGHT');

        return true;
    }

    /**
     * Method to uninstall the extension
     *
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 4.0.0
     */
    public function uninstall(
        InstallerAdapter $parent
    ): bool {
        echo Text::_('COM_JED_INSTALLERSCRIPT_UNINSTALL');

        return true;
    }

    /**
     * Method to update the extension
     *
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 4.0.0
     */
    public function update(
        InstallerAdapter $parent
    ): bool {
        echo Text::_('COM_JED_INSTALLERSCRIPT_UPDATE');

        return true;
    }
}
