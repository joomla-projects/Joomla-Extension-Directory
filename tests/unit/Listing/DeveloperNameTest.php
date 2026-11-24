<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Listing;

use Jed\Component\Jed\Administrator\Listing\DeveloperName;
use Joomla\Database\DatabaseFactory;
use PHPUnit\Framework\TestCase;

final class DeveloperNameTest extends TestCase
{
    /** @dataProvider names */
    public function testUsesTheCurrentOwnersPublicName(?string $customName, int $fieldState, int $fieldAccess, string $expected): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('PDO SQLite is required for this test.');
        }

        $db = (new DatabaseFactory())->getDriver('sqlite', ['database' => ':memory:', 'prefix' => 'jed_test_']);

        $db->setQuery('CREATE TABLE #__users (id INTEGER, name TEXT)')->execute();
        $db->setQuery('CREATE TABLE #__fields (id INTEGER, context TEXT, name TEXT, state INTEGER, access INTEGER)')->execute();
        $db->setQuery('CREATE TABLE #__fields_values (field_id INTEGER, item_id TEXT, value TEXT)')->execute();
        $db->setQuery('CREATE TABLE #__jed_extensions (id INTEGER, owner INTEGER, created_by INTEGER)')->execute();
        $db->setQuery("INSERT INTO #__users VALUES (17, 'Original creator'), (41, 'Owner account')")->execute();
        $db->setQuery('INSERT INTO #__jed_extensions VALUES (12, 41, 17)')->execute();
        $db->setQuery("INSERT INTO #__fields VALUES (5, 'com_users.user', 'developer_name', $fieldState, $fieldAccess)")->execute();
        $db->setQuery("INSERT INTO #__fields_values VALUES (5, '17', 'Original company')")->execute();

        if ($customName !== null) {
            $db->setQuery("INSERT INTO #__fields_values VALUES (5, '41', " . $db->quote($customName) . ')')->execute();
        }

        $query = $db->createQuery()
            ->select(DeveloperName::listingExpression($db))
            ->from($db->quoteName('#__jed_extensions', 'a'));

        self::assertSame($expected, $db->setQuery($query)->loadResult());
        self::assertSame($expected, DeveloperName::forOwner($db, 41));
        self::assertSame('', DeveloperName::forOwner($db, 999));
    }

    public static function names(): array
    {
        return [
            'missing field value' => [null, 1, 1, 'Owner account'],
            'only whitespace'     => ['   ', 1, 1, 'Owner account'],
            'public field value'  => ['  Public company  ', 1, 1, 'Public company'],
            'private field value' => ['Private company', 1, 2, 'Owner account'],
            'unpublished field'   => ['Unpublished company', 0, 1, 'Owner account'],
        ];
    }
}
