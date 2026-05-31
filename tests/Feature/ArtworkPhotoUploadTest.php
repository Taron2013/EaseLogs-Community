<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtworkPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_create_artwork_with_photo_stores_file_and_metadata(): void
    {
        $this->signIn();

        $response = $this->post('/artworks', [
            'title' => 'Sunset Study',
            'start_date' => now()->format('Y-m-d'),
            'medium' => 'Oil',
            'photo' => UploadedFile::fake()->image('sunset.jpg', 800, 600),
        ]);

        $response->assertRedirect('/artworks');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);

        $photo = ArtworkPhoto::query()->where('artwork_id', $artwork->id)->first();
        $this->assertNotNull($photo);
        $this->assertTrue($photo->is_primary);
        $this->assertSame('image/jpeg', $photo->mime_type);
        $this->assertSame(800, $photo->width);
        $this->assertSame(600, $photo->height);
        $this->assertNotNull($photo->uploaded_at);
        $this->assertNotEmpty($photo->file_path);

        Storage::disk('public')->assertExists($photo->file_path);
    }

    public function test_update_artwork_with_new_photo_replaces_primary(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Original']);

        $firstPath = 'artworks/'.$artwork->id.'/first.jpg';
        Storage::disk('public')->put($firstPath, 'first-image');

        ArtworkPhoto::create([
            'artwork_id' => $artwork->id,
            'file_path' => $firstPath,
            'mime_type' => 'image/jpeg',
            'is_primary' => true,
            'uploaded_at' => now()->subDay(),
        ]);

        $response = $this->put('/artworks/'.$artwork->id, [
            'title' => 'Original',
            'completed_work' => 0,
            'photo' => UploadedFile::fake()->image('updated.jpg', 400, 300),
        ]);

        $response->assertRedirect('/artworks/'.$artwork->id);

        $photos = ArtworkPhoto::query()->where('artwork_id', $artwork->id)->orderBy('id')->get();
        $this->assertCount(2, $photos);
        $this->assertFalse($photos[0]->fresh()->is_primary);
        $this->assertTrue($photos[1]->is_primary);

        $latest = $artwork->fresh()->latestPhoto;
        $this->assertNotNull($latest);
        $this->assertTrue($latest->is_primary);
        Storage::disk('public')->assertExists($latest->file_path);
    }

    public function test_index_page_shows_photo_thumbnail_when_file_exists(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Listed Work']);

        $path = 'artworks/'.$artwork->id.'/listed.jpg';
        Storage::disk('public')->put($path, 'listed-image');

        $photo = ArtworkPhoto::create([
            'artwork_id' => $artwork->id,
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $response = $this->get('/artworks');

        $response->assertStatus(200);
        $response->assertSee('<img src="'.$photo->publicUrl().'" alt="" class="artwork-thumb">', false);
        $response->assertSee('Listed Work');
        $response->assertDontSee('<span class="artwork-thumb-placeholder">No photo</span>', false);
        $response->assertDontSee('Inventory code', false);
    }

    public function test_index_page_shows_placeholder_when_photo_record_exists_but_file_is_missing(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Missing File Work']);

        ArtworkPhoto::create([
            'artwork_id' => $artwork->id,
            'file_path' => 'artworks/'.$artwork->id.'/missing.jpg',
            'mime_type' => 'image/jpeg',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        Storage::disk('public')->assertMissing('artworks/'.$artwork->id.'/missing.jpg');

        $response = $this->get('/artworks');

        $response->assertStatus(200);
        $response->assertSee('<span class="artwork-thumb-placeholder">No photo</span>', false);
        $response->assertSee('Missing File Work');
        $response->assertDontSee('<img src="/storage/artworks/'.$artwork->id.'/missing.jpg"', false);
    }

    public function test_show_page_displays_latest_photo(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Detail Work']);

        $path = 'artworks/'.$artwork->id.'/detail.jpg';
        Storage::disk('public')->put($path, 'detail-image');

        ArtworkPhoto::create([
            'artwork_id' => $artwork->id,
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $response = $this->get('/artworks/'.$artwork->id);

        $response->assertStatus(200);
        $response->assertSee('artwork-photo', false);
        $response->assertSee('Detail Work');
        $response->assertDontSee('Estimated value', false);
    }

    public function test_edit_page_displays_latest_photo_reference(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Edit Reference Work']);

        $path = 'artworks/'.$artwork->id.'/edit-ref.jpg';
        Storage::disk('public')->put($path, 'edit-ref-image');

        ArtworkPhoto::create([
            'artwork_id' => $artwork->id,
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $response = $this->get('/artworks/'.$artwork->id.'/edit');

        $response->assertStatus(200);
        $response->assertSee('artwork-photo-edit-reference', false);
        $response->assertSee('Current artwork photo');
        $response->assertSee('Edit Reference Work');
        $response->assertDontSee('Inventory code', false);
        $response->assertDontSee('SKU', false);
    }

    public function test_invalid_photo_upload_is_rejected(): void
    {
        $this->signIn();

        $response = $this->from('/artworks/create')->post('/artworks', [
            'title' => 'Bad Upload',
            'start_date' => now()->format('Y-m-d'),
            'photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ]);

        $response->assertRedirect('/artworks/create');
        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('artworks', 0);
        $this->assertDatabaseCount('artwork_photos', 0);
    }

    public function test_delete_artwork_removes_photo_files(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Temporary']);

        $path = 'artworks/'.$artwork->id.'/temp.jpg';
        Storage::disk('public')->put($path, 'temp-image');

        ArtworkPhoto::create([
            'artwork_id' => $artwork->id,
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $response = $this->delete('/artworks/'.$artwork->id);

        $response->assertRedirect('/artworks');
        $this->assertDatabaseCount('artworks', 0);
        $this->assertDatabaseCount('artwork_photos', 0);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_create_without_photo_still_works(): void
    {
        $this->signIn();

        $response = $this->post('/artworks', [
            'title' => '',
            'start_date' => now()->format('Y-m-d'),
            'medium' => 'Acrylic',
            'artwork_type' => 'Painting',
        ]);

        $response->assertRedirect('/artworks');
        $this->assertDatabaseCount('artwork_photos', 0);
        $this->assertDatabaseHas('artworks', [
            'title' => '',
            'medium' => 'Acrylic',
            'artwork_type' => 'Painting',
        ]);
    }
}
