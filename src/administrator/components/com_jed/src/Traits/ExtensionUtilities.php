<?php

/**
 * @package    Jed\Component\Jed\Administrator\Traits
 * @subpackage
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Traits;

use Exception;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Service\Category;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Michelf\Markdown;
use SimpleXMLElement;

/**
 * Utilities for working with extensions and extension categories
 *
 * @since 4.0.0
 */
trait ExtensionUtilities
{
    /**
     * Gets first paragraph of description as intro text
     *
     * @param string $d
     *
     * @return array
     *
     * @throws Exception
     * @since  4.0.0
     */
    public function splitDescription(string $d): array
    {
        // Remove images
        $d = preg_replace("/\!\[(.*)\]\((.*)\)/", '', $d);
        // Remove links
        $d = preg_replace("/\[(.*)\]\((.*)\)/", '', (string) $d);
        $d = Markdown::defaultTransform($d);

        $clean = (stripslashes(trim($d)));
        $xml   = new SimpleXMLElement('<div>' . $clean . '</div>');
        $ps    = $xml->xpath('//p');

        if (count($ps) > 0) {
            $ret['intro'] = htmlspecialchars_decode($ps[0]->asXml());


            if (count($ps) === 1) {
                // No more text (but might contain non-paragraphed text see JDEV-628
                $ret['body'] = str_replace($d, '', $d);
            } else {
                // Remove first paragraph from the text
                $dom = dom_import_simplexml($ps[0]);
                $dom->parentNode->removeChild($dom);
                $ret['body'] = htmlspecialchars_decode(str_replace('<?xml version="1.0"?>', '', $xml->asXml()));
            }
        } else {
            $seperator = stristr($d, '<br>') ? '<br>' : '<br />';
            $bits      = explode($seperator, $d);

            $o            = array_shift($bits);
            $ret['intro'] = $o;
            $ret['body']  = implode('<br />', $bits);
        }

        return $ret;
    }

    /**
     * Gets current extension category and hierarchy of parents as string
     *
     * @param int $category_id
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function getCategoryHierarchy(int $category_id): string
    {
        return LayoutHelper::render(
            'category.hierarchy',
            [
            'categories' => $this->getCategoryHierarchyStack($category_id),
            ]
        );
    }

    /**
     * Get a stack of Category tables with the hierarchy leading to the target category (ordered root towards leaf node)
     *
     * @param int $catId The category ID to search for
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getCategoryHierarchyStack(int $catId): array
    {
        $stack      = [];
        $catService = new Category();
        $rootNode   = $catService->get('root');
        $cat        = $catService->get($catId);

        do {
            if ($cat === null) {
                return $stack;
            }

            array_unshift($stack, $cat);

            $cat = $cat->getParent();
        } while ($cat !== null && $cat->id != $rootNode->id);

        return $stack;
    }

    /**
     * Get Developer Name from jed_developers table
     *
     * @since 4.0.0
     */
    public function getDeveloperName(int $uid): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.developer_name')
            ->from($db->quoteName('#__jed_developers', 'a'))
            ->where('a.user_id = ' . $uid);

        return $db->setQuery($query)->loadResult();
    }

    /**
     * Point #__jed_extensions.entry_version at the history entry that represents the extension's
     * current state.
     *
     * @param int $extensionId The extension PK in #__jed_extensions.
     * @param int $historyId   The #__jed_extensions_history PK to point at.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function updateEntryVersion(int $extensionId, int $historyId): void
    {
        $db = $this->getDatabase();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__jed_extensions'))
                ->set($db->quoteName('entry_version') . ' = :historyId')
                ->where($db->quoteName('id') . ' = :eid')
                ->bind(':historyId', $historyId, ParameterType::INTEGER)
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Store the selected categories for an extension into #__jed_extensions_category_map.
     *
     * @param int   $extensionId The extension ID to save the categories for
     * @param array $categoryIds The category IDs to store
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function storeCategories(int $extensionId, array $categoryIds): void
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)->delete($db->quoteName('#__jed_extensions_category_map'))->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query)->execute();

        $categoryIds = array_filter(array_map('intval', $categoryIds));

        if (empty($categoryIds)) {
            return;
        }

        $query->clear()->insert($db->quoteName('#__jed_extensions_category_map'))->columns(
            $db->quoteName(
                [
                    'extension_id',
                    'catid',
                ]
            )
        );

        array_walk(
            $categoryIds,
            static function ($categoryId) use (&$query, $extensionId) {
                $query->values($extensionId . ',' . $categoryId);
            }
        );

        $db->setQuery($query)->execute();
    }

    /**
     * Store the selected maintainers for an extension into #__jed_extensions_maintainers.
     *
     * @param int   $extensionId The extension ID to save the maintainers for
     * @param array $rows        The "maintainer" subform rows (each with a user_id)
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function storeMaintainers(int $extensionId, array $rows): void
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)->delete($db->quoteName('#__jed_extensions_maintainers'))->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query)->execute();

        $userIds = array_unique(array_filter(array_map(static fn ($row) => (int) ($row['user_id'] ?? 0), $rows)));

        if (empty($userIds)) {
            return;
        }

        $query = $db->getQuery(true)->insert($db->quoteName('#__jed_extensions_maintainers'))->columns(
            $db->quoteName(['extension_id', 'user_id'])
        );

        array_walk(
            $userIds,
            static function ($userId) use (&$query, $extensionId) {
                $query->values($extensionId . ',' . $userId);
            }
        );

        $db->setQuery($query)->execute();
    }

    /**
     * Delete extension images/files that were marked for removal on the edit form.
     *
     * @param int    $extensionId The extension ID the rows must belong to
     * @param array  $ids         The primary keys to delete
     * @param string $table       The table to delete from (#__jed_extensions_images or _files)
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function deleteMarkedUploads(int $extensionId, array $ids, string $table): void
    {
        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            return;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->quoteName($table))
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId)
            ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

        $db->setQuery($query)->execute();
    }

    /**
     * Move newly uploaded images into place and insert them into #__jed_extensions_images.
     *
     * @param int   $extensionId The extension ID
     * @param array $rows        The "images" subform rows, keyed by subform row group (e.g. "images0")
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function storeUploadedImages(int $extensionId, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $files  = (array) Factory::getApplication()->getInput()->files->get('jform', [], 'raw');
        $db     = $this->getDatabase();
        $userId = (int) Factory::getApplication()->getIdentity()->id;

        foreach ($rows as $rowKey => $row) {
            $upload = $this->extractUploadedFile($files, 'images', (string) $rowKey, 'filename');

            if ($upload === null) {
                continue;
            }

            $storedName = $this->moveUploadedExtensionFile($upload, $extensionId, 'images');

            if ($storedName === null) {
                continue;
            }

            $insert = $db->getQuery(true)
                ->insert($db->quoteName('#__jed_extensions_images'))
                ->columns($db->quoteName(['extension_id', 'filename', 'state', 'ordering', 'created_by', 'modified_by']))
                ->values(
                    implode(
                        ', ',
                        [
                            $extensionId,
                            $db->quote($storedName),
                            (int) ($row['state'] ?? 1),
                            (int) ($row['ordering'] ?? 0),
                            $userId,
                            $userId,
                        ]
                    )
                );

            $db->setQuery($insert)->execute();
        }
    }

    /**
     * Move newly uploaded files into place and insert them into #__jed_extensions_files.
     *
     * @param int   $extensionId The extension ID
     * @param array $rows        The "files" subform rows, keyed by subform row group (e.g. "files0")
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function storeUploadedFiles(int $extensionId, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $files  = (array) Factory::getApplication()->getInput()->files->get('jform', [], 'raw');
        $db     = $this->getDatabase();
        $userId = (int) Factory::getApplication()->getIdentity()->id;

        foreach ($rows as $rowKey => $row) {
            $upload = $this->extractUploadedFile($files, 'files', (string) $rowKey, 'file');

            if ($upload === null) {
                continue;
            }

            $storedName = $this->moveUploadedExtensionFile($upload, $extensionId, 'files');

            if ($storedName === null) {
                continue;
            }

            $insert = $db->getQuery(true)
                ->insert($db->quoteName('#__jed_extensions_files'))
                ->columns($db->quoteName(['extension_id', 'file', 'originalFile', 'meta', 'created_by']))
                ->values(
                    implode(
                        ', ',
                        [
                            $extensionId,
                            $db->quote($storedName),
                            $db->quote($upload['name']),
                            $db->quote(''),
                            $userId,
                        ]
                    )
                );

            $db->setQuery($insert)->execute();
        }
    }

    /**
     * Pull a single uploaded file's info out of the reshuffled PHP $_FILES structure for a
     * subform field, e.g. jform[images][images3][filename].
     *
     * @param array  $files   The raw $_FILES['jform'] structure (Input::files->get('jform', [], 'raw'))
     * @param string $subform The subform field name (e.g. "images")
     * @param string $rowKey  The subform row group name (e.g. "images3")
     * @param string $field   The inner file field name (e.g. "filename")
     *
     * @return array|null Array with name/tmp_name/size, or null if no file was uploaded there
     *
     * @since 4.0.0
     */
    private function extractUploadedFile(array $files, string $subform, string $rowKey, string $field): ?array
    {
        $error = $files['error'][$subform][$rowKey][$field] ?? UPLOAD_ERR_NO_FILE;

        if ((int) $error !== UPLOAD_ERR_OK) {
            return null;
        }

        return [
            'name'     => (string) ($files['name'][$subform][$rowKey][$field] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$subform][$rowKey][$field] ?? ''),
            'size'     => (int) ($files['size'][$subform][$rowKey][$field] ?? 0),
        ];
    }

    /**
     * Move an uploaded file from PHP's temporary location into permanent storage for an extension.
     *
     * @param array  $upload      Array with name/tmp_name/size (see extractUploadedFile())
     * @param int    $extensionId The extension ID the file belongs to
     * @param string $kind        Either "images" or "files"
     *
     * @return string|null The stored, web-relative filename, or null on failure
     *
     * @since 4.0.0
     */
    private function moveUploadedExtensionFile(array $upload, int $extensionId, string $kind): ?string
    {
        if (empty($upload['tmp_name']) || empty($upload['name']) || $upload['size'] <= 0) {
            return null;
        }

        $relativeDirectory  = 'images/jed_extensions/' . $extensionId . '/' . $kind;
        $directory          = JPATH_ROOT . '/' . $relativeDirectory;

        if (!Folder::exists($directory) && !Folder::create($directory)) {
            return null;
        }

        $safeName = File::makeSafe($upload['name']);
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', $safeName) ?: 'file';
        $target   = Path::clean($directory . '/' . time() . '-' . $safeName);

        if (!File::upload($upload['tmp_name'], $target)) {
            return null;
        }

        return $relativeDirectory . '/' . basename($target);
    }
}
