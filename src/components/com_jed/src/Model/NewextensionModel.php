<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Parser\File as FileParser;
use Jed\Component\Jed\Administrator\Parser\Github as GithubParser;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use RuntimeException;

/**
 * Model backing the "create a new extension" wizard (view "newextension").
 *
 * Layout "default": parseUploadedFile()/parseGithubUrl() detect the extension's data from an
 * uploaded package or a GitHub repository and stash it in the session.
 * Layout "form": getForm()/loadFormData() render forms/extensionform.xml pre-filled from that
 * session data; save() creates the #__jed_extensions row and its first #__jed_extensions_history
 * entry, analogous to the admin backend's ExtensionModel::save()/createExtension().
 *
 * @since 1.0.0
 */
class NewextensionModel extends FormModel
{
    use ExtensionUtilities;

    /**
     * The session key the detected manifest data is stored under between layout=default and
     * layout=form.
     *
     * @since 1.0.0
     */
    public const SESSION_KEY = 'com_jed.newextension.data';

    /**
     * Maps a Joomla manifest's <extension type="..."> (or package <files><file type="...">)
     * values onto JED's own "extension_types" taxonomy used by forms/extensionform.xml.
     *
     * @since 1.0.0
     */
    private const JOOMLA_TYPE_MAP = [
        'component' => 'com',
        'module'    => 'mod',
        'plugin'    => 'plugin',
    ];

    /**
     * Method to get the extension form.
     *
     * Reuses the same forms/extensionform.xml as the (edit-only) extensionform view.
     *
     * @param array $data     An optional array of data for the form to interrogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form
     *
     * @since  1.0.0
     * @throws Exception
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form
    {
        $form = $this->loadForm(
            'com_jed.newextension',
            'extensionform',
            [
                'control'   => $formname,
                'load_data' => $loadData,
            ]
        );

        if (!is_object($form)) {
            throw new Exception(Text::_('JERROR_LOADFILE_FAILED'), 500);
        }

        return $form;
    }

    protected function preprocessForm(Form $form, $data, $group = 'content')
    {
        $user = $this->getCurrentUser();
        $form->setValue('owner', $user->id);
    }

    /**
     * Method to get the data that should be injected in the form: whatever was detected from the
     * uploaded package or GitHub repository in step 1, mapped onto forms/extensionform.xml field
     * names.
     *
     * @return array
     *
     * @since  1.0.0
     */
    protected function loadFormData(): array
    {
        $detected = (array) Factory::getApplication()->getUserState(self::SESSION_KEY, []);

        if (empty($detected['data'])) {
            return [];
        }

        return (array) $detected['data'];
    }

    /**
     * Method to get the table.
     *
     * @param string $name    Name of the Table class
     * @param string $prefix  Optional prefix for the table class name
     * @param array  $options Optional configuration array for the Table object
     *
     * @return Table|bool
     *
     * @since  1.0.0
     * @throws Exception
     */
    public function getTable($name = 'ExtensionHistory', $prefix = 'Administrator', $options = []): Table|bool
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Creates the new extension: a #__jed_extensions row, followed by its first
     * #__jed_extensions_history entry (mirrors the admin backend's
     * ExtensionModel::save()/createExtension()).
     *
     * @param array $data The validated form data
     *
     * @return int The new extension's #__jed_extensions.id, or 0 on failure (errors are set on the model)
     *
     * @since  1.0.0
     * @throws Exception
     */
    public function save(array $data): int
    {
        if (!JedHelper::isLoggedIn()) {
            throw new Exception(Text::_('COM_JED_EXTENSION_NO_ACCESS_LABEL'), 401);
        }

        if (!isset($data['extension_id']) && !$data['extension_id']) {
            $extensionId = $this->createExtension($data);
        } else {
            $extensionId = (int) $data['extension_id'];
        }

        Factory::getApplication()->setUserState('com_jed.edit.extension.id', $extensionId);

        if (!$extensionId) {
            return 0;
        }

        $categories = (array) ($data['categories'] ?? []);

        $data['id']           = 0;
        $data['extension_id'] = $extensionId;
        $data['active']       = 1;
        unset($data['created']);

        $table = $this->getTable();

        if (!$table->bind($data) || !$table->check() || !$table->store()) {
            $this->setError($table->getError());

            return 0;
        }

        $rawPost = (array) Factory::getApplication()->getInput()->post->get('jform', [], 'array');

        $this->storeCategories($extensionId, $categories);
        $this->storeMaintainers($extensionId, (array) ($data['maintainer'] ?? []));
        $this->deleteMarkedUploads($extensionId, (array) ($rawPost['deleteImages'] ?? []), '#__jed_extensions_images');
        $this->deleteMarkedUploads($extensionId, (array) ($rawPost['deleteFiles'] ?? []), '#__jed_extensions_files');
        $this->storeUploadedImages($extensionId, (array) ($data['images'] ?? []));
        $this->storeUploadedFiles($extensionId, (array) ($data['files'] ?? []));

        return $extensionId;
    }

    /**
     * Creates the live #__jed_extensions row for a brand new extension.
     *
     * @param array $data The submitted form data.
     *
     * @return int The new #__jed_extensions.id, or 0 on failure (an error is set on the model).
     *
     * @since 1.0.0
     */
    private function createExtension(array $data): int
    {
        $user = Factory::getApplication()->getIdentity();

        /** @var \Jed\Component\Jed\Administrator\Table\ExtensionTable $table */
        $table = $this->getTable('Extension');

        $liveData = [
            'id'    => 0,
            'name'  => (string) ($data['name'] ?? ''),
            'alias' => (string) ($data['alias'] ?? ''),
            'catid' => !empty($data['catid']) ? (int) $data['catid'] : null,
            'owner' => (int) $user->id,
            'state' => 0,
        ];

        if (!$table->save($liveData)) {
            $this->setError($table->getError());

            return 0;
        }

        return (int) $table->id;
    }

    /**
     * Detects an extension's data from an uploaded zip file, stores it in the session for the
     * "form" layout to pick up, and returns it for the AJAX caller.
     *
     * @param array $upload A single $_FILES entry (name/tmp_name/error/size)
     *
     * @return array{success: bool, data?: array, message?: string}
     *
     * @since 1.0.0
     */
    public function parseUploadedFile(array $upload): array
    {
        if (empty($upload['tmp_name']) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => Text::_('COM_JED_NEWEXTENSION_UPLOAD_ERROR')];
        }

        try {
            $parser = new FileParser($upload['tmp_name']);

            return $this->detected($parser);
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Detects an extension's data from the latest GitHub release of the given repository URL,
     * stores it in the session for the "form" layout to pick up, and returns it for the AJAX
     * caller.
     *
     * @param string $url A GitHub repository URL, e.g. https://github.com/owner/repo
     *
     * @return array{success: bool, data?: array, message?: string}
     *
     * @since 1.0.0
     */
    public function parseGithubUrl(string $url): array
    {
        if (trim($url) === '') {
            return ['success' => false, 'message' => Text::_('COM_JED_NEWEXTENSION_GIT_URL_REQUIRED')];
        }

        try {
            $parser = new GithubParser($url);

            return $this->detected($parser);
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Builds the normalised, extensionform.xml-shaped data array from a manifest parser, stores
     * it in the session and returns the success envelope.
     *
     * @param FileParser|GithubParser $parser
     *
     * @return array{success: bool, data: array}
     *
     * @since 1.0.0
     */
    private function detected(FileParser|GithubParser $parser): array
    {
        $data = [
            'name'            => $parser->getName(),
            'developer_url'   => $parser->getAuthorUrl(),
            'developer_email' => $parser->getAuthorEmail(),
            'update_url'      => $parser->getUpdateServerUrl(),
            'changelog_url'   => $parser->getChangelogUrl(),
            'extension_types' => $this->mapExtensionTypes($parser->getExtensionTypes()),
        ];

        Factory::getApplication()->setUserState(self::SESSION_KEY, ['data' => $data]);

        return ['success' => true, 'data' => $data];
    }

    /**
     * Maps the raw Joomla manifest extension type(s) (e.g. "component", "module") onto JED's own
     * "extension_types" checkbox values (com/mod/plugin/specific).
     *
     * @param string[] $joomlaTypes
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    private function mapExtensionTypes(array $joomlaTypes): array
    {
        $mapped = [];

        foreach ($joomlaTypes as $joomlaType) {
            $mapped[] = self::JOOMLA_TYPE_MAP[$joomlaType] ?? 'specific';
        }

        return array_values(array_unique($mapped));
    }
}
