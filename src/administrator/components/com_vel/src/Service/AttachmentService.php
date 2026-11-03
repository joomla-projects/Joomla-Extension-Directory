<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Service;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use RuntimeException;

/**
 * Proof-of-concept files and other attachments (VEL2-PRD §6.1.8).
 *
 * Stored outside the web root under a random name, served only through a controller that has
 * checked `vel.communicate` or a participant token. The original filename is kept in the row
 * for display and for the download header, never on disk.
 *
 * @since 4.0.0
 */
class AttachmentService
{
    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @return string  The storage folder, created if missing.
     *
     * @throws RuntimeException  Not writable.
     *
     * @since 4.0.0
     */
    public function folder(): string
    {
        $configured = trim((string) ComponentHelper::getParams('com_vel')->get('attachment_path', ''));
        $path       = $configured !== '' ? rtrim($configured, '/\\') : \dirname(JPATH_ROOT) . '/vel_private';

        if (!is_dir($path)) {
            Folder::create($path, 0750);
        }

        if (!is_dir($path) || !is_writable($path)) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_ATTACHMENT_FOLDER', $path));
        }

        if (!is_file($path . '/.htaccess')) {
            @file_put_contents($path . '/.htaccess', "Require all denied\nDeny from all\n");
        }

        return $path;
    }

    /**
     * @return string[]  Allowed extensions, lower-cased.
     *
     * @since 4.0.0
     */
    public function allowedTypes(): array
    {
        $types = (string) ComponentHelper::getParams('com_vel')->get('attachment_types', 'zip,txt,md,log,png,jpg,jpeg,gif,pdf,asc,gpg,json,xml,php,html,htm');

        return array_values(array_filter(array_map(static fn (string $t): string => strtolower(trim($t, " .\t")), explode(',', $types))));
    }

    /**
     * @return int  Bytes.
     *
     * @since 4.0.0
     */
    public function maxBytes(): int
    {
        return max(1, (int) ComponentHelper::getParams('com_vel')->get('attachment_max_mb', 10)) * 1024 * 1024;
    }

    /**
     * Store an uploaded file (a $_FILES-style array) against a message or a report.
     *
     * @param array    $upload    name, tmp_name, size, error.
     * @param int|null $messageId The message, or null.
     * @param int|null $reportId  The report, or null.
     * @param int      $actorId   Who uploaded it (0 for a guest).
     *
     * @return int  The attachment id, or 0 when there was no file.
     *
     * @throws RuntimeException  Refused.
     *
     * @since 4.0.0
     */
    public function storeUpload(array $upload, ?int $messageId, ?int $reportId, int $actorId): int
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE || empty($upload['tmp_name'])) {
            return 0;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_ATTACHMENT_UPLOAD'));
        }

        $name = File::makeSafe(basename((string) ($upload['name'] ?? 'file')));
        $size = (int) ($upload['size'] ?? 0);

        if ($size <= 0 || $size > $this->maxBytes()) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_ATTACHMENT_SIZE', (int) ComponentHelper::getParams('com_vel')->get('attachment_max_mb', 10)));
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($extension === '' || !\in_array($extension, $this->allowedTypes(), true)) {
            throw new RuntimeException(Text::sprintf('COM_VEL_ERROR_ATTACHMENT_TYPE', $extension));
        }

        return $this->storeContents((string) file_get_contents((string) $upload['tmp_name']), $name, $messageId, $reportId, $actorId, true, (string) $upload['tmp_name']);
    }

    /**
     * Store raw contents (an e-mail attachment).
     *
     * @param string      $contents  The bytes.
     * @param string      $name      The original filename.
     * @param int|null    $messageId The message.
     * @param int|null    $reportId  The report.
     * @param int         $actorId   Who.
     * @param bool        $isUpload  Whether $tmpPath is an uploaded file to move.
     * @param string|null $tmpPath   The uploaded file's temp path.
     *
     * @return int  The attachment id.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    public function storeContents(string $contents, string $name, ?int $messageId, ?int $reportId, int $actorId, bool $isUpload = false, ?string $tmpPath = null): int
    {
        $folder = $this->folder();
        $name   = File::makeSafe(basename($name)) ?: 'attachment';
        $stored = bin2hex(random_bytes(16)) . '.bin';
        $target = $folder . '/' . $stored;

        if ($isUpload && $tmpPath !== null) {
            if (!File::upload($tmpPath, $target, false, true)) {
                throw new RuntimeException(Text::_('COM_VEL_ERROR_ATTACHMENT_UPLOAD'));
            }
        } elseif (file_put_contents($target, $contents) === false) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_ATTACHMENT_UPLOAD'));
        }

        @chmod($target, 0640);

        $mime = 'application/octet-stream';

        if (\function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $detected = finfo_file($finfo, $target);
                finfo_close($finfo);

                if (\is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
        }

        $row = (object) [
            'message_id'  => $messageId,
            'report_id'   => $reportId,
            'filename'    => mb_substr($name, 0, 255),
            'stored_name' => $stored,
            'mime'        => mb_substr($mime, 0, 128),
            'size'        => (int) filesize($target),
            'sha256'      => hash_file('sha256', $target) ?: '',
            'created'     => Factory::getDate()->toSql(),
            'created_by'  => $actorId,
        ];

        $this->db->insertObject('#__vel_attachments', $row, 'id');

        return (int) $row->id;
    }

    /**
     * @param int $id The attachment.
     *
     * @return object|null
     *
     * @since 4.0.0
     */
    public function find(int $id): ?object
    {
        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_attachments'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * @param int[] $messageIds Messages.
     * @param int[] $reportIds  Reports.
     *
     * @return object[]
     *
     * @since 4.0.0
     */
    public function forThread(array $messageIds, array $reportIds): array
    {
        $messageIds = array_values(array_filter(array_map('intval', $messageIds)));
        $reportIds  = array_values(array_filter(array_map('intval', $reportIds)));

        if ($messageIds === [] && $reportIds === []) {
            return [];
        }

        $query  = $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__vel_attachments'));
        $clause = [];

        if ($messageIds !== []) {
            $clause[] = $this->db->quoteName('message_id') . ' IN (' . implode(',', $messageIds) . ')';
        }

        if ($reportIds !== []) {
            $clause[] = $this->db->quoteName('report_id') . ' IN (' . implode(',', $reportIds) . ')';
        }

        $query->where('(' . implode(' OR ', $clause) . ')')->order($this->db->quoteName('created') . ' ASC');

        return $this->db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @param object $attachment The row.
     *
     * @return string  The absolute path.
     *
     * @since 4.0.0
     */
    public function path(object $attachment): string
    {
        return $this->folder() . '/' . basename((string) $attachment->stored_name);
    }

    /**
     * Delete the file and the row.
     *
     * @param int $id The attachment.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function delete(int $id): void
    {
        $attachment = $this->find($id);

        if ($attachment === null) {
            return;
        }

        $path = $this->path($attachment);

        if (is_file($path)) {
            @unlink($path);
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__vel_attachments'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Stream a file to the browser, as a download, never inline - an HTML proof of concept
     * rendered inline would be the XSS it describes.
     *
     * @param object $attachment The row.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function send(object $attachment): void
    {
        $path = $this->path($attachment);

        if (!is_file($path)) {
            throw new RuntimeException(Text::_('COM_VEL_ERROR_ATTACHMENT_MISSING'));
        }

        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/octet-stream', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . str_replace('"', '', (string) $attachment->filename) . '"', true);
        $app->setHeader('Content-Length', (string) filesize($path), true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $app->setHeader('Cache-Control', 'private, no-store', true);
        $app->sendHeaders();

        readfile($path);

        $app->close();
    }
}
