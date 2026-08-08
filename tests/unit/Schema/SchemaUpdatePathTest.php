<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;

/**
 * The schema update path (`P1-30`), asserted against the files that implement it.
 *
 * Two things happen at go-live that cannot be undone afterwards. The schema stops being free to
 * change, so every later change needs a file in `sql/updates/mysql/` and an anchor row in
 * `#__schemas` to compare it against; and the tables stop being disposable, so an install must
 * never destroy them. Both are one-line mistakes to make and neither announces itself - a missing
 * baseline file looks like nothing until the first update replays the whole history, and a
 * reinstated `DROP TABLE` looks like a tidy-up until somebody reinstalls the package.
 *
 * So they are asserted here rather than left to review.
 *
 * @since 4.0.0
 */
final class SchemaUpdatePathTest extends TestCase
{
    /**
     * The repository root.
     *
     * @since 4.0.0
     */
    private const ROOT = __DIR__ . '/../../..';

    /**
     * The components in pkg_jed that own tables, and the manifest each one declares them in.
     *
     * @since 4.0.0
     */
    private const COMPONENTS = [
        'com_jed'         => 'jed.xml',
        'com_tickets'     => 'tickets.xml',
        'com_abandonware' => 'abandonware.xml',
    ];

    /**
     * Columns of `#__jed_extensions` that `#__jed_extensions_history` deliberately does not
     * mirror, with the reason. Anything not listed here has to be mirrored.
     *
     * `entry_version` is a pointer from the live row into the history table - a revision holding
     * one would either point at itself or, once restored, point the listing at some other
     * revision. `last_update_check` and `last_update_check_error` are the automated update
     * probe's results (`P1-08`): they describe the developer's update server at a moment, not the
     * listing's content, so a rollback must not resurrect a stale one and a probe must not create
     * a revision. `ExtensionVersionUpdater::applyUpdate()` unsets exactly these three.
     *
     * `parent_confirmed` is the JED team's verdict on a developer's parent claim (`P1-23`).
     * `ExtensionModel::approve()` copies every history column onto the live row, so a column
     * present in the revision table is by definition a column a developer can set - and this one
     * decides whether their add-on appears on somebody else's listing, which at 268 listings
     * pointing at VirtueMart alone is a spam lever. Keeping it out of the revision is the primary
     * control; the `unset()` in `approve()` is the second line. The same separation as `blocked`
     * against `state` (4.8).
     *
     * @since 4.0.0
     */
    private const HISTORY_EXEMPT = [
        'entry_version',
        'last_update_check',
        'last_update_check_error',
        'parent_confirmed',
    ];

    /**
     * Columns `#__jed_extensions_history` adds because it is a history table.
     *
     * @since 4.0.0
     */
    private const HISTORY_OWN = [
        'extension_id',
        'active',
    ];

    /**
     * Every component ships a schema update file matching the version it is built as.
     *
     * This is the acceptance criterion that answers the plan's open question. The go-live version
     * is whatever `jorobo.ini` says at go-live, and bumping it without adding the matching file is
     * exactly how this item gets missed - so bumping it without adding the file fails here.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testEveryComponentHasABaselineForTheBuiltVersion(): void
    {
        $version = $this->buildVersion();

        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+/',
            $version,
            'The version in jorobo.ini is not a version number.'
        );

        foreach (array_keys(self::COMPONENTS) as $component) {
            $this->assertFileExists(
                $this->sqlDir($component) . '/updates/mysql/' . $version . '.sql',
                $component . ' has no schema update file for version ' . $version . '. Add'
                . ' sql/updates/mysql/' . $version . '.sql - it may be empty; its existence is what'
                . ' anchors #__schemas.'
            );
        }
    }

    /**
     * Every component's manifest points Joomla at that folder.
     *
     * The files alone do nothing. On the update route Joomla ignores `<install><sql>` and reads
     * `<update><schemas><schemapath>`; without the element it never writes a `#__schemas` row
     * either, so a fresh install starts with no anchor at all.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testEveryManifestDeclaresTheSchemaPath(): void
    {
        foreach (self::COMPONENTS as $component => $manifestFile) {
            $path     = self::ROOT . '/src/administrator/components/' . $component . '/' . $manifestFile;
            $manifest = simplexml_load_file($path);

            $this->assertNotFalse($manifest, $manifestFile . ' is not readable XML.');

            $paths = $manifest->xpath('//update/schemas/schemapath[@type="mysql"]');

            $this->assertNotEmpty(
                $paths,
                $manifestFile . ' declares no <update><schemas><schemapath type="mysql">, so Joomla'
                . ' will never run this component\'s schema updates.'
            );
            $this->assertSame('sql/updates/mysql', trim((string) $paths[0]), $manifestFile);
            $this->assertDirectoryExists($this->sqlDir($component) . '/updates/mysql');
        }
    }

    /**
     * Update file names are version numbers, and none claims to be newer than the build.
     *
     * Joomla sorts the folder with `version_compare` and records the highest name it finds as the
     * installed schema version. A file named above the build version would therefore mark schema
     * changes as applied that the package does not contain.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testUpdateFileNamesAreVersionsNotNewerThanTheBuild(): void
    {
        $version = $this->buildVersion();

        foreach (array_keys(self::COMPONENTS) as $component) {
            foreach (glob($this->sqlDir($component) . '/updates/mysql/*.sql') as $file) {
                $name = basename($file, '.sql');

                $this->assertMatchesRegularExpression(
                    '/^\d+\.\d+\.\d+$/',
                    $name,
                    $component . ': ' . basename($file) . ' is not named after a version.'
                );
                $this->assertLessThanOrEqual(
                    0,
                    version_compare($name, $version),
                    $component . ': ' . basename($file) . ' is newer than the version in jorobo.ini ('
                    . $version . ').'
                );
            }
        }
    }

    /**
     * No install file destroys anything.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testInstallSqlDestroysNothing(): void
    {
        foreach (array_keys(self::COMPONENTS) as $component) {
            $sql = $this->executableSql($this->sqlDir($component) . '/install.mysql.utf8.sql');

            $this->assertDoesNotMatchRegularExpression(
                '/\b(DROP\s+TABLE|TRUNCATE)\b/i',
                $sql,
                $component . ': install.mysql.utf8.sql destroys data. After go-live an install is'
                . ' reached again after any uninstall, and the tables are still there. The DROPs'
                . ' belong in reset.mysql.utf8.sql.'
            );
        }
    }

    /**
     * No uninstall file drops a table.
     *
     * Joomla also pushes the uninstall SQL as the rollback step for a *failed* install, so this
     * covers more than somebody clicking uninstall.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testUninstallSqlDropsNoTables(): void
    {
        foreach (array_keys(self::COMPONENTS) as $component) {
            $sql = $this->executableSql($this->sqlDir($component) . '/uninstall.mysql.utf8.sql');

            $this->assertDoesNotMatchRegularExpression(
                '/\b(DROP\s+TABLE|TRUNCATE)\b/i',
                $sql,
                $component . ': uninstalling drops its tables. For this package that is the'
                . ' directory itself, and it is also what Joomla runs when an install fails.'
            );
        }
    }

    /**
     * Installing twice is the same as installing once.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testInstallSqlIsRepeatable(): void
    {
        foreach (array_keys(self::COMPONENTS) as $component) {
            $sql = $this->executableSql($this->sqlDir($component) . '/install.mysql.utf8.sql');

            preg_match_all('/CREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i', $sql, $bare);
            $this->assertCount(
                0,
                $bare[0],
                $component . ': a CREATE TABLE without IF NOT EXISTS fails on a reinstall.'
            );

            preg_match_all('/INSERT\s+(?!IGNORE)/i', $sql, $strict);
            $this->assertCount(
                0,
                $strict[0],
                $component . ': an INSERT without IGNORE fails on a reinstall over surviving rows.'
            );
        }
    }

    /**
     * The reset file still drops exactly what the install creates.
     *
     * The reset workflow is worth keeping - that is why the statements were moved rather than
     * deleted. Moved somewhere nothing runs them, they rot: a table added to the install and not
     * to the reset leaves a stale table behind on the next reset, which is a confusing way to
     * spend an afternoon.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testResetFileMatchesTheInstall(): void
    {
        foreach (array_keys(self::COMPONENTS) as $component) {
            $created = $this->tablesMatching(
                '/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`(#__jed_[a-z0-9_]+)`/i',
                $this->sqlDir($component) . '/install.mysql.utf8.sql'
            );
            $dropped = $this->tablesMatching(
                '/DROP\s+TABLE(?:\s+IF\s+EXISTS)?\s+`(#__jed_[a-z0-9_]+)`/i',
                $this->sqlDir($component) . '/reset.mysql.utf8.sql'
            );

            sort($created);
            sort($dropped);

            $this->assertSame(
                $created,
                $dropped,
                $component . ': reset.mysql.utf8.sql and install.mysql.utf8.sql disagree about'
                . ' which tables exist.'
            );
        }
    }

    /**
     * Nothing installs the reset file.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testNoManifestReferencesTheResetFile(): void
    {
        foreach (self::COMPONENTS as $component => $manifestFile) {
            $manifest = (string) file_get_contents(
                self::ROOT . '/src/administrator/components/' . $component . '/' . $manifestFile
            );

            $this->assertStringNotContainsString(
                'reset.mysql.utf8.sql',
                $manifest,
                $manifestFile . ' references the reset file. Joomla would then run it.'
            );
        }
    }

    /**
     * `#__jed_extensions_history` mirrors `#__jed_extensions`, minus the documented exceptions.
     *
     * A revision that is missing a column does not fail; it restores that column to whatever the
     * live row happens to hold, silently. For `blocked` (`P1-01`) or a consent flag (`P1-27`) that
     * means a rollback can misrepresent a decision somebody made deliberately. So every column
     * added to the listing from here on has to be added to the history table as well, or added to
     * `HISTORY_EXEMPT` with a reason - which is a decision, and the point is that it is made.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testHistoryMirrorsTheExtensionsTable(): void
    {
        $file      = $this->sqlDir('com_jed') . '/install.mysql.utf8.sql';
        $extension = $this->columnsOf($file, '#__jed_extensions');
        $history   = $this->columnsOf($file, '#__jed_extensions_history');

        $this->assertNotEmpty($extension, 'Could not read the #__jed_extensions columns.');
        $this->assertNotEmpty($history, 'Could not read the #__jed_extensions_history columns.');

        $missing = array_values(array_diff($extension, $history, self::HISTORY_EXEMPT));

        $this->assertSame(
            [],
            $missing,
            'The history table does not mirror: ' . implode(', ', $missing) . '. Add the columns,'
            . ' or add them to HISTORY_EXEMPT with the reason they are not part of a revision.'
        );

        $extra = array_values(array_diff($history, $extension, self::HISTORY_OWN));

        $this->assertSame(
            [],
            $extra,
            'The history table has columns the listing does not: ' . implode(', ', $extra) . '.'
        );

        // A documented exception that has since been mirrored is a stale note, not a decision.
        $this->assertSame(
            [],
            array_values(array_intersect(self::HISTORY_EXEMPT, $history)),
            'HISTORY_EXEMPT names columns the history table actually has.'
        );
    }

    /**
     * The version the package is built as.
     *
     * `jorobo.ini` is not in the repository; `robo build` creates it from `jorobo.dist.ini`. Read
     * whichever is there, the same way JoRobo does.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function buildVersion(): string
    {
        $file = file_exists(self::ROOT . '/jorobo.ini')
            ? self::ROOT . '/jorobo.ini'
            : self::ROOT . '/jorobo.dist.ini';

        $ini = parse_ini_file($file, true);

        $this->assertIsArray($ini, 'Could not read ' . basename($file) . '.');
        $this->assertArrayHasKey('version', $ini, basename($file) . ' declares no version.');

        return (string) $ini['version'];
    }

    /**
     * The sql folder of a component.
     *
     * @param string $component The component name.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function sqlDir(string $component): string
    {
        return self::ROOT . '/src/administrator/components/' . $component . '/sql';
    }

    /**
     * A SQL file with its commentary removed, so that describing a DROP does not read as one.
     *
     * `#__` is a table prefix and not a comment, which is the same carve-out Joomla's
     * `DatabaseDriver::splitSql()` makes.
     *
     * @param string $file The file to read.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function executableSql(string $file): string
    {
        $this->assertFileExists($file);

        $sql = (string) file_get_contents($file);
        $sql = preg_replace('~/\*(?![!+]).*?\*/~s', ' ', $sql);
        $sql = preg_replace('/--[^\n]*/', ' ', (string) $sql);
        $sql = preg_replace('/#(?!__)[^\n]*/', ' ', (string) $sql);

        return (string) $sql;
    }

    /**
     * The table names a pattern finds in a file.
     *
     * @param string $pattern The pattern, with the name in group 1.
     * @param string $file    The file to read.
     *
     * @return array<int, string>
     *
     * @since 4.0.0
     */
    private function tablesMatching(string $pattern, string $file): array
    {
        preg_match_all($pattern, $this->executableSql($file), $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The column names of one CREATE TABLE in a file, in declaration order.
     *
     * @param string $file  The file to read.
     * @param string $table The table name, including the `#__` prefix.
     *
     * @return array<int, string>
     *
     * @since 4.0.0
     */
    private function columnsOf(string $file, string $table): array
    {
        $sql = $this->executableSql($file);

        $found = preg_match(
            '/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`' . preg_quote($table, '/') . '`\s*\((.*?)\n\)\s*ENGINE/s',
            $sql,
            $matches
        );

        if ($found !== 1) {
            return [];
        }

        $columns = [];

        foreach (explode("\n", $matches[1]) as $line) {
            $line = trim($line);

            // Column definitions start with a backticked name; keys start with PRIMARY/KEY/UNIQUE.
            if (preg_match('/^`([a-z0-9_]+)`\s+\S/i', $line, $column)) {
                $columns[] = $column[1];
            }
        }

        return $columns;
    }
}
