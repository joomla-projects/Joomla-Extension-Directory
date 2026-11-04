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
 * @since 4.0.0
 */
class DockerRunner
{
    /**
     * Resource limits for one audit container.
     *
     * @since 4.0.0
     */
    public const MEMORY    = '2g';
    public const CPUS      = '2';
    public const PIDS      = '512';
    public const WORK_SIZE = '1500m';

    /**
     * @param ProcessRunner $processRunner  Runs the `docker` CLI.
     * @param string        $dockerBinary   Path to the docker binary.
     * @param string        $image          The audit image tag.
     *
     * @since 4.0.0
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
     * @since 4.0.0
     */
    public function runAuditContainer(string $inputDir, string $outputDir, int $timeoutSeconds = 900): ProcessResult
    {
        $command = [
            $this->dockerBinary,
            'run',
            '--rm',
            '--network', 'none',
            // The package is untrusted code: bound what it can take from the host and what it can
            // do inside the container. The working copy lives on a size-capped tmpfs, so an archive
            // that expands past it fails inside the container instead of filling the host disk.
            '--memory', self::MEMORY,
            '--memory-swap', self::MEMORY,
            '--cpus', self::CPUS,
            '--pids-limit', self::PIDS,
            '--cap-drop', 'ALL',
            '--security-opt', 'no-new-privileges',
            '--read-only',
            '--tmpfs', '/audit/work:rw,exec,nosuid,size=' . self::WORK_SIZE,
            '--tmpfs', '/tmp:rw,nosuid,size=256m',
            '-v', $inputDir . ':/audit/input:ro',
            '-v', $outputDir . ':/audit/output',
            $this->image,
        ];

        return $this->processRunner->run($command, null, $timeoutSeconds);
    }
}
