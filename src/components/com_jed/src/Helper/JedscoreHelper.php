<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * JED Extension Score Helper
 *
 * @package JED
 * @since   4.0.0
 */
class JedscoreHelper
{
    /**
     * @param float $score
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function getStars(float $score = 0): string
    {
        if ($score == 0) {
            return 'Not Rated';
        }

        if (!is_numeric($score)) {
            return '';
        }

        $whole = floor($score);
        $half  = $score > $whole ? 1 : 0;
        $empty = 5 - $whole - $half;

        $html = '<div class="stars stars-' . self::getClass($score) . '">';
        $html .= str_repeat('<span class="star star-full fa fa-star"></span>', $whole);
        $html .= str_repeat('<span class="star star-half fa fa-star-half"></span>', $half);
        $html .= str_repeat('<span class="star star-empty fa fa-star opacity-0"></span>', $empty);
        $html .= '</div>';

        return $html;
    }

    /**
     * @param float $score A rating from 0 to 5.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function getStarsShort(float $score = 0): string
    {
        if (!$score) {
            return '';
        }

        $html = '<div class="stars-short stars-' . self::getClass($score) . '">';
        $html .= '<span class="fa fa-star"></span>';
        $html .= $score;
        $html .= '</div>';

        return $html;
    }

    /**
     * @param float $score A rating from 0 to 5.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function getClass(float $score): string
    {
        if (!$score) {
            return 'none';
        }

        if ($score <= 2) {
            return 'low';
        }

        if ($score <= 4) {
            return 'medium';
        }

        return 'high';
    }
}
