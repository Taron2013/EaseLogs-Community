<?php

namespace Tests\Unit;

use App\Support\ArtworkPhotoBulkImport\PhotoImportUploadEnvironment;
use App\Support\ArtworkPhotoBulkImport\PhotoImportUploadLimit;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhotoImportUploadEnvironmentTest extends TestCase
{
    public function test_parse_ini_size_handles_suffixed_values(): void
    {
        $this->assertSame(1024, PhotoImportUploadEnvironment::parseIniSize('1K'));
        $this->assertSame(1024 * 1024, PhotoImportUploadEnvironment::parseIniSize('1M'));
        $this->assertSame(1024 * 1024 * 1024, PhotoImportUploadEnvironment::parseIniSize('1G'));
        $this->assertSame(6144 * 1024 * 1024, PhotoImportUploadEnvironment::parseIniSize('6144M'));
    }

    public function test_report_includes_app_and_php_limits(): void
    {
        $report = PhotoImportUploadEnvironment::report();

        $this->assertContains($report['status'], ['ok', 'degraded', 'misconfigured']);
        $this->assertSame(PhotoImportUploadLimit::maxMegabytes(), $report['app_max_mb']);
        $this->assertIsArray($report['warnings']);
        $this->assertIsArray($report['notes']);
    }

    public function test_warns_when_app_limit_exceeds_php_post_max(): void
    {
        config(['easelogs.photo_import_max_upload_mb' => 999999]);

        $postMaxMb = PhotoImportUploadEnvironment::report()['php_post_max_mb'];

        if ($postMaxMb === null) {
            $this->markTestSkipped('PHP post_max_size is not available in this test runtime.');
        }

        $warnings = PhotoImportUploadEnvironment::warnings();

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('post_max_size', implode(' ', $warnings));
    }

    #[DataProvider('missingFileMessageProvider')]
    public function test_missing_file_message_explains_large_discarded_upload(?int $contentLength, string $needle): void
    {
        $message = PhotoImportUploadEnvironment::missingFileMessage($contentLength);

        $this->assertStringContainsString($needle, $message);
    }

    /**
     * @return array<string, array{0: ?int, 1: string}>
     */
    public static function missingFileMessageProvider(): array
    {
        return [
            'small missing upload' => [null, 'required'],
            'large missing upload' => [5 * 1024 * 1024 * 1024, 'did not arrive'],
        ];
    }
}
