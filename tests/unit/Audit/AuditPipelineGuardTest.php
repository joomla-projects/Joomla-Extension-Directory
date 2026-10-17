<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Audit;

use Jed\Component\Jed\Administrator\Audit\AuditPipeline;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * The audit pipeline reads an untrusted zip and downloads from a URL a developer supplies.
 * Both have to stay inside the lines the rest of the directory draws.
 *
 * @since 4.0.0
 */
final class AuditPipelineGuardTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/jed-audit-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/src', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/src/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->dir . '/src');
        @unlink($this->dir . '/secret.txt');
        @rmdir($this->dir);
    }

    /**
     * Calls a private method without building the pipeline's collaborators.
     *
     * @param string $method The method name.
     * @param array  $args   The arguments.
     *
     * @return mixed
     */
    private function callPrivate(string $method, array $args): mixed
    {
        $class    = new ReflectionClass(AuditPipeline::class);
        $pipeline = $class->newInstanceWithoutConstructor();
        $m        = $class->getMethod($method);

        return $m->invokeArgs($pipeline, $args);
    }

    public function testSymlinksInTheExtensionSourceAreNotRead(): void
    {
        file_put_contents($this->dir . '/secret.txt', 'host secret');
        file_put_contents($this->dir . '/src/real.php', '<?php // real');
        symlink($this->dir . '/secret.txt', $this->dir . '/src/leak.php');

        $files = $this->callPrivate('collectSourceFiles', [$this->dir . '/src']);

        $this->assertArrayHasKey('real.php', $files);
        $this->assertArrayNotHasKey('leak.php', $files);
        $this->assertStringNotContainsString('host secret', implode('', $files));
    }

    public function testADownloadUrlPointingAtTheLocalMachineIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Refused to download extension zip/');

        $this->callPrivate('downloadExtensionZip', ['http://localhost/extension.zip', $this->dir . '/extension.zip']);
    }

    public function testAnEmptyDownloadUrlIsRefused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->callPrivate('downloadExtensionZip', ['', $this->dir . '/extension.zip']);
    }
}
