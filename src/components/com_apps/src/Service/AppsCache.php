<?php

/**
 * @package JED
 *
 * @subpackage Apps
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Apps\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\OutputController;

/**
 * The origin cache: rendered answers in Joomla's cache, group com_apps, so that System > Clear
 * Cache can empty it. A lock makes a cold key cost one rebuild, not one per waiting request.
 *
 * @since 4.0.0
 */
class AppsCache
{
    public const GROUP = 'com_apps';

    private OutputController $cache;

    public function __construct(CacheControllerFactoryInterface $factory, private readonly int $minutes, private readonly string $lockDir)
    {
        $this->cache = $factory->createCacheController('output', [
            'defaultgroup' => self::GROUP,
            'caching'      => $minutes > 0,
            'lifetime'     => max(1, $minutes),
        ]);
    }

    /**
     * @param callable $build fn(): AppsResult
     *
     * @since 4.0.0
     */
    public function remember(string $key, callable $build): AppsResult
    {
        if ($this->minutes < 1) {
            return $build();
        }

        $hit = $this->read($key);

        if ($hit !== null) {
            return $hit;
        }

        $lock = $this->lock($key);

        try {
            // Whoever held the lock may have filled the key while this request waited.
            $hit = $this->read($key);

            if ($hit !== null) {
                return $hit;
            }

            $result = $build();

            if ($result->kind !== AppsResult::KIND_ERROR) {
                $this->cache->store(json_encode($result->toStored()), $key, self::GROUP);
            }

            return $result;
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private function read(string $key): ?AppsResult
    {
        $raw = $this->cache->get($key, self::GROUP);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $stored = json_decode($raw, true);

        return is_array($stored) && isset($stored['status'], $stored['body'], $stored['kind'])
            ? AppsResult::fromStored($stored)
            : null;
    }

    /**
     * @return resource|null  null if the lock file cannot be used: then the request just rebuilds.
     */
    private function lock(string $key)
    {
        if (!is_dir($this->lockDir) && !@mkdir($this->lockDir, 0755, true) && !is_dir($this->lockDir)) {
            return null;
        }

        $handle = @fopen($this->lockDir . '/' . md5($key) . '.lock', 'c');

        if ($handle === false) {
            return null;
        }

        flock($handle, LOCK_EX);

        return $handle;
    }
}
