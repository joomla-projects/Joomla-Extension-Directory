<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Listing;

use PHPUnit\Framework\TestCase;

/**
 * The two linked-extension relations of `P1-23`.
 *
 * Two things are asserted here, and they are different in kind.
 *
 * The first is the **shape of the rules**, read out of the source. `LinkedExtensions::resolve()`
 * and `assertLinkable()` both need a database, and `mayLinkVariant()` needs an identity as well,
 * so - as `ReviewformModelSaveTest` records for the same reason - this suite has no Joomla
 * application fixture to run them against yet (`P1-33` still owes it one). What can be pinned
 * down without one is which columns each rule reads and which of the three rejections exist at
 * all, and those are exactly the things a later refactor quietly drops.
 *
 * The second is the **URL parsing**, which is pure string work and is therefore executed rather
 * than read. That is the half a developer touches: they paste the address of the listing they
 * are looking at, and if it does not resolve, the relation does not get made.
 *
 * @since 4.0.0
 */
final class LinkedExtensionsTest extends TestCase
{
    /**
     * The class source.
     *
     * @var string
     *
     * @since 4.0.0
     */
    private string $source = '';

    /**
     * @return void
     *
     * @since 4.0.0
     */
    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/administrator/components/com_jed/src/Listing/LinkedExtensions.php'
        );
    }

    /**
     * Extract one method body, so a match elsewhere in the class cannot make a test pass by
     * accident.
     *
     * @param string $signature The method signature to start at.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function method(string $signature): string
    {
        $start = strpos($this->source, $signature);

        $this->assertNotFalse($start, $signature . ' is gone from LinkedExtensions.');

        $rest = substr($this->source, $start);
        $next = strpos($rest, "\n    /**");

        return $next === false ? $rest : substr($rest, 0, $next);
    }

    /**
     * The three ways a link target can be wrong, all of them present in the JED3 stock.
     *
     * `P0-03` counted 18 free/paid rows and 6 parent rows pointing at their own extension, and
     * one dangling target in each table. Soft-deleted targets are the third: the frontend is
     * done with those rows, so a link to one is a link to a 410.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testATargetMustNotBeItselfMissingOrDeleted(): void
    {
        $body = $this->method('public static function assertLinkable(');

        $this->assertStringContainsString(
            '$target === $selfId',
            $body,
            'A listing may not link to itself.'
        );
        $this->assertStringContainsString(
            "quoteName('deleted') . ' = 0'",
            $body,
            'A soft-deleted listing may not be linked to.'
        );
        $this->assertStringContainsString(
            '_NOT_FOUND',
            $body,
            'A target that does not exist must be rejected, not stored.'
        );
    }

    /**
     * A blocked or offline target is a state, not a mistake.
     *
     * It can change back, and it is the *rendering* that has to respect it - which the site
     * model does through the one shared visibility rule. Rejecting it on save instead would
     * delete a relation because of something temporary.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testABlockedTargetIsNotRejectedOnSave(): void
    {
        $body = $this->method('public static function assertLinkable(');

        $this->assertStringNotContainsString('blocked', $body);
        $this->assertStringNotContainsString('approved', $body);
    }

    /**
     * The variant permission reads `owner` or an accepted maintainer row - never `created_by`.
     *
     * This is the invariant of 8.8 and the one CLAUDE.md singles out as the likeliest to go
     * wrong. `created_by` does not follow an ownership transfer, so a check that used it would
     * let a previous owner keep pairing listings they no longer own.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testVariantPermissionNeverReadsCreatedBy(): void
    {
        $body = $this->method('private static function isOwnerOrMaintainer(');

        $this->assertStringContainsString("quoteName('owner')", $body);
        $this->assertStringContainsString('#__jed_extensions_maintainers', $body);
        $this->assertStringNotContainsString('created_by', $body);
    }

    /**
     * An invitation that has not been accepted grants nothing.
     *
     * The maintainer row exists from the moment somebody is named, and it is the accepted state
     * that turns a named person into a maintainer (`P1-03` item 4).
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testAnUnacceptedMaintainerInvitationGrantsNothing(): void
    {
        $body = $this->method('private static function isOwnerOrMaintainer(');

        $this->assertStringContainsString('$accepted = 1', $body);
        $this->assertStringContainsString("quoteName('state') . ' = :state'", $body);
    }

    /**
     * Both sides must be the user's own before a developer may pair them.
     *
     * The free and paid halves of one product are usually the same vendor - but `P1-23` notes
     * "not always", and a listing pointing at a stranger's is a claim that stranger never made.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testADeveloperNeedsBothSidesToPairThem(): void
    {
        $body = $this->method('public static function mayLinkVariant(');

        $this->assertStringContainsString(
            'self::isOwnerOrMaintainer($selfId) && self::isOwnerOrMaintainer($target)',
            $body,
            'Pairing needs both listings, not just the one being edited.'
        );
        $this->assertStringContainsString(
            "authorise('core.edit', 'com_jed')",
            $body,
            'The JED team may always make the link.'
        );
    }

    /**
     * The parent claim and its confirmation are separate carriers.
     *
     * The developer states what their own add-on extends; whether that also appears on the other
     * product's page is the team's decision. Mapped onto one column, publishing an edit would
     * confirm the claim - which is the same failure mode `blocked` and `state` are kept apart to
     * avoid (4.8).
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testTheParentClaimIsNotSelfConfirming(): void
    {
        $history = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/administrator/components/com_jed/sql/install.mysql.utf8.sql'
        );

        // The revision table carries the developer's two columns and not the team's third one.
        // Anchored on the CREATE and closed at its own terminator: the live table's comments
        // name #__jed_extensions_history too, and a looser anchor picks up the live table's
        // `parent_confirmed` and quietly inverts what this test proves.
        $this->assertSame(
            1,
            preg_match(
                '~CREATE TABLE IF NOT EXISTS `#__jed_extensions_history`\s*\((.*?)\n\) ENGINE~s',
                $history,
                $m
            ),
            'The revision table is gone from the install SQL.'
        );

        $historyBlock = $m[1];

        $this->assertStringContainsString('`variant_of_id`', $historyBlock);
        $this->assertStringContainsString('`parent_id`', $historyBlock);
        $this->assertStringNotContainsString('`parent_confirmed`', $historyBlock);

        // And approval refuses to promote it even if somebody adds the column later.
        $approve = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/administrator/components/com_jed/src/Model/ExtensionModel.php'
        );

        $this->assertStringContainsString("\$liveData['parent_confirmed']", $approve);
    }

    /**
     * Changing which product an add-on claims to extend withdraws the confirmation.
     *
     * Without this a developer could get a link confirmed against something nobody minds and
     * then re-point it at VirtueMart, keeping the tick and the 268-listing audience with it.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testMovingTheParentWithdrawsTheConfirmation(): void
    {
        $table = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/administrator/components/com_jed/src/Table/ExtensionTable.php'
        );

        $start = strpos($table, 'protected function normaliseLinks(');
        $this->assertNotFalse($start, 'ExtensionTable no longer vets the link columns.');

        $body = substr($table, $start);
        $body = substr($body, 0, (int) strpos($body, "\n    /**") ?: null);

        $this->assertStringContainsString('$this->parent_confirmed = 0', $body);
        $this->assertStringContainsString('$storedParent', $body);
    }

    /**
     * The reverse direction of the parent relation is the confirmed one.
     *
     * "Add-on for X" is a statement about your own listing and shows unconditionally. "Add-ons
     * for this extension" puts other people's listings on somebody else's page, and that is what
     * the confirmation gates - on the detail page and on the list the "see all" link leads to.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testOnlyConfirmedAddOnsAppearOnTheParentsPage(): void
    {
        $model = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/components/com_jed/src/Model/ExtensionModel.php'
        );

        $start = strpos($model, 'public function getLinkedExtensions(');
        $this->assertNotFalse($start, 'The detail page no longer loads linked extensions.');

        $body = substr($model, $start);

        $this->assertStringContainsString("quoteName('a.parent_confirmed') . ' = 1'", $body);

        $list = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/components/com_jed/src/Model/ExtensionsModel.php'
        );

        $this->assertStringContainsString("quoteName('a.parent_confirmed') . ' = 1'", $list);
    }

    /**
     * The variant relation is stored once and read in both directions.
     *
     * Storing it twice is what JED3 did for 75 of its 938 pairs and not for the rest, which is
     * precisely the inconsistency one row per pair cannot have. The price is that the read has
     * to ask both ways, and forgetting that loses half the catalogue's pairs.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testTheVariantRelationIsReadInBothDirections(): void
    {
        $model = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/components/com_jed/src/Model/ExtensionModel.php'
        );

        $start = strpos($model, 'public function getLinkedExtensions(');
        $body  = substr($model, (int) $start);

        // The listing this one names, OR the listings that name it.
        $this->assertMatchesRegularExpression(
            '~variant_of_id.{0,200}OR.{0,200}variant_of_id~s',
            $body,
            'The variant lookup must cover both directions.'
        );
    }

    /**
     * What {@see LinkedExtensions::resolve()} accepts, expressed as the parsing it performs.
     *
     * A developer identifies the other listing by its page, not by a primary key, so a pasted
     * JED URL has to reduce to the same thing the alias does. This mirrors the transformations
     * in resolve() up to the database lookup, which is the part that needs no fixture.
     *
     * @param string $input    What somebody pastes into the field.
     * @param string $expected What is looked up: a numeric id, or an alias.
     *
     * @return void
     *
     * @dataProvider references
     *
     * @since 4.0.0
     */
    public function testAPastedReferenceReducesToAnIdOrAnAlias(string $input, string $expected): void
    {
        $raw = trim($input);

        if (preg_match('~^https?://~i', $raw)) {
            $query = [];
            parse_str((string) parse_url($raw, PHP_URL_QUERY), $query);

            if (!empty($query['id'])) {
                $raw = (string) $query['id'];
            } else {
                $path = trim((string) parse_url($raw, PHP_URL_PATH), '/');
                $raw  = $path === '' ? '' : (string) substr(strrchr('/' . $path, '/'), 1);
            }
        }

        if (preg_match('~^(\d+)-~', $raw, $m)) {
            $raw = $m[1];
        }

        $this->assertSame($expected, $raw);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     *
     * @since 4.0.0
     */
    public static function references(): array
    {
        return [
            'a bare id'            => ['1791', '1791'],
            'an alias'             => ['hikashop', 'hikashop'],
            'a non-SEF URL'        => ['http://localhost/index.php?option=com_jed&view=extension&id=1791', '1791'],
            'a SEF URL'            => ['https://extensions.joomla.org/extension/hikashop', 'hikashop'],
            'a SEF URL with slash' => ['https://extensions.joomla.org/extension/hikashop/', 'hikashop'],
            'an id-alias segment'  => ['1791-hikashop', '1791'],
            'surrounding spaces'   => ['  hikashop  ', 'hikashop'],
        ];
    }
}
