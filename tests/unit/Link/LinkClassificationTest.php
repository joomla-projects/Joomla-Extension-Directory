<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Link;

use Jed\Component\Jed\Administrator\Link\LinkField;
use Jed\Component\Jed\Administrator\Link\LinkStatus;
use Jed\Component\Jed\Administrator\Url\UrlCheckResult;
use PHPUnit\Framework\TestCase;

/**
 * How a validator's answer becomes an escalation, or does not.
 *
 * This is where `P1-09` is most easily got wrong, and the failure is quiet in both directions: too
 * eager and the JED team is buried in alerts about sites that work fine, at which point they stop
 * reading them; too lax and a listing whose download has been dead for months sits there and
 * nobody hears about it.
 *
 * Both classes are pure, so this can be a table rather than a simulation.
 *
 * @since 4.0.0
 */
final class LinkClassificationTest extends TestCase
{
    /**
     * Validator outcomes and the class each must land in.
     *
     * @return array<string, array{0: string, 1: LinkStatus}>
     *
     * @since 4.0.0
     */
    public static function outcomes(): array
    {
        return [
            // Gone. The target does not exist, and no amount of patience changes that.
            '404'                       => ['COM_JED_URLCHECK_NOT_FOUND', LinkStatus::HARD],
            'domain does not resolve'   => ['COM_JED_URLCHECK_FAILED_DNS', LinkStatus::HARD],
            'domain gone, at refusal'   => ['COM_JED_URLCHECK_REFUSED_DNS', LinkStatus::HARD],
            'connection refused'        => ['COM_JED_URLCHECK_FAILED_UNREACHABLE', LinkStatus::HARD],
            'points into private space' => ['COM_JED_URLCHECK_REFUSED_PRIVATE_ADDRESS', LinkStatus::HARD],
            'redirect loop'             => ['COM_JED_URLCHECK_REFUSED_REDIRECT_LOOP', LinkStatus::HARD],
            'no longer a valid URL'     => ['COM_JED_URLCHECK_REFUSED_FORMAT', LinkStatus::HARD],
            'repository not public'     => ['COM_JED_URLCHECK_GIT_NOT_PUBLIC', LinkStatus::HARD],

            // Turned away. Extremely common against a checker arriving from a datacentre with an
            // unfamiliar user agent, and says nothing about whether the site works for people.
            '403 or 429'                => ['COM_JED_URLCHECK_TURNED_AWAY', LinkStatus::SOFT],
            'timeout'                   => ['COM_JED_URLCHECK_FAILED_TIMEOUT', LinkStatus::SOFT],
            'certificate trouble'       => ['COM_JED_URLCHECK_FAILED_TLS', LinkStatus::SOFT],
            'a 5xx'                     => ['COM_JED_URLCHECK_SERVER_ERROR', LinkStatus::SOFT],
            'an odd status'             => ['COM_JED_URLCHECK_UNEXPECTED_STATUS', LinkStatus::SOFT],
            'the check itself failed'   => ['COM_JED_URLCHECK_FAILED_FAILED', LinkStatus::SOFT],

            // Answered, but the document is not what the field promises. The developer can nearly
            // always fix these, and they are the ones with real consequences for their users.
            'update feed is empty'      => ['COM_JED_URLCHECK_UPDATE_EMPTY', LinkStatus::SEMANTIC],
            'update feed has no entries' => ['COM_JED_URLCHECK_UPDATE_NO_ENTRIES', LinkStatus::SEMANTIC],
            'update feed is a manifest' => ['COM_JED_URLCHECK_UPDATE_IS_MANIFEST', LinkStatus::SEMANTIC],
            'update feed is behind'     => ['COM_JED_URLCHECK_UPDATE_BEHIND', LinkStatus::SEMANTIC],
            'changelog is malformed'    => ['COM_JED_URLCHECK_CHANGELOG_MALFORMED', LinkStatus::SEMANTIC],

            // Answered, and the note is an observation rather than a problem. The first pass over
            // the real catalogue produced this list: nearly every paid extension answers its
            // download link with a product page, and counting those as failures made the
            // moderation filter match almost everything.
            'download is a product page' => ['COM_JED_URLCHECK_DOWNLOAD_IS_PAGE', LinkStatus::OK],
            'a page link returns a file' => ['COM_JED_URLCHECK_NOT_A_PAGE', LinkStatus::OK],
            'a human-readable changelog' => ['COM_JED_URLCHECK_CHANGELOG_NOT_XML', LinkStatus::OK],
            'a collection update file'   => ['COM_JED_URLCHECK_UPDATE_IS_COLLECTION', LinkStatus::OK],

            // Anything nobody has thought about. It must never be able to open a case by itself.
            'something new'             => ['COM_JED_URLCHECK_SOMETHING_ADDED_LATER', LinkStatus::SOFT],
        ];
    }

    /**
     * @dataProvider outcomes
     *
     * @param string     $message  The validator's language key.
     * @param LinkStatus $expected The class it must land in.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testClassifiesEveryOutcome(string $message, LinkStatus $expected): void
    {
        $this->assertSame(
            $expected,
            LinkStatus::fromResult(UrlCheckResult::notice($message)),
            $message
        );
    }

    /**
     * A validator that says "ok" is ok, whatever the message.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testASuccessIsAlwaysOk(): void
    {
        $this->assertSame(LinkStatus::OK, LinkStatus::fromResult(UrlCheckResult::ok('COM_JED_URLCHECK_OK')));
        $this->assertSame(LinkStatus::OK, LinkStatus::fromResult(UrlCheckResult::ok('COM_JED_URLCHECK_UPDATE_OK')));
    }

    /**
     * Only the two classes that mean something count.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testOnlyHardAndSemanticCount(): void
    {
        $this->assertTrue(LinkStatus::HARD->counts());
        $this->assertTrue(LinkStatus::SEMANTIC->counts());
        $this->assertFalse(LinkStatus::SOFT->counts());
        $this->assertFalse(LinkStatus::OK->counts());
    }

    /**
     * Every checked column resolves to a registered validator and a usable weight.
     *
     * Cheap, and it catches the one mistake this table invites: adding a column and forgetting
     * one half of the pair, which would make that link silently never checked or never counted.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testEveryFieldIsFullyDescribed(): void
    {
        $known = ['reachable', 'updateserver', 'changelog', 'download', 'git'];

        foreach (LinkField::all() as $field) {
            $this->assertContains(LinkField::validator($field), $known, $field);
            $this->assertGreaterThan(0.0, LinkField::weight($field), $field);
            $this->assertLessThanOrEqual(1.0, LinkField::weight($field), $field);
        }
    }

    /**
     * What the weights actually mean, in runs.
     *
     * At the three-day interval each run is three days, so these are the durations a developer
     * and the team experience: a dead download link is heard about after nine days, a dead demo
     * link after eighteen.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testWeightsTurnIntoRunCounts(): void
    {
        $runsToReach = static function (string $field, int $threshold): int {
            for ($i = 1; $i <= 100; $i++) {
                if (LinkField::reaches($field, $i, $threshold)) {
                    return $i;
                }
            }

            return 0;
        };

        // Developer threshold.
        $this->assertSame(3, $runsToReach('download_url', 3), 'download');
        $this->assertSame(3, $runsToReach('update_url', 3), 'update server');
        $this->assertSame(4, $runsToReach('developer_url', 3), 'website');
        $this->assertSame(4, $runsToReach('support_url', 3), 'support');
        $this->assertSame(6, $runsToReach('demo_url', 3), 'demo');
        $this->assertSame(6, $runsToReach('documentation_url', 3), 'documentation');

        // Team threshold - always further out than the developer's, for every field, which is
        // what "developer first" means in practice.
        foreach (LinkField::all() as $field) {
            $this->assertGreaterThan(
                $runsToReach($field, 3),
                $runsToReach($field, 6),
                $field . ': the team must never hear before the developer'
            );
        }
    }

    /**
     * A column that is not in the table cannot escalate.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testAnUncheckedColumnNeverEscalates(): void
    {
        $this->assertFalse(LinkField::reaches('internal_download_url', 9999, 3));
        $this->assertFalse(LinkField::reaches('nonsense', 9999, 1));
        $this->assertSame('', LinkField::validator('internal_download_url'));
    }
}
