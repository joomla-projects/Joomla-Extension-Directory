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

use Jed\Component\Vel\Administrator\Enum\Severity;

/**
 * CVSS v3.1 base score, straight from the FIRST specification (section 7.1) including its
 * Roundup function. Temporal and environmental metrics are accepted in the vector and ignored
 * for the score - the VEL stores and filters on the base score only.
 *
 * @since 4.0.0
 */
final class Cvss31
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
        'PR' => ['N', 'L', 'H'],
        'UI' => ['N', 'R'],
        'S'  => ['U', 'C'],
        'C'  => ['H', 'L', 'N'],
        'I'  => ['H', 'L', 'N'],
        'A'  => ['H', 'L', 'N'],
    ];

    /**
     * Temporal and environmental metrics, accepted and carried but not scored.
     *
     * @var string[]
     *
     * @since 4.0.0
     */
    private const OPTIONAL = ['E', 'RL', 'RC', 'CR', 'IR', 'AR', 'MAV', 'MAC', 'MPR', 'MUI', 'MS', 'MC', 'MI', 'MA'];

    /**
     * @param string $vector A "CVSS:3.1/..." (or 3.0) vector.
     *
     * @return CvssResult
     *
     * @throws CvssException  Not a valid v3.x vector.
     *
     * @since 4.0.0
     */
    public static function evaluate(string $vector): CvssResult
    {
        $metrics = self::parse($vector);

        $av = ['N' => 0.85, 'A' => 0.62, 'L' => 0.55, 'P' => 0.2][$metrics['AV']];
        $ac = ['L' => 0.77, 'H' => 0.44][$metrics['AC']];
        $ui = ['N' => 0.85, 'R' => 0.62][$metrics['UI']];

        $changed = $metrics['S'] === 'C';
        $pr      = $changed
            ? ['N' => 0.85, 'L' => 0.68, 'H' => 0.5][$metrics['PR']]
            : ['N' => 0.85, 'L' => 0.62, 'H' => 0.27][$metrics['PR']];

        $cia = ['H' => 0.56, 'L' => 0.22, 'N' => 0.0];
        $iss = 1 - ((1 - $cia[$metrics['C']]) * (1 - $cia[$metrics['I']]) * (1 - $cia[$metrics['A']]));

        $impact = $changed
            ? 7.52 * ($iss - 0.029) - 3.25 * (($iss - 0.02) ** 15)
            : 6.42 * $iss;

        $exploitability = 8.22 * $av * $ac * $pr * $ui;

        if ($impact <= 0) {
            $score = 0.0;
        } elseif ($changed) {
            $score = self::roundUp(min(1.08 * ($impact + $exploitability), 10));
        } else {
            $score = self::roundUp(min($impact + $exploitability, 10));
        }

        $canonical = 'CVSS:3.1';

        foreach (array_keys(self::BASE) as $key) {
            $canonical .= '/' . $key . ':' . $metrics[$key];
        }

        foreach (self::OPTIONAL as $key) {
            if (isset($metrics[$key])) {
                $canonical .= '/' . $key . ':' . $metrics[$key];
            }
        }

        return new CvssResult('3.1', $canonical, $score, Severity::fromScore($score), array_intersect_key($metrics, self::BASE));
    }

    /**
     * @param string $vector The vector.
     *
     * @return array<string, string>  Metric => value.
     *
     * @throws CvssException
     *
     * @since 4.0.0
     */
    public static function parse(string $vector): array
    {
        $vector = trim($vector);

        if (!preg_match('/^CVSS:3\.[01]\//', $vector)) {
            throw new CvssException('COM_VEL_CVSS_ERROR_NOT_V3', [$vector]);
        }

        $metrics = [];

        foreach (explode('/', substr($vector, 9)) as $part) {
            if (!preg_match('/^([A-Z]{1,3}):([A-Z])$/', $part, $m)) {
                throw new CvssException('COM_VEL_CVSS_ERROR_MALFORMED_METRIC', [$part]);
            }

            [, $key, $value] = $m;

            if (isset(self::BASE[$key])) {
                if (!\in_array($value, self::BASE[$key], true)) {
                    throw new CvssException('COM_VEL_CVSS_ERROR_INVALID_VALUE', [$key, $value]);
                }
            } elseif (!\in_array($key, self::OPTIONAL, true)) {
                throw new CvssException('COM_VEL_CVSS_ERROR_UNKNOWN_METRIC', [$key]);
            }

            if (isset($metrics[$key])) {
                throw new CvssException('COM_VEL_CVSS_ERROR_DUPLICATE_METRIC', [$key]);
            }

            $metrics[$key] = $value;
        }

        foreach (array_keys(self::BASE) as $key) {
            if (!isset($metrics[$key])) {
                throw new CvssException('COM_VEL_CVSS_ERROR_MISSING_METRIC', [$key]);
            }
        }

        return $metrics;
    }

    /**
     * CVSS v3.1 Roundup, appendix A of the specification. Rounds up to one decimal place in a
     * way that is stable across floating-point implementations.
     *
     * @param float $input The value.
     *
     * @return float
     *
     * @since 4.0.0
     */
    public static function roundUp(float $input): float
    {
        $int = (int) round($input * 100000);

        if ($int % 10000 === 0) {
            return $int / 100000.0;
        }

        return (floor($int / 10000) + 1) / 10.0;
    }
}
