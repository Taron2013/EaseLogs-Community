<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Services\ArtworkPhotoService;
use App\Support\ArtworkImageRejectedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Track B1: durable ArtworkPhoto persistence requires image qualification.
 */
class ArtworkImageQualificationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_import_stored_file_rejects_undecodable_jpg_without_persisting(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Import Gate']);

        $path = sys_get_temp_dir().'/elc-b1-poison-'.uniqid('', true).'.jpg';
        file_put_contents($path, 'not-a-jpeg-payload');

        try {
            app(ArtworkPhotoService::class)->importStoredFile(
                $artwork,
                $path,
                'poison.jpg',
            );
            $this->fail('Expected ArtworkImageRejectedException');
        } catch (ArtworkImageRejectedException) {
            // Expected fail-closed rejection before durable persistence.
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, ArtworkPhoto::query()->count());
        $this->assertFalse(Storage::disk('public')->exists('artworks/'.$artwork->id));
    }

    public function test_store_photo_still_accepts_valid_upload(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Happy Path']);

        $photo = app(ArtworkPhotoService::class)->store(
            $artwork,
            UploadedFile::fake()->image('ok.jpg', 20, 15),
        );

        $this->assertNotNull($photo->id);
        $this->assertSame('image/jpeg', $photo->mime_type);
        $this->assertSame(20, $photo->width);
        $this->assertSame(15, $photo->height);
        $this->assertTrue(Storage::disk('public')->exists($photo->file_path));
    }
}
