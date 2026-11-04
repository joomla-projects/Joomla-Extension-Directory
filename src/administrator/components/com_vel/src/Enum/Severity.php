<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Enum;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * CVSS qualitative severity, as FIRST defines it for 3.x and 4.0 alike.
 *
 * @since 4.0.0
 */
enum Severity: string
{
    case NONE     = 'none';
    case LOW      = 'low';
    case MEDIUM   = 'medium';
    case HIGH     = 'high';
    case CRITICAL = 'critical';

    /**
     * The band a base score falls in: 0 none, 0.1-3.9 low, 4.0-6.9 medium, 7.0-8.9 high,
     * 9.0-10.0 critical.
     *
     * @param float $score A base score.
     *
     * @return self
     *
     * @since 4.0.0
     */
    public static function fromScore(float $score): self
    {
        return match (true) {
            $score <= 0.0 => self::NONE,
            $score < 4.0  => self::LOW,
            $score < 7.0  => self::MEDIUM,
            $score < 9.0  => self::HIGH,
            default       => self::CRITICAL,
        };
    }

    /**
     * The order the public filter sorts them in.
     *
     * @return int
     *
     * @since 4.0.0
     */
    public function rank(): int
    {
        return match ($this) {
            self::NONE     => 0,
            self::LOW      => 1,
            self::MEDIUM   => 2,
            self::HIGH     => 3,
            self::CRITICAL => 4,
        };
    }

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_SEVERITY_' . strtoupper($this->value);
    }

    /**
     * @return string  Bootstrap badge colour. Text accompanies it everywhere - colour alone is not
     *                 accessible and not enough.
     *
     * @since 4.0.0
     */
    public function badge(): string
    {
        return match ($this) {
            self::NONE     => 'secondary',
            self::LOW      => 'info',
            self::MEDIUM   => 'warning text-dark',
            self::HIGH     => 'danger',
            self::CRITICAL => 'dark',
        };
    }

    /**
     * The CVE record's `baseSeverity` spelling.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function cveValue(): string
    {
        return strtoupper($this->value);
    }
}
