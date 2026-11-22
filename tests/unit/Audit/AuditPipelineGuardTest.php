<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Audit;

use Jed\Component\Jed\Administrator\Audit\AuditPipeline;
use Jed\Component\Jed\Administrator\Audit\ClaudeAuditor;
use Jed\Component\Jed\Administrator\Audit\DockerRunner;
use Jed\Component\Jed\Administrator\Url\FetchResult;
use Jed\Component\Jed\Administrator\Url\SafeHttpFetcher;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\Database\DatabaseInterface;
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
        @unlink($this->dir . '/extension.zip');
        @rmdir($this->dir);
    }

    /**
     * Calls a private method with the default ZIP fetcher unless a stub is supplied.
     */
    private function callPrivate(string $method, array $args, ?SafeHttpFetcher $fetcher = null): mixed
    {
        $pipeline = new AuditPipeline(
            $this->createStub(DatabaseInterface::class),
            $this->createStub(DockerRunner::class),
            $this->createStub(ClaudeAuditor::class),
            $this->dir,
            $this->dir,
            fetcher: $fetcher
        );

        $class = new ReflectionClass($pipeline);

        return $class->getMethod($method)->invokeArgs($pipeline, $args);
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

    public function testSuccessfulDownloadWritesTheResponseBody(): void
    {
        $url         = 'https://example.com/extension.zip';
        $destination = $this->dir . '/extension.zip';
        $body        = "PK\x03\x04\x00\xFF";
        $fetcher     = $this->createMock(SafeHttpFetcher::class);
        $fetcher->expects($this->once())->method('fetch')->with($url)
            ->willReturn(new FetchResult(false, status: 200, body: $body));

        $this->callPrivate('downloadExtensionZip', [$url, $destination], $fetcher);

        $this->assertSame($body, file_get_contents($destination));
    }

    /** @dataProvider failedDownloadStatuses */
    public function testFailedDownloadReportsTheStatusWithoutWritingAFile(int $status): void
    {
        $destination = $this->dir . '/extension.zip';
        $fetcher     = $this->createStub(SafeHttpFetcher::class);
        $fetcher->method('fetch')->willReturn(new FetchResult(false, status: $status, body: 'Error response'));

        $language = new Language('en-GB');
        $language->load('com_jed', dirname(__DIR__, 3) . '/src/administrator/components/com_jed');
        $application = $this->createStub(CMSApplicationInterface::class);
        $application->method('getLanguage')->willReturn($language);
        $previousApplication  = Factory::$application;
        Factory::$application = $application;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to download extension zip: HTTP ' . $status);

        try {
            $this->callPrivate('downloadExtensionZip', ['https://example.com/extension.zip', $destination], $fetcher);
        } finally {
            Factory::$application = $previousApplication;
            $this->assertFileDoesNotExist($destination);
        }
    }

    public static function failedDownloadStatuses(): array
    {
        return [
            'no response'          => [0],
            'non-success response' => [300],
            'not found'            => [404],
        ];
    }
}
