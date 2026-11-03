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
 * A parsed and scored CVSS vector.
 *
 * @since 4.0.0
 */
final class CvssResult
{
    /**
     * @param string               $version  "3.1" or "4.0".
     * @param string               $vector   The vector, normalised (metrics in canonical order).
     * @param float                $score    The base score, 0.0-10.0.
     * @param Severity             $severity The qualitative band.
     * @param array<string,string> $metrics  Metric => value, base metrics only.
     *
     * @since 4.0.0
     */
    public function __construct(
        public readonly string $version,
        public readonly string $vector,
        public readonly float $score,
        public readonly Severity $severity,
        public readonly array $metrics
    ) {
    }
}
