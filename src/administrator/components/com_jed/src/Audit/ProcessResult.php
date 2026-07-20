<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Audit;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The result of a {@see ProcessRunner::run()} call.
 *
 * @since 4.1.0
 */
final class ProcessResult
{
    /**
     * @param int    $exitCode The process exit code.
     * @param string $stdout   Captured standard output.
     * @param string $stderr   Captured standard error.
     *
     * @since 4.1.0
     */
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr
    ) {
    }

    /**
     * @return bool True if the process exited with code 0.
     *
     * @since 4.1.0
     */
    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
