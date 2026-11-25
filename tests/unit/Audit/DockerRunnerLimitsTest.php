<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Audit;

use Jed\Component\Jed\Administrator\Audit\DockerRunner;
use Jed\Component\Jed\Administrator\Audit\ProcessResult;
use Jed\Component\Jed\Administrator\Audit\ProcessRunner;
use PHPUnit\Framework\TestCase;

/**
 * The audit container runs untrusted third-party code. Whatever else changes, its command line
 * has to keep it off the network, bounded in memory, processes and working space, and without
 * privileges.
 *
 * @since 4.0.0
 */
final class DockerRunnerLimitsTest extends TestCase
{
    /**
     * @return string[] The command the runner handed to the process runner.
     */
    private function commandUsed(): array
    {
        $captured = [];
        $process  = $this->createMock(ProcessRunner::class);
        $process->method('run')->willReturnCallback(function (array $command) use (&$captured) {
            $captured = $command;

            return new ProcessResult(0, '', '');
        });

        (new DockerRunner($process))->runAuditContainer('/in', '/out');

        return $captured;
    }

    /**
     * The value that follows a flag in the command.
     */
    private function valueOf(array $command, string $flag): ?string
    {
        $index = array_search($flag, $command, true);

        return $index === false ? null : ($command[$index + 1] ?? null);
    }

    public function testTheContainerHasNoNetworkAndIsCappedInMemoryProcessesAndCpu(): void
    {
        $command = $this->commandUsed();

        $this->assertSame('none', $this->valueOf($command, '--network'));
        $this->assertSame(DockerRunner::MEMORY, $this->valueOf($command, '--memory'));
        $this->assertSame(DockerRunner::MEMORY, $this->valueOf($command, '--memory-swap'), 'swap must not extend the memory limit');
        $this->assertSame(DockerRunner::PIDS, $this->valueOf($command, '--pids-limit'));
        $this->assertSame(DockerRunner::CPUS, $this->valueOf($command, '--cpus'));
    }

    public function testTheContainerHasNoPrivilegesAndAReadOnlyFilesystem(): void
    {
        $command = $this->commandUsed();

        $this->assertSame('ALL', $this->valueOf($command, '--cap-drop'));
        $this->assertSame('no-new-privileges', $this->valueOf($command, '--security-opt'));
        $this->assertContains('--read-only', $command);
    }

    public function testTheWorkingCopyLivesOnASizeCappedTmpfsSmallerThanTheMemoryLimit(): void
    {
        $command = $this->commandUsed();
        $tmpfs   = array_values(array_filter($command, static fn ($part) => str_starts_with((string) $part, '/audit/work:')));

        $this->assertCount(1, $tmpfs);
        $this->assertStringContainsString('size=' . DockerRunner::WORK_SIZE, $tmpfs[0]);

        // A tmpfs is charged to the container's memory: a cap above the memory limit would end in
        // the OOM killer instead of a plain "disk full" failure.
        $toMegabytes = static fn (string $size): int => (int) $size * (str_ends_with($size, 'g') ? 1024 : 1);

        $this->assertLessThan($toMegabytes(DockerRunner::MEMORY), $toMegabytes(DockerRunner::WORK_SIZE));
    }
}
