<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Abandonware;

use Jed\Component\Abandonware\Administrator\Enum\CaseStatus;
use Jed\Component\Abandonware\Administrator\Enum\Resolution;
use PHPUnit\Framework\TestCase;

/**
 * The abandonware case's status machine (`P1-19`).
 *
 * Worth a table test rather than a walk through the service, because the property that matters is
 * a property of the *graph*: there must be no path from a freshly received case to `abandoned` that
 * does not pass through the owner having been contacted. 4.10 calls the contact attempt the step
 * most likely to be skipped, and an extension with no release for three years may simply be
 * finished - the marker at the end of this process is public and commercial, so the ordering is
 * not a nicety.
 *
 * The service checks `contact_time` as well, because a status is a column somebody can edit and a
 * timestamp is a record of something that happened. This covers the other half: that the graph
 * itself offers no shortcut.
 *
 * @since 4.1.0
 */
final class CaseStatusTest extends TestCase
{
    /**
     * Every legal move, exhaustively.
     *
     * @return array<string, array{0: CaseStatus, 1: CaseStatus}>
     *
     * @since 4.1.0
     */
    public static function legalMoves(): array
    {
        return [
            'received -> reviewing'             => [CaseStatus::RECEIVED, CaseStatus::REVIEWING],
            'received -> owner contacted'       => [CaseStatus::RECEIVED, CaseStatus::OWNER_CONTACTED],
            'received -> resolved'              => [CaseStatus::RECEIVED, CaseStatus::RESOLVED],
            'received -> dismissed'             => [CaseStatus::RECEIVED, CaseStatus::DISMISSED],
            'reviewing -> owner contacted'      => [CaseStatus::REVIEWING, CaseStatus::OWNER_CONTACTED],
            'reviewing -> resolved'             => [CaseStatus::REVIEWING, CaseStatus::RESOLVED],
            'contacted -> grace expired'        => [CaseStatus::OWNER_CONTACTED, CaseStatus::GRACE_EXPIRED],
            'contacted -> abandoned'            => [CaseStatus::OWNER_CONTACTED, CaseStatus::ABANDONED],
            // The developer answered inside the grace period - the outcome the process exists to
            // make possible, and the one that must never be harder to record than marking.
            'contacted -> resolved'             => [CaseStatus::OWNER_CONTACTED, CaseStatus::RESOLVED],
            'grace expired -> abandoned'        => [CaseStatus::GRACE_EXPIRED, CaseStatus::ABANDONED],
            'grace expired -> resolved'         => [CaseStatus::GRACE_EXPIRED, CaseStatus::RESOLVED],
            // Step 5: a new maintainer takes over a listing that is already marked.
            'abandoned -> resolved'             => [CaseStatus::ABANDONED, CaseStatus::RESOLVED],
            'abandoned -> dismissed'            => [CaseStatus::ABANDONED, CaseStatus::DISMISSED],
        ];
    }

    /**
     * @dataProvider legalMoves
     *
     * @param CaseStatus $from The starting status.
     * @param CaseStatus $to   The target.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testLegalMovesAreAllowed(CaseStatus $from, CaseStatus $to): void
    {
        $this->assertTrue($from->canMoveTo($to), $from->value . ' should reach ' . $to->value);
    }

    /**
     * The moves that must not exist. The first four are the whole point of the enum.
     *
     * @return array<string, array{0: CaseStatus, 1: CaseStatus}>
     *
     * @since 4.1.0
     */
    public static function illegalMoves(): array
    {
        return [
            'received cannot jump to abandoned'      => [CaseStatus::RECEIVED, CaseStatus::ABANDONED],
            'reviewing cannot jump to abandoned'     => [CaseStatus::REVIEWING, CaseStatus::ABANDONED],
            'received cannot skip to grace expired'  => [CaseStatus::RECEIVED, CaseStatus::GRACE_EXPIRED],
            'reviewing cannot skip to grace expired' => [CaseStatus::REVIEWING, CaseStatus::GRACE_EXPIRED],
            // A closed case is closed. A later signal opens a new one, so that an extension
            // adopted once and abandoned again reads as two events, not one long one.
            'resolved is terminal'                   => [CaseStatus::RESOLVED, CaseStatus::RECEIVED],
            'resolved cannot be re-marked'           => [CaseStatus::RESOLVED, CaseStatus::ABANDONED],
            'dismissed is terminal'                  => [CaseStatus::DISMISSED, CaseStatus::REVIEWING],
            'a case cannot go back to received'      => [CaseStatus::OWNER_CONTACTED, CaseStatus::RECEIVED],
            'abandoned does not reopen for contact'  => [CaseStatus::ABANDONED, CaseStatus::OWNER_CONTACTED],
        ];
    }

    /**
     * @dataProvider illegalMoves
     *
     * @param CaseStatus $from The starting status.
     * @param CaseStatus $to   The target.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testIllegalMovesAreRefused(CaseStatus $from, CaseStatus $to): void
    {
        $this->assertFalse($from->canMoveTo($to), $from->value . ' must not reach ' . $to->value);
    }

    /**
     * There is no route to `abandoned` that avoids `owner_contacted`.
     *
     * Proved by search rather than by inspection: a future status added to the enum with a careless
     * `allowedNext()` would open a path that reading the table would not obviously reveal.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testEveryPathToAbandonedPassesThroughOwnerContacted(): void
    {
        $paths = [];
        $walk  = static function (CaseStatus $at, array $sofar) use (&$walk, &$paths): void {
            $sofar[] = $at;

            if ($at === CaseStatus::ABANDONED) {
                $paths[] = $sofar;

                return;
            }

            foreach ($at->allowedNext() as $next) {
                // Cycle guard; the graph is acyclic today and this keeps the test honest if it
                // ever is not.
                if (!\in_array($next, $sofar, true)) {
                    $walk($next, $sofar);
                }
            }
        };

        $walk(CaseStatus::RECEIVED, []);

        $this->assertNotEmpty($paths, 'abandoned must be reachable at all, or the process is broken');

        foreach ($paths as $path) {
            $this->assertContains(
                CaseStatus::OWNER_CONTACTED,
                $path,
                'reached abandoned via ' . implode(' -> ', array_map(static fn(CaseStatus $s): string => $s->value, $path))
                . ' without contacting the owner'
            );
        }
    }

    /**
     * The open set and the generated column in the schema have to agree, or the duplicate rule
     * enforces something different from what the code believes.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testOpenSetMatchesTheSchemaGeneratedColumn(): void
    {
        $sql = file_get_contents(
            \dirname(__DIR__, 3) . '/src/administrator/components/com_abandonware/sql/install.mysql.utf8.sql'
        );

        $this->assertIsString($sql);

        foreach (CaseStatus::cases() as $status) {
            $inSchema = str_contains($sql, "'" . $status->value . "'");

            $this->assertSame(
                $status->isOpen(),
                $inSchema,
                $status->value . ($status->isOpen() ? ' is open but missing from' : ' is closed but listed in')
                . ' the open_extension_id generated column'
            );
        }
    }

    /**
     * `abandoned` counts as open. It looks wrong and is not: a marked extension can still be
     * adopted, and until it is, a second signal about it must find the existing case rather than
     * open a parallel one.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testAbandonedStillCountsAsOpen(): void
    {
        $this->assertTrue(CaseStatus::ABANDONED->isOpen());
        $this->assertFalse(CaseStatus::RESOLVED->isOpen());
        $this->assertFalse(CaseStatus::DISMISSED->isOpen());
    }

    /**
     * A duplicate and an abusive report are not outcomes of the process - they are reasons the case
     * should not have existed. They must not land in the counts that describe how it performed.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testDismissiveResolutionsCloseAsDismissed(): void
    {
        $this->assertSame(CaseStatus::DISMISSED, Resolution::DUPLICATE->closingStatus());
        $this->assertSame(CaseStatus::DISMISSED, Resolution::ABUSE->closingStatus());

        foreach ([Resolution::TRANSFERRED, Resolution::DEVELOPER_RESPONDED, Resolution::NOT_ABANDONED, Resolution::NO_LONGER_LISTED] as $resolution) {
            $this->assertSame(CaseStatus::RESOLVED, $resolution->closingStatus(), $resolution->value);
        }
    }

    /**
     * Every resolution must be reachable from a marked case, or an outcome exists that cannot be
     * recorded - which is how a team ends up leaving cases open instead.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testEveryResolutionIsReachableFromAbandoned(): void
    {
        foreach (Resolution::cases() as $resolution) {
            $this->assertTrue(
                CaseStatus::ABANDONED->canMoveTo($resolution->closingStatus()),
                $resolution->value . ' cannot be recorded on a marked case'
            );
        }
    }

    /**
     * Language keys are derived, not typed. A missing one shows up as a raw constant on the page.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testEveryLabelHasALanguageString(): void
    {
        $ini = file_get_contents(
            \dirname(__DIR__, 3) . '/src/administrator/components/com_abandonware/language/en-GB/com_abandonware.ini'
        );

        $this->assertIsString($ini);

        foreach (CaseStatus::cases() as $status) {
            $this->assertStringContainsString($status->label() . '="', $ini, $status->label());
        }

        foreach (Resolution::cases() as $resolution) {
            $this->assertStringContainsString($resolution->label() . '="', $ini, $resolution->label());
        }
    }
}
