<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Parser;

use Jed\Component\Jed\Administrator\Parser\SafeZip;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

/**
 * Zips arrive from any logged-in user, so extraction has to stay inside its limits.
 *
 * @since 4.0.0
 */
final class SafeZipTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/jed-safezip-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            is_dir($dir . '/' . $entry) && !is_link($dir . '/' . $entry) ? $this->rrmdir($dir . '/' . $entry) : unlink($dir . '/' . $entry);
        }

        rmdir($dir);
    }

    /**
     * @param array<string, string> $files Entry name => contents.
     */
    private function makeZip(array $files): string
    {
        $path = $this->dir . '/test.zip';
        $zip  = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    public function testAnOrdinaryPackageIsExtractedWithItsDirectories(): void
    {
        $zip = $this->makeZip(['pkg.xml' => '<extension/>', 'sub/dir/file.php' => '<?php // x', 'empty/' => '']);

        SafeZip::extract($zip, $this->dir . '/out');

        $this->assertSame('<extension/>', file_get_contents($this->dir . '/out/pkg.xml'));
        $this->assertSame('<?php // x', file_get_contents($this->dir . '/out/sub/dir/file.php'));
        $this->assertDirectoryExists($this->dir . '/out/empty');
    }

    public function testAnArchiveThatExpandsPastTheLimitIsRefusedAndRemoved(): void
    {
        $zip = $this->makeZip(['big.bin' => str_repeat("\0", 2 * 1024 * 1024)]);

        try {
            SafeZip::extract($zip, $this->dir . '/out', 100, 1024 * 1024);
            $this->fail('A 2 MB expansion must be refused when the limit is 1 MB.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('more than the allowed size', $e->getMessage());
        }

        $this->assertDirectoryDoesNotExist($this->dir . '/out');
    }

    public function testAnArchiveWithTooManyEntriesIsRefused(): void
    {
        $files = [];

        for ($i = 0; $i < 6; $i++) {
            $files['f' . $i . '.txt'] = 'x';
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('too many files');

        SafeZip::extract($this->makeZip($files), $this->dir . '/out', 5);
    }

    public function testEntriesThatWouldLeaveTheDestinationAreSkipped(): void
    {
        $zip = $this->makeZip([
            '../escaped.txt'      => 'no',
            'a/../../escaped2.txt' => 'no',
            '/abs.txt'            => 'no',
            'ok.txt'              => 'yes',
        ]);

        SafeZip::extract($zip, $this->dir . '/out');

        $this->assertFileExists($this->dir . '/out/ok.txt');
        $this->assertFileDoesNotExist($this->dir . '/escaped.txt');
        $this->assertFileDoesNotExist($this->dir . '/escaped2.txt');
        $this->assertFileDoesNotExist('/abs.txt');
    }

    public function testAFileThatIsNotAZipIsRefused(): void
    {
        file_put_contents($this->dir . '/notzip.zip', 'plain text');

        $this->expectException(RuntimeException::class);

        SafeZip::extract($this->dir . '/notzip.zip', $this->dir . '/out');
    }
}
