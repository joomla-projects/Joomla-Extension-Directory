<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;

/**
 * The owner / maintainer rules of 8.8.
 *
 * The invariant these guard is the one CLAUDE.md singles out because it is the likeliest to go
 * wrong: a permission check reads `owner` OR an accepted maintainer row, and **never**
 * `created_by`. Joomla components conventionally key on `created_by`, and `created_by` does not
 * follow an ownership transfer - so a check that uses it hands the previous owner permanent
 * access to a listing they no longer own (8.8.1).
 *
 * The four-way coverage the acceptance criteria ask for - owner, maintainer, both, neither - is
 * exercised against the running installation by `p103_roles.php`, which can create real rows.
 * What is asserted here is the shape of the rules themselves: which column each helper reads,
 * that an unaccepted invitation grants nothing, and that no access decision anywhere in com_jed
 * has drifted back to `created_by`. `P1-33` still owes this suite an application fixture.
 *
 * @since 4.1.0
 */
final class RoleRulesTest extends TestCase
{
    /**
     * The site helper source.
     *
     * @var string
     *
     * @since 4.1.0
     */
    private string $helper = '';

    /**
     * @return void
     *
     * @since 4.1.0
     */
    protected function setUp(): void
    {
        $this->helper = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/components/com_jed/src/Helper/JedHelper.php'
        );
    }

    /**
     * Extract one method body, so a match elsewhere cannot make a test pass by accident.
     *
     * @param string $signature The method signature to start at.
     *
     * @return string
     *
     * @since 4.1.0
     */
    private function methodBody(string $signature): string
    {
        $start = strpos($this->helper, $signature);
        $this->assertNotFalse($start, $signature . ' not found');

        $rest = substr($this->helper, $start);
        $end  = strpos($rest, "\n    }");

        return $end === false ? $rest : substr($rest, 0, $end);
    }

    /**
     * The owner-only rule reads `owner` and nothing else.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testIsOwnerReadsTheOwnerColumn(): void
    {
        $body = $this->methodBody('public static function isOwner(int $extensionId): bool');

        $this->assertStringContainsString("quoteName('owner')", $body);
        $this->assertStringNotContainsString('created_by', $body);
        $this->assertStringNotContainsString('maintainers', $body);
    }

    /**
     * Owner-only and owner-or-maintainer are separate entry points.
     *
     * Soft delete and ownership transfer are the owner's alone (the 8.8 matrix). Expressing that
     * as an argument on one helper would let a call site pick the laxer rule by leaving it out.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testTheTwoRolesAreSeparateHelpers(): void
    {
        $this->assertStringContainsString('public static function isOwner(int $extensionId): bool', $this->helper);
        $this->assertStringContainsString('public static function isOwnerOrMaintainer(int $extensionId): bool', $this->helper);
    }

    /**
     * An invitation that has not been accepted grants nothing.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testOnlyAcceptedMaintainersCount(): void
    {
        $body = $this->methodBody('public static function isOwnerOrMaintainer(int $extensionId): bool');

        $this->assertStringContainsString('MAINTAINER_ACCEPTED', $body);
        $this->assertStringContainsString("quoteName('state')", $body);
        $this->assertStringNotContainsString('created_by', $body);

        $list = $this->methodBody('public static function getOwnedOrMaintainedCondition(');
        $this->assertStringContainsString('MAINTAINER_ACCEPTED', $list);
        $this->assertStringNotContainsString('created_by', $list);
    }

    /**
     * The three states are distinct values, so "invited" and "declined" cannot collapse into
     * "accepted" through a loose comparison.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testMaintainerStatesAreDistinct(): void
    {
        preg_match('/MAINTAINER_INVITED\s*=\s*(-?\d+)/', $this->helper, $invited);
        preg_match('/MAINTAINER_ACCEPTED\s*=\s*(-?\d+)/', $this->helper, $accepted);
        preg_match('/MAINTAINER_DECLINED\s*=\s*(-?\d+)/', $this->helper, $declined);

        $values = [$invited[1] ?? null, $accepted[1] ?? null, $declined[1] ?? null];

        $this->assertNotContains(null, $values, 'all three maintainer states must be declared');
        $this->assertCount(3, array_unique($values));
    }

    /**
     * The generic authorship helper refuses the extensions table outright.
     *
     * The plan is explicit that a helper whose correctness depends on which table it is handed
     * must not be left in place. Making the wrong table an error is the strongest form of that.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testAuthorshipHelperRefusesExtensions(): void
    {
        $body = $this->methodBody('public static function userIDItem(int $id, string $table): bool');

        $this->assertStringContainsString("str_contains(\$table, 'jed_extensions')", $body);
        $this->assertStringContainsString('throw new Exception', $body);
    }

    /**
     * canUserEdit() refuses to answer for an extension, and no longer fails open.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testCanUserEditRefusesExtensionsAndDoesNotFailOpen(): void
    {
        $body = $this->methodBody('public static function canUserEdit(mixed $item): bool');

        $this->assertStringContainsString("property_exists(\$item, 'owner')", $body);
        // An existing record with no authorship column used to return true.
        $this->assertMatchesRegularExpression(
            '/if \(!isset\(\$item->created_by\)\) \{\s*return false;/',
            $body
        );
    }

    /**
     * No access decision in com_jed keys on `created_by` for an extension.
     *
     * The grep discipline the plan asks for, as a test rather than a habit: it fails when
     * somebody reintroduces the pattern, which a one-off grep cannot do.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testNoExtensionAccessDecisionUsesCreatedBy(): void
    {
        $root = \dirname(__DIR__, 3) . '/src';

        // The specific places that decide who may act on an *extension*. Whole files would be
        // the wrong unit: DashboardModel also queries tickets and reviews, where created_by is
        // the authorship column and is exactly right.
        $suspects = [
            '/components/com_jed/src/Model/ExtensionsModel.php'             => 'public function getMyItems',
            '/components/com_jed/src/Model/DashboardModel.php'              => 'public function getExtensions',
            '/components/com_jed/src/Field/JedmyextensionsField.php'        => 'protected function getOptions',
            '/administrator/components/com_jed/tmpl/extensions/default.php' => null,
        ];

        foreach ($suspects as $relative => $method) {
            $source = (string) file_get_contents($root . $relative);

            if ($method !== null) {
                $start = strpos($source, $method);
                $this->assertNotFalse($start, $method . ' not found in ' . $relative);
                $source = substr($source, $start);
                $end    = strpos($source, "\n    }");
                $source = $end === false ? $source : substr($source, 0, $end);
            }

            // Strip comments before matching - the explanations deliberately mention the column.
            $stripped = preg_replace('#(//[^\n]*|/\*.*?\*/)#s', '', $source);

            $this->assertDoesNotMatchRegularExpression(
                '/where\([^)]*created_by/i',
                $stripped,
                $relative . ' still filters an extension access decision on created_by'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/item->created_by\s*===?\s*\$userId/',
                $stripped,
                $relative . ' still compares created_by to decide access'
            );
        }

        // ForeignkeyField is generic across tables and keeps a created_by branch on purpose, for
        // the authored records it also points at. What matters is that the extensions branch
        // takes the ownership rule, so assert the branch rather than the file.
        $field = (string) file_get_contents($root . '/administrator/components/com_jed/src/Field/ForeignkeyField.php');

        $this->assertMatchesRegularExpression(
            "/str_contains\(.*jed_extensions.*\)\)\s*\{\s*\\\$query->where\(SiteJedHelper::getOwnedOrMaintainedCondition/s",
            $field,
            'the extensions branch of ForeignkeyField must use the ownership rule'
        );
    }

    /**
     * access.xml names every JED-specific action the plan lists.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testAccessXmlNamesTheJedActions(): void
    {
        $xml = simplexml_load_file(
            \dirname(__DIR__, 3) . '/src/administrator/components/com_jed/access.xml'
        );

        $this->assertNotFalse($xml);

        $declared = [];

        foreach ($xml->xpath('//section[@name="component"]/action') as $action) {
            $declared[] = (string) $action['name'];
        }

        foreach (['jed.approve', 'jed.block', 'jed.transfer.force', 'jed.audit.view', 'jed.user.ban'] as $action) {
            $this->assertContains($action, $declared, $action . ' is missing from access.xml');
        }
    }

    /**
     * The declared actions are actually checked, rather than being decoration.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testTheJedActionsAreEnforced(): void
    {
        $controller = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/administrator/components/com_jed/src/Controller/ExtensionController.php'
        );

        $this->assertStringContainsString("'jed.block'", $controller);
        $this->assertStringContainsString("authorise('jed.approve', 'com_jed')", $controller);
    }
}
