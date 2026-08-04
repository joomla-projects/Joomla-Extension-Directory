<?php

/**
 * @package    Jed\Component\Jed\Administrator\Traits
 * @subpackage
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Traits;

use Jed\Component\Jed\Administrator\MediaHandling\ImagePipeline;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Service\Category;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Throwable;

/**
 * Utilities for working with extensions and extension categories
 *
 * @since 4.0.0
 */
trait ExtensionUtilities
{
    /**
     * Filename extensions accepted for an uploaded extension package.
     *
     * @since 4.1.0
     */
    private const PACKAGE_EXTENSIONS = ['zip', 'tar', 'gz', 'tgz', 'bz2'];

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
     * Get the Developer Name from the user's "developer_name" custom field
     *
     * @since 4.0.0
     */
    public function getDeveloperName(int $uid): string
    {
        $db      = $this->getDatabase();
        $context = 'com_users.user';
        $name    = 'developer_name';
        $itemId  = (string) $uid;

        $query = $db->getQuery(true)
            ->select($db->quoteName('v.value'))
            ->from($db->quoteName('#__fields', 'f'))
            ->join('INNER', $db->quoteName('#__fields_values', 'v') . ' ON ' . $db->quoteName('v.field_id') . ' = ' . $db->quoteName('f.id'))
            ->where($db->quoteName('f.context') . ' = :context')
            ->where($db->quoteName('f.name') . ' = :name')
            ->where($db->quoteName('v.item_id') . ' = :uid')
            ->bind(':context', $context)
            ->bind(':name', $name)
            ->bind(':uid', $itemId, ParameterType::STRING);

        return (string) $db->setQuery($query)->loadResult();
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
     * Apply the Joomla tags picked on the extension form to the live #__jed_extensions row.
     *
     * Like categories/maintainers, tags are treated as live metadata rather than part of the
     * pending review: they're written straight to the live extension record's Joomla tag mapping
     * on every save, regardless of the history/approval workflow that gates the rest of the
     * extension's content. The actual tag mapping (#__contentitem_tag_map) is handled by core's
     * "Taggable" behaviour plugin, triggered by ExtensionTable::store() below - see
     * ExtensionTable's TaggableTableInterface and the "com_jed.extension" row script.php ensures
     * exists in #__content_types.
     *
     * @param int   $extensionId The extension PK in #__jed_extensions.
     * @param array $tags        The tag ids (and/or "#new#..." labels for new tags) from the form.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function storeTags(int $extensionId, array $tags): void
    {
        $table = Factory::getApplication()->bootComponent('com_jed')
            ->getMVCFactory()->createTable('Extension', 'Administrator');
        $table->setUseExceptions(true);

        if (!$table->load($extensionId)) {
            return;
        }

        $table->newTags = $tags;
        $table->store();
    }

    /**
     * Delete extension images/files that were marked for removal on the edit form.
     *
     * The stored files go with the rows. Leaving them behind was not merely untidy: an image
     * removed from a listing stayed readable at its URL forever, which is the wrong answer for
     * a screenshot a developer took down on purpose.
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

        $db     = $this->getDatabase();
        $column = $table === '#__jed_extensions_images' ? 'filename' : 'file';

        $paths = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName($column))
                ->from($db->quoteName($table))
                ->where($db->quoteName('extension_id') . ' = ' . $extensionId)
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')')
        )->loadColumn();

        $query = $db->getQuery(true)
            ->delete($db->quoteName($table))
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId)
            ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

        $db->setQuery($query)->execute();

        $this->deleteStoredUploads((array) $paths, $table === '#__jed_extensions_images');
    }

    /**
     * Remove stored upload files from disk.
     *
     * Only paths this installation owns are touched - a bare JED3 filename refers to a file on
     * the legacy CDN that is not ours to delete, and ImagePipeline refuses it for that reason.
     *
     * @param string[] $paths    Stored paths below the site root.
     * @param bool     $isImage  Whether the paths are images, which also have variants on disk.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function deleteStoredUploads(array $paths, bool $isImage): void
    {
        $pipeline = new ImagePipeline();

        foreach ($paths as $path) {
            $path = (string) $path;

            if ($path === '' || !str_contains($path, '/')) {
                continue;
            }

            if ($isImage) {
                $pipeline->delete($path);

                continue;
            }

            $absolute = Path::clean(JPATH_ROOT . '/' . ltrim($path, '/\\'));

            if (str_starts_with($absolute, Path::clean(JPATH_ROOT)) && is_file($absolute)) {
                @unlink($absolute);
            }
        }
    }

    /**
     * Delete every stored image and package of an extension, rows and files.
     *
     * This is the hard deletion, for the privacy removal in `P1-18`. It is deliberately not
     * wired to the soft delete in `P1-01`: a soft-deleted listing is still readable in the
     * backend, and a backend record whose screenshots 404 is a worse record.
     *
     * @param int $extensionId The extension ID.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function purgeExtensionUploads(int $extensionId): void
    {
        $db = $this->getDatabase();

        foreach (['#__jed_extensions_images' => 'filename', '#__jed_extensions_files' => 'file'] as $table => $column) {
            $paths = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName($column))
                    ->from($db->quoteName($table))
                    ->where($db->quoteName('extension_id') . ' = ' . $extensionId)
            )->loadColumn();

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->where($db->quoteName('extension_id') . ' = ' . $extensionId)
            )->execute();

            $this->deleteStoredUploads((array) $paths, $table === '#__jed_extensions_images');
        }

        $directory = Path::clean(JPATH_ROOT . '/images/jed_extensions/' . $extensionId);

        if (Folder::exists($directory)) {
            Folder::delete($directory);
        }
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

            $storedName = $this->moveUploadedExtensionImage($upload, $extensionId);

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

            $storedName = $this->moveUploadedExtensionPackage($upload, $extensionId);

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
     * Pull a single uploaded file's info out of the request for a subform field,
     * e.g. jform[images][images3][filename].
     *
     * Input::files->get() does not hand back the PHP $_FILES layout. $_FILES groups by property
     * first and by field path second - $_FILES['jform']['error']['images']['images3']['filename']
     * - while Joomla's Files input decodes that into one array per file, keyed by the field path:
     * $files['images']['images3']['filename']['error']. Reading it the $_FILES way finds nothing,
     * always, which is why no image or package upload has ever been stored.
     *
     * @param array  $files   The decoded structure from Input::files->get('jform', [], 'raw')
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
        $upload = $files[$subform][$rowKey][$field] ?? null;

        if (!\is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = (string) ($upload['tmp_name'] ?? '');

        // The provenance gate. Everything downstream treats tmp_name as a path it may read and
        // re-encode, so this is the one place that has to establish PHP put the file there.
        // Joomla's Files input applies no safety check of its own, filter argument or not.
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return null;
        }

        return [
            'name'     => (string) ($upload['name'] ?? ''),
            'tmp_name' => $tmpName,
            'size'     => (int) ($upload['size'] ?? 0),
        ];
    }

    /**
     * Store an uploaded image for an extension, with every variant.
     *
     * The name the browser sent is used for nothing but the log: the stored name is derived
     * from the extension id and a timestamp, and the extension comes from the detected image
     * type. That closes the hole the previous version left open - it kept the client's
     * extension, so a PHP script uploaded as "shell.php" landed executable under the web root.
     *
     * @param array  $upload      Array with name/tmp_name/size (see extractUploadedFile())
     * @param int    $extensionId The extension ID the file belongs to
     *
     * @return string|null The stored path below the site root, or null when the upload was rejected
     *
     * @since 4.0.0
     */
    private function moveUploadedExtensionImage(array $upload, int $extensionId): ?string
    {
        if (empty($upload['tmp_name']) || empty($upload['name']) || $upload['size'] <= 0) {
            return null;
        }

        $relativeDirectory = 'images/jed_extensions/' . $extensionId . '/images';

        try {
            $filename = (new ImagePipeline())->store(
                $upload,
                JPATH_ROOT . '/' . $relativeDirectory,
                time() . '-' . bin2hex(random_bytes(4))
            );
        } catch (Throwable $e) {
            // One unusable screenshot must not throw away the rest of the developer's edit,
            // so the row is skipped and the reason is shown rather than swallowed.
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

            return null;
        }

        return $relativeDirectory . '/' . $filename;
    }

    /**
     * Store an uploaded extension package for an extension.
     *
     * Packages are not images and do not go through ImagePipeline, but they land in the same
     * web-readable tree, so the extension is checked against an allowlist rather than taken
     * from the upload. Anything else is refused.
     *
     * @param array $upload      Array with name/tmp_name/size (see extractUploadedFile())
     * @param int   $extensionId The extension ID the file belongs to
     *
     * @return string|null The stored path below the site root, or null when the upload was rejected
     *
     * @since 4.0.0
     */
    private function moveUploadedExtensionPackage(array $upload, int $extensionId): ?string
    {
        if (empty($upload['tmp_name']) || empty($upload['name']) || $upload['size'] <= 0) {
            return null;
        }

        $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));

        if (!\in_array($extension, self::PACKAGE_EXTENSIONS, true)) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf(
                    'COM_JED_FILE_ERROR_NOT_A_PACKAGE',
                    htmlspecialchars((string) $upload['name'], ENT_QUOTES, 'UTF-8'),
                    implode(', ', self::PACKAGE_EXTENSIONS)
                ),
                'warning'
            );

            return null;
        }

        $relativeDirectory = 'images/jed_extensions/' . $extensionId . '/files';
        $directory         = JPATH_ROOT . '/' . $relativeDirectory;

        if (!Folder::exists($directory) && !Folder::create($directory)) {
            return null;
        }

        $target = Path::clean($directory . '/' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension);

        if (!File::upload($upload['tmp_name'], $target)) {
            return null;
        }

        return $relativeDirectory . '/' . basename($target);
    }
}
