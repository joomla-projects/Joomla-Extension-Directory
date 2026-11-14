<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Feeds\Support;

use Joomla\Database\DatabaseFactory;
use Joomla\Database\DatabaseInterface;

/**
 * The feed tables for the tests: a fresh SQLite memory database each time.
 */
final class TestDatabase
{
    /** The com_vel feed tables (as in sql/install.mysql.utf8.sql), in an SQLite memory database. */
    public static function create(bool $withQueue = true, bool $withProgress = true): DatabaseInterface
    {
        $db = (new DatabaseFactory())->getDriver('sqlite', ['database' => ':memory:', 'prefix' => 'jos_']);

        $tables = [
            'CREATE TABLE jos_vel_feed_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, source TEXT NOT NULL, started TEXT NOT NULL, finished TEXT DEFAULT NULL,
                window_from TEXT DEFAULT NULL, window_to TEXT DEFAULT NULL, items_seen INTEGER NOT NULL DEFAULT 0, items_new INTEGER NOT NULL DEFAULT 0,
                items_updated INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'running\', message TEXT NOT NULL DEFAULT \'\', cursor TEXT NOT NULL DEFAULT \'\''
                . ($withProgress ? ', progress TEXT, heartbeat TEXT DEFAULT NULL' : '') . ')',
            'CREATE TABLE jos_vel_feed_items (id INTEGER PRIMARY KEY AUTOINCREMENT, source TEXT NOT NULL, external_id TEXT NOT NULL, assigner TEXT NOT NULL DEFAULT \'\',
                title TEXT NOT NULL DEFAULT \'\', fetched TEXT NOT NULL, external_updated TEXT DEFAULT NULL, content_hash TEXT NOT NULL DEFAULT \'\', payload TEXT,
                classification TEXT NOT NULL DEFAULT \'unclassified\', classification_reason TEXT NOT NULL DEFAULT \'\', confidence TEXT NOT NULL DEFAULT \'low\',
                decision TEXT NOT NULL DEFAULT \'pending\', advisory_id INTEGER DEFAULT NULL, decided_by INTEGER DEFAULT NULL, decided_time TEXT DEFAULT NULL,
                UNIQUE (source, external_id))',
            'CREATE TABLE jos_vel_advisories (id INTEGER PRIMARY KEY, cve_id TEXT, state TEXT, cvss_vector TEXT, date_updated TEXT)',
            'CREATE TABLE jos_vel_advisory_cwes (advisory_id INTEGER, cwe_id TEXT)',
        ];

        if ($withQueue) {
            $tables[] = 'CREATE TABLE jos_vel_feed_backlog (id INTEGER PRIMARY KEY AUTOINCREMENT, source TEXT NOT NULL, external_id TEXT NOT NULL, url TEXT NOT NULL DEFAULT \'\',
                fetch_time TEXT DEFAULT NULL, queued TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, last_error TEXT NOT NULL DEFAULT \'\', staged_by INTEGER DEFAULT NULL,
                UNIQUE (source, external_id))';
        }

        foreach ($tables as $sql) {
            $db->setQuery($sql)->execute();
        }

        return $db;
    }
}
