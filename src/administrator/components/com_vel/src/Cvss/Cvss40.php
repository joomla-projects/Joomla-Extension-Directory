<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Cvss;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use InvalidArgumentException;
use Jed\Component\Vel\Administrator\Enum\Severity;

/**
 * CVSS v4.0 vector parsing and validation.
 *
 * Scoring a v4.0 vector needs the MacroVector lookup table from the FIRST specification - 270
 * entries that have to be copied exactly, not derived. Until that table is vendored from FIRST's
 * reference implementation (research task R-01), a 4.0 vector is accepted with the base score the
 * editor reads off the FIRST calculator, and this class validates the vector and derives the
 * severity band. {@see self::evaluate()} refuses a 4.0 vector without a supplied score rather
 * than guess.
 *
 * @since 4.0.0
 */
final class Cvss40
{
    /**
     * The base metrics in canonical order with their allowed values.
     *
     * @var array<string, string[]>
     *
     * @since 4.0.0
     */
    public const BASE = [
        'AV' => ['N', 'A', 'L', 'P'],
        'AC' => ['L', 'H'],
        'AT' => ['N', 'P'],
        'PR' => ['N', 'L', 'H'],
        'UI' => ['N', 'P', 'A'],
        'VC' => ['H', 'L', 'N'],
        'VI' => ['H', 'L', 'N'],
        'VA' => ['H', 'L', 'N'],
        'SC' => ['H', 'L', 'N'],
        'SI' => ['H', 'L', 'N'],
        'SA' => ['H', 'L', 'N'],
    ];

    /**
     * Threat, environmental and supplemental metrics: accepted, carried, not scored.
     *
     * @var string[]
     *
     * @since 4.0.0
     */
    private const OPTIONAL = [
        'E', 'CR', 'IR', 'AR', 'MAV', 'MAC', 'MAT', 'MPR', 'MUI', 'MVC', 'MVI', 'MVA', 'MSC', 'MSI', 'MSA',
        'S', 'AU', 'R', 'V', 'RE', 'U',
    ];

    /**
     * @param string     $vector A "CVSS:4.0/..." vector.
     * @param float|null $score  The base score from the FIRST calculator.
     *
     * @return CvssResult
     *
     * @throws InvalidArgumentException  Not a valid v4.0 vector, or no score supplied.
     *
     * @since 4.0.0
     */
    public static function evaluate(string $vector, ?float $score = null): CvssResult
    {
        $metrics = self::parse($vector);

        if ($score === null) {
            throw new InvalidArgumentException('A CVSS v4.0 vector needs its base score supplied; see Cvss40.');
        }

        if ($score < 0 || $score > 10) {
            throw new InvalidArgumentException('CVSS score out of range: ' . $score);
        }

        $score = round($score, 1);

        $canonical = 'CVSS:4.0';

        foreach (array_keys(self::BASE) as $key) {
            $canonical .= '/' . $key . ':' . $metrics[$key];
        }

        foreach (self::OPTIONAL as $key) {
            if (isset($metrics[$key])) {
                $canonical .= '/' . $key . ':' . $metrics[$key];
            }
        }

        return new CvssResult('4.0', $canonical, $score, Severity::fromScore($score), array_intersect_key($metrics, self::BASE));
    }

    /**
     * @param string $vector The vector.
     *
     * @return array<string, string>  Metric => value.
     *
     * @throws InvalidArgumentException
     *
     * @since 4.0.0
     */
    public static function parse(string $vector): array
    {
        $vector = trim($vector);

        if (!str_starts_with($vector, 'CVSS:4.0/')) {
            throw new InvalidArgumentException('Not a CVSS v4.0 vector: ' . $vector);
        }

        $metrics = [];

        foreach (explode('/', substr($vector, 9)) as $part) {
            if (!preg_match('/^([A-Z]{1,3}):([A-Z])$/', $part, $m)) {
                throw new InvalidArgumentException('Malformed CVSS metric: ' . $part);
            }

            [, $key, $value] = $m;

            if (isset(self::BASE[$key])) {
                if (!\in_array($value, self::BASE[$key], true)) {
                    throw new InvalidArgumentException('Invalid value for ' . $key . ': ' . $value);
                }
            } elseif (!\in_array($key, self::OPTIONAL, true)) {
                throw new InvalidArgumentException('Unknown CVSS metric: ' . $key);
            }

            if (isset($metrics[$key])) {
                throw new InvalidArgumentException('Duplicate CVSS metric: ' . $key);
            }

            $metrics[$key] = $value;
        }

        foreach (array_keys(self::BASE) as $key) {
            if (!isset($metrics[$key])) {
                throw new InvalidArgumentException('Missing CVSS base metric: ' . $key);
            }
        }

        return $metrics;
    }
}
