<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// The classes under test guard against direct access the way every file in the package does;
// the tests are the one legitimate caller outside Joomla.
\defined('_JEXEC') || \define('_JEXEC', 1);
\defined('JPATH_ROOT') || \define('JPATH_ROOT', \dirname(__DIR__, 2));
\defined('JPATH_ADMINISTRATOR') || \define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');
\defined('JPATH_SITE') || \define('JPATH_SITE', JPATH_ROOT);

// PSR-4 for the package's own namespaces. Joomla's framework classes are not loaded: the
// classes these tests cover are written to need none of them at run time, and a test that
// does hit one fails loudly with "class not found", which is the right answer.
spl_autoload_register(static function (string $class): void {
    $map = [
        'Jed\\Component\\Vel\\Administrator\\' => JPATH_ADMINISTRATOR . '/components/com_vel/src/',
        'Jed\\Component\\Vel\\Site\\'          => JPATH_SITE . '/components/com_vel/src/',
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
});

$composer = JPATH_ROOT . '/vendor/autoload.php';

if (is_file($composer)) {
    require $composer;
}
