<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Finder;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseQuery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Smart Search recomputes a link's state from getStateQuery() whenever a category or an item
 * changes state. That query has to carry the same visibility rule index() applies, or toggling a
 * category makes blocked, unapproved and deleted listings searchable again.
 *
 * @since 4.0.0
 */
final class StateQueryTest extends TestCase
{
    public function testTheStateIsZeroForAListingThePublicCannotSee(): void
    {
        $class = 'Joomla\\Plugin\\Finder\\Jed\\Extension\\Jed';

        // Joomla maps these namespaces from the installed extensions; the suite runs against the
        // repository, so they are pointed at the sources directly.
        \JLoader::registerNamespace('Joomla\\Plugin\\Finder\\Jed', dirname(__DIR__, 3) . '/src/plugins/finder/jed/src');
        \JLoader::registerNamespace('Joomla\\Component\\Finder\\Administrator', JPATH_ADMINISTRATOR . '/components/com_finder/src');

        if (!class_exists($class)) {
            $this->markTestSkipped('Finder adapter is not autoloadable here.');
        }

        $selected = [];
        $query    = $this->createMock(DatabaseQuery::class);
        $query->method('select')->willReturnCallback(function ($columns) use (&$selected, $query) {
            $selected[] = $columns;

            return $query;
        });
        $query->method('from')->willReturnSelf();
        $query->method('leftJoin')->willReturnSelf();

        $db = $this->createMock(DatabaseInterface::class);
        $db->method('createQuery')->willReturn($query);
        $db->method('quoteName')->willReturnArgument(0);

        $reflection = new ReflectionClass($class);
        $adapter    = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($adapter, $db);

        $reflection->getMethod('getStateQuery')->invoke($adapter);

        $sql = implode(' ', $selected);

        foreach (['a.approved = 1', 'a.blocked = 0', 'a.deleted = 0'] as $condition) {
            $this->assertStringContainsString($condition, $sql);
        }

        $this->assertStringContainsString('ELSE 0 END AS state', $sql);
    }
}
