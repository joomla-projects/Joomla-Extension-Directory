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


/**
 * The one entry point the rest of the component uses: hand it whatever vector was typed or
 * imported and get back version, canonical vector, score and severity.
 *
 * @since 4.0.0
 */
final class Cvss
{
    /**
     * @param string     $vector        The vector as typed or imported.
     * @param float|null $suppliedScore A score supplied alongside it (required for 4.0, checked
     *                                  against the computed one for 3.x).
     *
     * @return CvssResult
     *
     * @throws CvssException
     *
     * @since 4.0.0
     */
    public static function evaluate(string $vector, ?float $suppliedScore = null): CvssResult
    {
        $vector = trim($vector);

        if (str_starts_with($vector, 'CVSS:4.0/')) {
            return Cvss40::evaluate($vector, $suppliedScore);
        }

        if (preg_match('/^CVSS:3\.[01]\//', $vector)) {
            $result = Cvss31::evaluate($vector);

            // A typed score that disagrees with the vector is a typo in one of them, and the
            // record must not carry both. The vector wins, and the caller is told.
            if ($suppliedScore !== null && abs(round($suppliedScore, 1) - $result->score) > 0.05) {
                throw new CvssException('COM_VEL_CVSS_ERROR_SCORE_MISMATCH', [number_format($suppliedScore, 1), number_format($result->score, 1)]);
            }

            return $result;
        }

        throw new CvssException('COM_VEL_CVSS_ERROR_UNRECOGNISED', [$vector]);
    }

    /**
     * @param string $vector The vector.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function isValid(string $vector): bool
    {
        try {
            $vector = trim($vector);

            if (str_starts_with($vector, 'CVSS:4.0/')) {
                Cvss40::parse($vector);
            } else {
                Cvss31::parse($vector);
            }

            return true;
        } catch (CvssException $e) {
            return false;
        }
    }
}
