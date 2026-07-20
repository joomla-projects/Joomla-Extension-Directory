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
 * Runs the `jed-audit` Docker image against one job's input/output workspace.
 *
 * The container is started with `--network none`: it unzips and statically
 * analyzes untrusted third-party extension code, and everything it needs
 * (jorobo, phpstan, a Joomla core copy) is baked into the image at build time, so
 * no runtime network access is required or permitted.
 *
 * @since 4.1.0
 */
class DockerRunner
{
    /**
     * @param ProcessRunner $processRunner  Runs the `docker` CLI.
     * @param string        $dockerBinary   Path to the docker binary.
     * @param string        $image          The audit image tag.
     *
     * @since 4.1.0
     */
    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly string $dockerBinary = 'docker',
        private readonly string $image = 'jed-audit:latest'
    ) {
    }

    /**
     * Run the audit container.
     *
     * @param string $inputDir       Host directory bind-mounted read-only at /audit/input (must contain extension.zip).
     * @param string $outputDir      Host directory bind-mounted read-write at /audit/output.
     * @param int    $timeoutSeconds Maximum time to allow the container to run.
     *
     * @return ProcessResult
     *
     * @since 4.1.0
     */
    public function runAuditContainer(string $inputDir, string $outputDir, int $timeoutSeconds = 900): ProcessResult
    {
        $command = [
            $this->dockerBinary,
            'run',
            '--rm',
            '--network', 'none',
            '-v', $inputDir . ':/audit/input:ro',
            '-v', $outputDir . ':/audit/output',
            $this->image,
        ];

        return $this->processRunner->run($command, null, $timeoutSeconds);
    }
}
