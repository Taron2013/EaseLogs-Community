<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Models\User;
use App\Services\ArtworkPhotoService;
use App\Support\ArtworkPhotoBulkImport\BulkImportManualResolution;
use App\Support\ArtworkPhotoBulkImport\BulkImportRowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Track B3.3: CE ZIP preview runs intrinsic qualification before usable candidates.
 */
class PhotoImportCommonQualificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_valid_image_retains_usable_path_and_association(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Blue Study']);

        $zip = $this->makeImportZip([
            'Blue Study.jpg' => UploadedFile::fake()->image('Blue Study.jpg', 24, 18)->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $row = $this->previewRowByFilename($user, $token, 'Blue Study.jpg');

        $this->assertNotNull($row['absolute_path']);
        $this->assertIsString($row['absolute_path']);
        $this->assertFileExists($row['absolute_path']);
        $this->assertSame($artwork->id, $row['artwork_id']);
        $this->assertTrue(! empty($row['absolute_path']));
        $this->assertNotSame(BulkImportRowStatus::INVALID_FILE, $row['status']);
    }

    public function test_intrinsically_invalid_image_extension_is_rejected_before_association(): void
    {
        $this->signIn();

        $zip = $this->makeImportZip([
            'spoof.jpg' => 'definitely not an image payload',
        ]);

        $user = auth()->user();
        $this->assertInstanceOf(User::class, $user);

        $token = $this->previewImport($zip);
        $row = $this->previewRowByFilename($user, $token, 'spoof.jpg');

        $this->assertSame(BulkImportRowStatus::INVALID_FILE, $row['status']);
        $this->assertTrue($row['absolute_path'] === null || $row['absolute_path'] === '');
        $this->assertNull($row['file_hash']);
        $this->assertNull($row['artwork_id']);
        $this->assertNotSame(BulkImportRowStatus::UNMATCHED, $row['status']);
        $this->assertFalse(BulkImportManualResolution::canResolveRow($row));
        $this->assertFalse(! empty($row['absolute_path']));
    }

    public function test_intrinsically_invalid_bytes_cannot_become_artwork_photo(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Rejected']);

        $zip = $this->makeImportZip([
            'IMG_0001.jpg' => "\xFF\xD8\xFF",
        ]);

        $token = $this->previewImport($zip);
        $preview = Cache::get('artwork_photo_bulk_preview:'.$user->id.':'.$token);
        $this->assertIsArray($preview);

        $statuses = collect($preview['rows'])->pluck('status')->all();
        $this->assertContains(BulkImportRowStatus::INVALID_FILE, $statuses);
        $this->assertNotContains(BulkImportRowStatus::UNMATCHED, $statuses);

        $this->applyImport($token);

        $this->assertSame(0, ArtworkPhoto::query()->count());
    }

    public function test_valid_unmatched_image_still_qualifies_and_remains_matchable(): void
    {
        $this->signIn();

        $zip = $this->makeImportZip([
            'IMG_019283.jpg' => UploadedFile::fake()->image('IMG_019283.jpg', 20, 15)->getContent(),
        ]);

        $user = auth()->user();
        $this->assertInstanceOf(User::class, $user);

        $token = $this->previewImport($zip);
        $row = $this->previewRowByFilename($user, $token, 'IMG_019283.jpg');

        $this->assertSame(BulkImportRowStatus::MISSING_ARTWORK, $row['status']);
        $this->assertNotSame(BulkImportRowStatus::INVALID_FILE, $row['status']);
        $this->assertNotNull($row['absolute_path']);
        $this->assertIsString($row['absolute_path']);
        $this->assertFileExists($row['absolute_path']);
        $this->assertTrue(BulkImportManualResolution::canResolveRow($row));
        $this->assertTrue(! empty($row['absolute_path']));
    }

    public function test_durable_import_stored_file_still_qualifies(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(ArtworkPhotoService::class))->getFileName(),
        );

        $this->assertStringContainsString(
            'imageQualifier->qualifyPath($absolutePath)',
            $source,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function previewRowByFilename(User $user, string $token, string $filename): array
    {
        $preview = Cache::get('artwork_photo_bulk_preview:'.$user->id.':'.$token);
        $this->assertIsArray($preview);

        foreach ($preview['rows'] as $row) {
            if (($row['filename'] ?? null) === $filename) {
                return $row;
            }
        }

        $this->fail('Expected preview row for '.$filename);
    }

    private function previewImport(UploadedFile $zip): string
    {
        $response = $this->post(route('artworks.photo-bulk-import.preview'), [
            'photo_zip' => $zip,
        ]);
        $response->assertRedirect();

        return basename(parse_url($response->headers->get('Location'), PHP_URL_PATH) ?: '');
    }

    private function applyImport(string $token): void
    {
        $this->post(route('artworks.photo-bulk-import.apply'), [
            'token' => $token,
        ])->assertRedirect(route('artworks.import-export'));
    }

    /**
     * @param  array<string, string>  $files
     */
    private function makeImportZip(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'photo-import-');
        $zipPath = $path.'.zip';
        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return new UploadedFile($zipPath, 'photos.zip', 'application/zip', null, true);
    }
}
