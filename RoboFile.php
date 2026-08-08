<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * Download robo.phar from http://robo.li/robo.phar and type in the root of the repo: $ php robo.phar
 * Or do: $ composer update, and afterwards you will be able to execute robo like $ php vendor/bin/robo
 *
 * @package     Joomla.Site
 * @subpackage  RoboFile
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\Jorobo\Tasks\Tasks as loadReleaseTasks;
use Robo\Tasks;

require_once 'vendor/autoload.php';

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', __DIR__);
}

/**
 * Modern php task runner for Joomla! Browser Automated Tests execution
 *
 * @package  RoboFile
 *
 * @since    1.0
 */
class RoboFile extends \Robo\Tasks
{
    // Load tasks from composer, see composer.json
    use loadReleaseTasks;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set default timezone (so no warnings are generated if it is not set)
        date_default_timezone_set('UTC');
    }

    /**
     * Build the joomla extension package
     *
     * @param   array  $params  Additional params
     *
     * @return  void
     */
    public function build($params = ['dev' => false])
    {
        if (!file_exists('jorobo.ini')) {
            $this->_copy('jorobo.dist.ini', 'jorobo.ini');
        }

        $this->taskBuild($params)->run();
    }

    /**
     * Update copyright headers for this project. (Set the text up in the jorobo.ini)
     *
     * @return  void
     */
    public function headers()
    {
        if (!file_exists('jorobo.ini')) {
            $this->_copy('jorobo.dist.ini', 'jorobo.ini');
        }

        (new \Joomla\Jorobo\Tasks\CopyrightHeader())->run();
    }

    /**
     * Update Version __DEPLOY_VERSION__ in Weblinks. (Set the version up in the jorobo.ini)
     *
     * @return  void
     */
    public function bump()
    {
        (new \Joomla\Jorobo\Tasks\BumpVersion())->run();
    }

    /**
     * Map into Joomla installation.
     *
     * @param   String  $target  The target joomla instance
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     *
     */
    public function map($target)
    {
        (new \Joomla\Jorobo\Tasks\Map($target))->run();
    }

    /**
     * Drop every JED table in a Joomla installation and recreate it empty (P1-30).
     *
     * This is the development reset workflow. It used to happen by accident, because every
     * install.mysql.utf8.sql began with a DROP TABLE for each of its tables - convenient before
     * go-live, total data loss after it. The DROPs now live in sql/reset.mysql.utf8.sql, which no
     * manifest references, and this command is the only thing that runs them.
     *
     * Two locks, because the thing on the other side of them is the whole directory:
     *
     *   - `allow_schema_reset = 1` in the [dev] section of jorobo.ini. jorobo.ini is not in the
     *     repository, so this is a decision each developer makes on their own machine and nobody
     *     can make on a server through a web UI.
     *   - `--force`. Without it the command prints what it would drop and stops.
     *
     * Usage:  vendor/bin/robo schema:reset d:/apachefriends/xampp/htdocs/jed2 --force
     *
     * @param   string  $target  Path to the Joomla installation to reset.
     * @param   array   $opts    force: actually execute; without it this is a dry run.
     *
     * @return  int  0 on success, 1 on refusal or failure.
     */
    public function schemaReset($target, $opts = ['force' => false])
    {
        if (!$this->isSchemaResetAllowed()) {
            $this->say('schema:reset is disabled.');
            $this->say('Set allow_schema_reset = 1 in the [dev] section of jorobo.ini to arm it.');
            $this->say('It drops every JED table in the target installation - that is why it is off by default.');

            return 1;
        }

        $target = rtrim(str_replace('\\', '/', $target), '/');

        if (!is_file($target . '/configuration.php')) {
            $this->say('No configuration.php in ' . $target . ' - that is not a Joomla installation.');

            return 1;
        }

        try {
            $config = $this->readJoomlaConfiguration($target);
            $batches = $this->collectResetStatements($config['prefix']);
        } catch (\RuntimeException $e) {
            $this->say($e->getMessage());

            return 1;
        }

        $total = 0;

        foreach ($batches as $component => $statements) {
            $total += count($statements);
            $this->say(sprintf('%-16s %d statements', $component, count($statements)));
        }

        if (empty($opts['force'])) {
            $this->say('');
            $this->say(sprintf(
                'Dry run. %d statements would run against `%s` (prefix `%s`) on %s.',
                $total,
                $config['db'],
                $config['prefix'],
                $config['host']
            ));
            $this->say('Every JED table there would be dropped and recreated empty. Re-run with --force.');

            return 0;
        }

        $pdo = $this->connect($config);

        if ($pdo === null) {
            return 1;
        }

        foreach ($batches as $component => $statements) {
            foreach ($statements as $statement) {
                try {
                    $pdo->exec($statement);
                } catch (\PDOException $e) {
                    $this->say(sprintf('%s: %s', $component, $e->getMessage()));
                    $this->say('Query: ' . substr(preg_replace('/\s+/', ' ', $statement), 0, 160));

                    return 1;
                }
            }

            $this->say($component . ' reset.');
        }

        $this->say(sprintf('Done. %d statements against `%s`. Re-run the import next.', $total, $config['db']));

        return 0;
    }

    /**
     * Read the [dev] allow_schema_reset switch. jorobo.ini wins; jorobo.dist.ini (which ships
     * with the switch off) is the fallback so a developer who never copied the file gets the
     * safe answer rather than an error.
     *
     * @return  bool
     */
    private function isSchemaResetAllowed()
    {
        $file = file_exists(__DIR__ . '/jorobo.ini') ? __DIR__ . '/jorobo.ini' : __DIR__ . '/jorobo.dist.ini';
        $ini  = parse_ini_file($file, true);

        return isset($ini['dev']['allow_schema_reset']) && (string) $ini['dev']['allow_schema_reset'] === '1';
    }

    /**
     * Pull the database credentials out of a Joomla installation's configuration.php.
     *
     * @param   string  $target  Path to the Joomla installation.
     *
     * @return  array
     *
     * @throws  \RuntimeException  On anything that is not a MySQL installation.
     */
    private function readJoomlaConfiguration($target)
    {
        if (!class_exists('JConfig', false)) {
            require_once $target . '/configuration.php';
        }

        $jConfig = new \JConfig();
        $dbType  = strtolower($jConfig->dbtype ?? '');

        if (!in_array($dbType, ['mysql', 'mysqli', 'pdomysql'], true)) {
            throw new \RuntimeException('schema:reset only supports MySQL, not ' . $dbType . '.');
        }

        $host = $jConfig->host ?? 'localhost';
        $port = 3306;

        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
            $port          = (int) $port;
        }

        return [
            'host'     => $host,
            'port'     => $port,
            'user'     => $jConfig->user ?? '',
            'password' => $jConfig->password ?? '',
            'db'       => $jConfig->db ?? '',
            'prefix'   => $jConfig->dbprefix ?? '',
        ];
    }

    /**
     * Build the statement list per component: the DROPs from reset.mysql.utf8.sql, then the
     * CREATEs from install.mysql.utf8.sql. Dropping without recreating would leave the
     * installation broken, which is not what "reset" means to anyone using it.
     *
     * @param   string  $prefix  The target installation's table prefix.
     *
     * @return  array  Component name => list of statements.
     *
     * @throws  \RuntimeException  If a component's SQL is missing.
     */
    private function collectResetStatements($prefix)
    {
        $batches = [];

        foreach (['com_jed', 'com_tickets', 'com_abandonware'] as $component) {
            $dir        = __DIR__ . '/src/administrator/components/' . $component . '/sql';
            $statements = [];

            foreach (['reset.mysql.utf8.sql', 'install.mysql.utf8.sql'] as $file) {
                if (!is_file($dir . '/' . $file)) {
                    throw new \RuntimeException('Missing ' . $component . '/sql/' . $file);
                }

                foreach (self::splitSql(file_get_contents($dir . '/' . $file)) as $statement) {
                    $statements[] = str_replace('#__', $prefix, $statement);
                }
            }

            $batches[$component] = $statements;
        }

        return $batches;
    }

    /**
     * Open the connection. Separate from the dry run on purpose: a dry run must not need working
     * credentials to tell you what it would do.
     *
     * @param   array  $config  As returned by readJoomlaConfiguration().
     *
     * @return  \PDO|null
     */
    private function connect($config)
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['db']);

        try {
            return new \PDO($dsn, $config['user'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (\PDOException $e) {
            $this->say('Could not connect: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Split a SQL file into statements.
     *
     * Quote-aware, because the mail template rows carry JSON with semicolons in it, and comment
     * aware, because these files are mostly commentary. `#__` is explicitly not a comment - the
     * same carve-out Joomla's own DatabaseDriver::splitSql() makes.
     *
     * @param   string  $sql  The file contents.
     *
     * @return  array  The statements, without their trailing semicolon.
     */
    private static function splitSql($sql)
    {
        $statements = [];
        $current    = '';
        $length     = strlen($sql);
        $quote      = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote === null) {
                // Line comments: -- and # , but #__ is a table prefix.
                if (($char === '-' && $next === '-') || ($char === '#' && substr($sql, $i, 3) !== '#__')) {
                    $break = strpos($sql, "\n", $i);
                    $i     = $break === false ? $length : $break;

                    continue;
                }

                // Block comments, except the /*! ... */ and /*+ ... */ forms MySQL executes.
                if ($char === '/' && $next === '*' && !in_array(substr($sql, $i, 3), ['/*!', '/*+'], true)) {
                    $break = strpos($sql, '*/', $i);
                    $i     = $break === false ? $length : $break + 1;

                    continue;
                }

                if ($char === ';') {
                    $statement = trim($current);

                    if ($statement !== '') {
                        $statements[] = $statement;
                    }

                    $current = '';

                    continue;
                }

                if ($char === "'" || $char === '"' || $char === '`') {
                    $quote = $char;
                }
            } elseif ($char === '\\') {
                // Escaped character inside a string - take it and the next one verbatim.
                $current .= $char . $next;
                $i++;

                continue;
            } elseif ($char === $quote) {
                $quote = null;
            }

            $current .= $char;
        }

        $statement = trim($current);

        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
