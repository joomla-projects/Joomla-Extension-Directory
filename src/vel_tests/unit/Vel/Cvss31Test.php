<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Vel;

use InvalidArgumentException;
use Jed\Component\Vel\Administrator\Cvss\Cvss;
use Jed\Component\Vel\Administrator\Cvss\Cvss31;
use Jed\Component\Vel\Administrator\Cvss\Cvss40;
use Jed\Component\Vel\Administrator\Cvss\CvssException;
use Jed\Component\Vel\Administrator\Enum\Severity;
use PHPUnit\Framework\TestCase;

/**
 * CVSS 3.1 scores against FIRST's published examples, and the 4.0 parser.
 */
final class Cvss31Test extends TestCase
{
    /**
     * Vectors and the scores FIRST's calculator gives them.
     *
     * @return array<string, array{string, float, string}>
     */
    public static function vectors(): array
    {
        return [
            'critical RCE'          => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 9.8, 'critical'],
            'reflected XSS'         => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N', 6.1, 'medium'],
            'stored XSS, low priv'  => ['CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N', 5.4, 'medium'],
            'SQLi authenticated'    => ['CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H', 8.8, 'high'],
            'info disclosure'       => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N', 5.3, 'medium'],
            'nothing'               => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N', 0.0, 'none'],
            'local low'             => ['CVSS:3.1/AV:L/AC:H/PR:H/UI:R/S:U/C:L/I:N/A:N', 1.8, 'low'],
            'scope changed high'    => ['CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:C/C:H/I:H/A:H', 9.9, 'critical'],
            'physical'              => ['CVSS:3.1/AV:P/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N', 4.6, 'medium'],
            'with temporal metrics' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H/E:P/RL:O/RC:C', 9.8, 'critical'],
        ];
    }

    /**
     * @dataProvider vectors
     */
    public function testScores(string $vector, float $score, string $severity): void
    {
        $result = Cvss31::evaluate($vector);

        self::assertSame($score, $result->score, $vector);
        self::assertSame($severity, $result->severity->value, $vector);
        self::assertSame('3.1', $result->version);
        self::assertStringStartsWith('CVSS:3.1/AV:', $result->vector);
    }

    public function testThreePointZeroIsAcceptedAndRestamped(): void
    {
        $result = Cvss31::evaluate('CVSS:3.0/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');

        self::assertSame(9.8, $result->score);
        self::assertStringStartsWith('CVSS:3.1/', $result->vector);
    }

    public function testCanonicalOrderIsRestored(): void
    {
        $result = Cvss31::evaluate('CVSS:3.1/C:H/I:H/A:H/AV:N/AC:L/PR:N/UI:N/S:U');

        self::assertSame('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', $result->vector);
    }

    public function testMissingMetricIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cvss31::evaluate('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H');
    }

    public function testInvalidValueIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cvss31::evaluate('CVSS:3.1/AV:X/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
    }

    public function testRefusalCarriesALanguageKeyAndItsArguments(): void
    {
        // The CVSS classes run without Joomla, so they throw the key and the model translates.
        try {
            Cvss31::evaluate('CVSS:3.1/AV:X/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
            self::fail('expected a CvssException');
        } catch (CvssException $e) {
            self::assertSame('COM_VEL_CVSS_ERROR_INVALID_VALUE', $e->getMessage());
            self::assertSame(['AV', 'X'], $e->args);
        }
    }

    public function testRoundUp(): void
    {
        self::assertSame(4.0, Cvss31::roundUp(4.0));
        self::assertSame(4.1, Cvss31::roundUp(4.02));
        self::assertSame(4.0, Cvss31::roundUp(4.000001));
        self::assertSame(4.1, Cvss31::roundUp(4.00002));
    }

    public function testFacadeRefusesMismatchedScore(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cvss::evaluate('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 5.0);
    }

    public function testFacadeAcceptsMatchingScore(): void
    {
        self::assertSame(9.8, Cvss::evaluate('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 9.8)->score);
    }

    public function testFourPointZeroNeedsASuppliedScore(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cvss40::evaluate('CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N');
    }

    public function testFourPointZeroWithScore(): void
    {
        $result = Cvss40::evaluate('CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N', 9.3);

        self::assertSame('4.0', $result->version);
        self::assertSame(9.3, $result->score);
        self::assertSame(Severity::CRITICAL, $result->severity);
    }

    public function testFourPointZeroRejectsUnknownMetric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cvss40::parse('CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N/ZZ:X');
    }

    public function testIsValid(): void
    {
        self::assertTrue(Cvss::isValid('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'));
        self::assertTrue(Cvss::isValid('CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N'));
        self::assertFalse(Cvss::isValid('AV:N/AC:L'));
        self::assertFalse(Cvss::isValid(''));
    }

    public function testSeverityBands(): void
    {
        self::assertSame(Severity::NONE, Severity::fromScore(0.0));
        self::assertSame(Severity::LOW, Severity::fromScore(0.1));
        self::assertSame(Severity::LOW, Severity::fromScore(3.9));
        self::assertSame(Severity::MEDIUM, Severity::fromScore(4.0));
        self::assertSame(Severity::MEDIUM, Severity::fromScore(6.9));
        self::assertSame(Severity::HIGH, Severity::fromScore(7.0));
        self::assertSame(Severity::HIGH, Severity::fromScore(8.9));
        self::assertSame(Severity::CRITICAL, Severity::fromScore(9.0));
        self::assertSame(Severity::CRITICAL, Severity::fromScore(10.0));
    }
}
