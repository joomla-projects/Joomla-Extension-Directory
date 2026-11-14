<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Helper;

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * JED Extension Trophy Helper
 *
 * trophy icons (e.g. popular, or version numbers, or module/component
 *
 * @package JED
 * @since   4.0.0
 */
class JedtrophyHelper
{
    /**
     * The `extension_types` tokens that appear in the JED3 data, in display order.
     *
     * Occurrences across the legacy stock: mod 7,874 · plugin 7,210 · com 3,593 · ext 1,042 ·
     * esp 138 · lang 16. Labels come from the existing COM_JED_EXTENSION_INCLUDES__LABEL*
     * keys, the same convention getTrophyIncludesString() already builds by hand.
     *
     * "ext" and "esp" are placeholders - their intended meaning is not confirmed, so their
     * strings are provisional and expected to be renamed. A token not listed here still
     * renders, as its raw value, rather than disappearing.
     *
     * @var string[]
     *
     * @since 4.0.0
     */
    private const INCLUDE_TOKENS = ['com', 'mod', 'plugin', 'ext', 'esp', 'lang'];

    /**
     * @param $versionstr
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function getTrophyVersionsString($versionstr): string
    {
        //  echo $versionstr;exit();
        $l_version = str_replace('[', '', $versionstr);

        $l_version = str_replace(']', '', $l_version);
        $l_version = str_replace('"', '', $l_version);

        $trophies = explode(',', $l_version);

        $output = ''; //<div class="trophies versions">';
        foreach ($trophies as $v) {
            $title = Text::_('COM_JED_VERSION_' . $v);
            // Version number, with the "backward-compatibility plugin" marker on the x.1 rows.
            $txt   = match ($v) {
                '30'    => '3',
                '40'    => '4',
                '41'    => '4.1',
                '50'    => '5',
                '51'    => '5 ' . Text::_('COM_JED_VERSION_BADGE_BC'),
                '60'    => '6',
                '61'    => '6 ' . Text::_('COM_JED_VERSION_BADGE_BC'),
                default => '',
            };

            if ($txt !== '') {
                $txt = '<span class="fab fa-joomla"></span>&nbsp;' . $txt . '&nbsp;';
            }

            $output .= '<span title="' . $title . '" class="joomla-version-badge">' . $txt . '</span>';
        }

        //$output .= '</div>';
        return $output;
    }

    /**
     * @param $includestr
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function getTrophyIncludesString($includestr): string
    {
        $l_include = str_replace('[', '', $includestr);

        $l_include = str_replace(']', '', $l_include);
        $l_include = str_replace('"', '', $l_include);
        $trophies  = explode(',', $l_include);

        $output = '<div class="trophies includes">';
        foreach ($trophies as $v) {
            $title = Text::_('COM_JED_EXTENSION_INCLUDES__LABEL' . strtoupper($v));
            $output .= '<span class="hasTooltip" data-toggle="tooltip" title="' . $title . '">	<span  class="badge badge-' . $v . '">' . strtoupper(substr($v, 0, 1)) . '</span>	</span>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * @param $includestr
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function getTrophyIncludesStringFull($includestr): string
    {
        $trophies = (array) json_decode((string) $includestr);

        $output = [];

        foreach ($trophies as $v) {
            $token = strtolower(trim((string) $v));

            if ($token === '') {
                continue;
            }

            // An unrecognised token is shown as-is rather than dropped. The switch this
            // replaced covered only com/mod/plugin, so the ~1,200 listings carrying "ext",
            // "esp" or "lang" lost the entry silently - and a listing whose only token was
            // one of those rendered an empty Includes field.
            $output[] = in_array($token, self::INCLUDE_TOKENS, true)
                ? Text::_('COM_JED_EXTENSION_INCLUDES__LABEL' . strtoupper($token))
                : $token;
        }

        return implode(', ', $output);
    }

    /**
     * @param $versionstr
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function getTrophyVersionsStringFull($versionstr): string
    {
        //  echo $versionstr;exit();
        $l_version = str_replace('[', '', $versionstr);

        $l_version = str_replace(']', '', $l_version);
        $l_version = str_replace('"', '', $l_version);

        $trophies = explode(',', $l_version);

        $output      = '';//<div class="trophies versions">';
        $comma_count = 0;
        foreach ($trophies as $v) {
            $title = 'Joomla!&nbsp;' . ((float)$v) / 10;
            $comma_count++;

            if ($comma_count > 1) {
                $output .= '<br />' . $title;
            } else {
                $output .= $title;
            }
        }

        //$output .= '</div>';
        return $output;
    }
}
