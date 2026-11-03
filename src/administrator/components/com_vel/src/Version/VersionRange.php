<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Version;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * An inclusive affected range, "from" to "to", either side possibly unbounded.
 *
 * @since 4.0.0
 */
final class VersionRange
{
    /**
     * @param Version|null $from Inclusive lower bound, or null for unbounded.
     * @param Version|null $to   Inclusive upper bound, or null for unbounded.
     *
     * @since 4.0.0
     */
    public function __construct(
        public readonly ?Version $from,
        public readonly ?Version $to
    ) {
    }

    /**
     * @param string $from The stored lower bound ("*" or a version).
     * @param string $to   The stored upper bound.
     *
     * @return self
     *
     * @since 4.0.0
     */
    public static function fromStrings(string $from, string $to): self
    {
        return new self(Version::parse($from), Version::parse($to));
    }

    /**
     * @param Version $version A version.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function contains(Version $version): bool
    {
        if ($this->from !== null && $version->compare($this->from) < 0) {
            return false;
        }

        if ($this->to !== null && $version->compare($this->to) > 0) {
            return false;
        }

        return true;
    }

    /**
     * @return bool  Whether the range has no upper bound - nothing fixed yet.
     *
     * @since 4.0.0
     */
    public function isOpenEnded(): bool
    {
        return $this->to === null;
    }

    /**
     * @return string  Human form, e.g. "1.0.0 – 2.3.4", "≤ 2.3.4", "≥ 1.0.0", "all versions".
     *
     * @since 4.0.0
     */
    public function __toString(): string
    {
        if ($this->from === null && $this->to === null) {
            return 'all versions';
        }

        if ($this->from === null) {
            return '<= ' . $this->to;
        }

        if ($this->to === null) {
            return '>= ' . $this->from;
        }

        return $this->from . ' - ' . $this->to;
    }
}
