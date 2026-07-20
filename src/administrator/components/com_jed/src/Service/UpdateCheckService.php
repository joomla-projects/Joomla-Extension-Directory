<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Queue\QueueService;
use Jed\Component\Jed\Administrator\Update\UpdateServerXmlParser;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Http\Http;
use RuntimeException;
use Throwable;

/**
 * Polls each extension's declared update-server URL and, on finding a newer
 * version, applies it directly to the live row (via {@see ExtensionVersionUpdater})
 * and enqueues an `extension.audit` job.
 *
 * @since 4.1.0
 */
class UpdateCheckService
{
    /**
     * @param DatabaseInterface       $db             The database connector object.
     * @param Http                    $http           The HTTP client used to fetch update feeds.
     * @param UpdateServerXmlParser   $parser         Parses the update-site XML feed.
     * @param ExtensionVersionUpdater $versionUpdater Applies a detected version bump to the live row.
     * @param QueueService            $queueService   Enqueues the follow-up audit job.
     *
     * @since 4.1.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly Http $http,
        private readonly UpdateServerXmlParser $parser,
        private readonly ExtensionVersionUpdater $versionUpdater,
        private readonly QueueService $queueService
    ) {
    }

    /**
     * Check a batch of extensions for updates.
     *
     * @param int  $batchSize Maximum number of extensions to check in this run.
     * @param bool $force     If true, ignore the "recently checked" cooldown.
     *
     * @return array{checked: int, updated: int, errors: int}
     *
     * @since 4.1.0
     */
    public function run(int $batchSize = 50, bool $force = false): array
    {
        $checked = 0;
        $updated = 0;
        $errors  = 0;

        foreach ($this->getCandidates($batchSize, $force) as $row) {
            $checked++;
            $extensionId = (int) $row->id;

            try {
                if ($this->checkOne($row)) {
                    $updated++;
                }
            } catch (Throwable $e) {
                $errors++;
                $this->stampCheck($extensionId, $e->getMessage());
            }
        }

        return ['checked' => $checked, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Check a single extension row for an update, applying it if one is found.
     *
     * @param object $row The candidate row (id, update_url, extension_version).
     *
     * @return bool True if a newer version was applied.
     *
     * @throws Throwable On a fetch/HTTP failure or downstream write failure.
     *
     * @since 4.1.0
     */
    private function checkOne(object $row): bool
    {
        $extensionId = (int) $row->id;
        $response    = $this->http->get($row->update_url, [], 15);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException(\sprintf('Update server responded with HTTP %d', $response->getStatusCode()));
        }

        $result = $this->parser->parse((string) $response->getBody());

        if ($result === null) {
            $this->stampCheck($extensionId, 'Update feed contained no <update> entries.');

            return false;
        }

        $this->stampCheck($extensionId, null);

        if (!version_compare($result->version, (string) $row->extension_version, '>')) {
            return false;
        }

        $historyId = $this->versionUpdater->applyUpdate($extensionId, $result->version, $result->downloadUrl);
        $this->queueService->enqueue('extension.audit', $extensionId, $historyId, ['version' => $result->version]);

        return true;
    }

    /**
     * Select extensions due for an update check.
     *
     * @param int  $batchSize Maximum rows to select.
     * @param bool $force     If true, skip the "recently checked" cooldown filter.
     *
     * @return object[]
     *
     * @since 4.1.0
     */
    private function getCandidates(int $batchSize, bool $force): array
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'update_url', 'extension_version']))
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('uses_updater') . ' = 1')
            ->where($db->quoteName('update_url') . ' != ' . $db->quote(''))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('last_update_check') . ' IS NULL DESC, ' . $db->quoteName('last_update_check') . ' ASC')
            ->setLimit($batchSize);

        if (!$force) {
            $cutoff = Factory::getDate('now')->sub(new \DateInterval('PT1H'))->toSql();
            $query->where(
                '(' . $db->quoteName('last_update_check') . ' IS NULL OR ' . $db->quoteName('last_update_check') . ' < :cutoff)'
            )->bind(':cutoff', $cutoff, ParameterType::STRING);
        }

        return $db->setQuery($query)->loadObjectList();
    }

    /**
     * Stamp the last-checked timestamp (and optional error) on an extension.
     *
     * @param int         $extensionId The extension id.
     * @param string|null $error       The error message, or null to clear it.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function stampCheck(int $extensionId, ?string $error): void
    {
        $db  = $this->db;
        $now = Factory::getDate('now')->toSql();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__jed_extensions'))
            ->set($db->quoteName('last_update_check') . ' = :now')
            ->set($db->quoteName('last_update_check_error') . ' = :error')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':now', $now, ParameterType::STRING)
            ->bind(':error', $error, ParameterType::STRING)
            ->bind(':id', $extensionId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }
}
