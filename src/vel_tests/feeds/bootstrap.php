<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Bootstrap for the feed tests (vel_tests/phpunit-feeds.xml.dist).
 *
 * These run the real feed code - FeedRunner, the feed sources, the HTTP clients, the delta-log
 * parser, the queue - against an SQLite database and fake cvelistV5 / NVD servers. They need
 * Joomla's own `libraries` folder (Joomla 5 or 6) for its Database, Registry and Http packages;
 * the few CMS services the feed code touches (Text, Log, ComponentHelper, Factory, HttpFactory)
 * and the advisory side (VelService, AdvisoryMerger) are replaced by the stand-ins in
 * Support/CmsStubs.php. Nothing is fetched from the internet.
 *
 * Where the libraries are, first found wins:
 *   JOOMLA_LIBRARIES=/path/to/joomla/libraries
 *   JOOMLA_ROOT=/path/to/joomla            (uses its libraries folder)
 *   <this repository>/libraries
 *
 * Without them, or without pdo_sqlite, every test is skipped with the reason.
 */

\defined('_JEXEC') || \define('_JEXEC', 1);
\defined('JPATH_ROOT') || \define('JPATH_ROOT', \dirname(__DIR__, 2));
\defined('JPATH_ADMINISTRATOR') || \define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');
\defined('JPATH_SITE') || \define('JPATH_SITE', JPATH_ROOT);

$libraries = null;

foreach (array_filter([getenv('JOOMLA_LIBRARIES') ?: null, getenv('JOOMLA_ROOT') ? getenv('JOOMLA_ROOT') . '/libraries' : null, JPATH_ROOT . '/libraries']) as $candidate) {
    if (is_file($candidate . '/vendor/autoload.php') && is_dir($candidate . '/vendor/joomla/database')) {
        $libraries = $candidate;

        break;
    }
}

$reason = match (true) {
    $libraries === null                => 'The feed tests need a Joomla 5/6 "libraries" folder: set JOOMLA_LIBRARIES=/path/to/joomla/libraries (or JOOMLA_ROOT).',
    !\extension_loaded('pdo_sqlite')   => 'The feed tests need the pdo_sqlite PHP extension.',
    default                            => '',
};

if ($reason === '') {
    require $libraries . '/vendor/autoload.php';

    // Before anything can autoload the real CMS classes.
    require __DIR__ . '/Support/CmsStubs.php';

    spl_autoload_register(static function (string $class): void {
        $map = [
            'Jed\\Component\\Vel\\Administrator\\' => JPATH_ADMINISTRATOR . '/components/com_vel/src/',
            'Jed\\Tests\\Feeds\\'                 => __DIR__ . '/',
        ];

        foreach ($map as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

                if (is_file($file)) {
                    require $file;
                }

                return;
            }
        }
    }, true, true);
}

\define('VEL_FEED_TESTS_SKIP', $reason);
