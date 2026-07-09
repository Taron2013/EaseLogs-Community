<?php

namespace Tests\Feature;

use App\Models\Artwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ArtworkPhotoBulkImportZipSecurityTest extends TestCase
{
    use RefreshDatabase;

    private int $tempImportFileCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->tempImportFileCount = $this->countTempImportFiles();
    }

    public function test_zip_with_parent_directory_traversal_is_rejected(): void
    {
        $this->signIn();

        $zip = $this->makeZipFromEntries([
            '../evil.jpg' => UploadedFile::fake()->image('evil.jpg')->getContent(),
        ]);

        $this->assertUnsafeZipRejected($zip, 'unsafe file paths');
        $this->assertNoNewExtractedFiles();
    }

    public function test_zip_with_nested_traversal_path_is_rejected(): void
    {
        $this->signIn();

        $zip = $this->makeZipFromEntries([
            'photos/../../evil.jpg' => UploadedFile::fake()->image('evil.jpg')->getContent(),
        ]);

        $this->assertUnsafeZipRejected($zip, 'unsafe file paths');
        $this->assertNoNewExtractedFiles();
    }

    public function test_zip_with_absolute_unix_path_is_rejected(): void
    {
        $this->signIn();

        $zip = $this->makeZipFromEntries([
            '/tmp/evil.jpg' => UploadedFile::fake()->image('evil.jpg')->getContent(),
        ]);

        $this->assertUnsafeZipRejected($zip, 'unsafe file paths');
        $this->assertNoNewExtractedFiles();
    }

    public function test_zip_with_windows_style_absolute_or_traversal_path_is_rejected(): void
    {
        $this->signIn();

        $absoluteZip = $this->makeZipFromEntries([
            'C:/Windows/evil.jpg' => UploadedFile::fake()->image('evil.jpg')->getContent(),
        ]);
        $this->assertUnsafeZipRejected($absoluteZip, 'unsafe file paths');

        $backslashZip = $this->makeZipFromEntries([
            'photos\\..\\..\\evil.jpg' => UploadedFile::fake()->image('evil.jpg')->getContent(),
        ]);
        $this->assertUnsafeZipRejected($backslashZip, 'unsafe file paths');

        $this->assertNoNewExtractedFiles();
    }

    public function test_zip_with_too_many_entries_is_rejected(): void
    {
        $this->signIn();
        config(['easelogs.photo_import_zip.max_entries' => 3]);

        $entries = [];
        for ($i = 1; $i <= 4; $i++) {
            $entries['photos/photo-'.$i.'.jpg'] = UploadedFile::fake()->image('photo-'.$i.'.jpg')->getContent();
        }

        $zip = $this->makeZipFromEntries($entries);

        $this->assertUnsafeZipRejected($zip, 'too many entries');
        $this->assertNoNewExtractedFiles();
    }

    public function test_zip_exceeding_total_uncompressed_size_is_rejected(): void
    {
        $this->signIn();
        config(['easelogs.photo_import_zip.max_total_uncompressed_mb' => 1]);

        $zip = $this->makeZipFromEntries([
            'photos/large-a.jpg' => str_repeat('a', 600 * 1024),
            'photos/large-b.jpg' => str_repeat('b', 600 * 1024),
        ], storeWithoutCompression: true);

        $this->assertUnsafeZipRejected($zip, 'too large when uncompressed');
        $this->assertNoNewExtractedFiles();
    }

    public function test_zip_with_oversized_individual_file_is_rejected(): void
    {
        $this->signIn();
        config(['easelogs.photo_import_zip.max_entry_uncompressed_mb' => 1]);

        $zip = $this->makeZipFromEntries([
            'photos/huge.jpg' => str_repeat('x', 2 * 1024 * 1024),
        ], storeWithoutCompression: true);

        $this->assertUnsafeZipRejected($zip, 'file that is too large');
        $this->assertNoNewExtractedFiles();
    }

    public function test_zip_exceeding_allowed_directory_depth_is_rejected(): void
    {
        $this->signIn();
        config(['easelogs.photo_import_zip.max_path_depth' => 2]);

        $zip = $this->makeZipFromEntries([
            'a/b/c/deep.jpg' => UploadedFile::fake()->image('deep.jpg')->getContent(),
        ]);

        $this->assertUnsafeZipRejected($zip, 'nested too deeply');
        $this->assertNoNewExtractedFiles();
    }

    public function test_valid_zip_import_preview_still_works(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create([
            'title' => 'Safe Import Study',
        ]);

        $zip = $this->makeZipFromEntries([
            'mapping.csv' => "artwork_id,filename\n{$artwork->id},photos/safe.jpg\n",
            'photos/safe.jpg' => UploadedFile::fake()->image('safe.jpg')->getContent(),
        ]);

        $response = $this->from(route('artworks.import-export'))
            ->post(route('artworks.photo-bulk-import.preview'), [
                'photo_zip' => $zip,
            ]);

        $response->assertRedirect();

        $token = basename(parse_url((string) $response->headers->get('Location'), PHP_URL_PATH) ?: '');
        $this->assertNotSame('', $token);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('safe.jpg', false);
    }

    public function test_failed_unsafe_zip_preview_does_not_leave_extracted_files_behind(): void
    {
        $this->signIn();

        $tempRoot = storage_path('app/temp/photo-imports');
        File::ensureDirectoryExists($tempRoot);
        $beforeDirectories = $this->countExtractDirectories();

        $zip = $this->makeZipFromEntries([
            '../evil.jpg' => UploadedFile::fake()->image('evil.jpg')->getContent(),
        ]);

        $this->assertUnsafeZipRejected($zip, 'unsafe file paths');
        $this->assertSame($beforeDirectories, $this->countExtractDirectories());
        $this->assertNoFilesOutsideTempRoot($tempRoot);
    }

    private function assertUnsafeZipRejected(UploadedFile $zip, string $expectedMessageFragment): void
    {
        $this->from(route('artworks.import-export'))
            ->post(route('artworks.photo-bulk-import.preview'), [
                'photo_zip' => $zip,
            ])
            ->assertRedirect(route('artworks.import-export'))
            ->assertSessionHas('error');

        $message = session('error');
        $this->assertIsString($message);
        $this->assertStringContainsString($expectedMessageFragment, $message);
    }

    private function assertNoNewExtractedFiles(): void
    {
        $this->assertSame($this->tempImportFileCount, $this->countTempImportFiles());
        $this->assertNoFilesOutsideTempRoot(storage_path('app/temp/photo-imports'));
    }

    private function countTempImportFiles(): int
    {
        $tempRoot = storage_path('app/temp/photo-imports');

        return is_dir($tempRoot) ? count(File::allFiles($tempRoot)) : 0;
    }

    private function countExtractDirectories(): int
    {
        $tempRoot = storage_path('app/temp/photo-imports');

        if (! is_dir($tempRoot)) {
            return 0;
        }

        return count(array_filter(
            scandir($tempRoot) ?: [],
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($tempRoot.'/'.$entry),
        ));
    }

    private function assertNoFilesOutsideTempRoot(string $tempRoot): void
    {
        $evilPath = dirname($tempRoot).'/evil.jpg';
        $this->assertFileDoesNotExist($evilPath);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function makeZipFromEntries(array $entries, bool $storeWithoutCompression = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'photo-import-security-');
        $zipPath = $path.'.zip';
        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);

            if ($storeWithoutCompression) {
                $zip->setCompressionName($name, ZipArchive::CM_STORE);
            }
        }

        $zip->close();

        return new UploadedFile($zipPath, 'photos.zip', 'application/zip', null, true);
    }
}
