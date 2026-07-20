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

use RuntimeException;

/**
 * A narrow `proc_open()` wrapper for running external commands (e.g. `docker run`)
 * with a hard timeout. Deliberately not a general-purpose process library
 * (e.g. symfony/process) to avoid adding a new runtime dependency to the shipped
 * JED extension package for this one narrow use.
 *
 * @since 4.1.0
 */
class ProcessRunner
{
    /**
     * Run a command and wait for it to finish, or kill it after the timeout.
     *
     * @param string[]    $command        The command and its arguments (no shell involved).
     * @param string|null $cwd            The working directory, or null to inherit.
     * @param int         $timeoutSeconds Maximum time to wait before killing the process.
     *
     * @return ProcessResult
     *
     * @throws RuntimeException If the process could not be started, or timed out.
     *
     * @since 4.1.0
     */
    public function run(array $command, ?string $cwd = null, int $timeoutSeconds = 900): ProcessResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd);

        if (!\is_resource($process)) {
            throw new RuntimeException('Failed to start process: ' . implode(' ', $command));
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start  = time();

        while (true) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            $status = proc_get_status($process);

            if (!$status['running']) {
                break;
            }

            if ((time() - $start) > $timeoutSeconds) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                throw new RuntimeException(\sprintf(
                    'Process timed out after %d seconds: %s',
                    $timeoutSeconds,
                    implode(' ', $command)
                ));
            }

            usleep(100000);
        }

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ProcessResult($exitCode, $stdout, $stderr);
    }
}
