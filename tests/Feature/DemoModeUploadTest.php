<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Support\DemoMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoModeUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_demo_mode_with_upload_behavior_disabled_returns_403_for_photo_upload(): void
    {
        $this->signIn();
        $this->setDemoMode(DemoMode::UPLOAD_BEHAVIOR_DISABLED);

        $response = $this->post('/artworks', [
            'title' => 'Blocked Upload',
            'start_date' => now()->format('Y-m-d'),
            'photo' => UploadedFile::fake()->image('blocked.jpg'),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Artwork::query()->count());
        $this->assertSame(0, ArtworkPhoto::query()->count());
    }

    public function test_demo_mode_with_upload_behavior_disabled_allows_metadata_only_create(): void
    {
        $this->signIn();
        $this->setDemoMode(DemoMode::UPLOAD_BEHAVIOR_DISABLED);

        $response = $this->post('/artworks', [
            'title' => 'No Photo',
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect('/artworks');
        $this->assertSame(1, Artwork::query()->count());
        $this->assertSame(0, ArtworkPhoto::query()->count());
    }

    public function test_demo_mode_with_upload_behavior_disabled_hides_upload_control(): void
    {
        $this->signIn();
        $this->setDemoMode(DemoMode::UPLOAD_BEHAVIOR_DISABLED);

        $response = $this->get('/artworks/create');

        $response->assertOk();
        $response->assertSee(DemoMode::MESSAGE_UPLOADS_DISABLED, false);
        $response->assertDontSee('type="file" name="photo"', false);
    }

    public function test_demo_mode_with_upload_behavior_discard_completes_without_storing_file(): void
    {
        $this->signIn();
        $this->setDemoMode(DemoMode::UPLOAD_BEHAVIOR_DISCARD);

        $response = $this->post('/artworks', [
            'title' => 'Discard Upload Test',
            'start_date' => now()->format('Y-m-d'),
            'photo' => UploadedFile::fake()->image('discarded.jpg'),
        ]);

        $response->assertRedirect('/artworks');
        $response->assertSessionHas('success', 'Artwork created successfully.');
        $response->assertSessionHas('info', DemoMode::MESSAGE_UPLOAD_DISCARDED);

        $this->assertSame(1, Artwork::query()->count());
        $this->assertSame(0, ArtworkPhoto::query()->count());
        Storage::disk('public')->assertDirectoryEmpty('artworks');
    }

    public function test_demo_mode_with_upload_behavior_discard_does_not_replace_existing_photo(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Keep Photo']);

        $path = 'artworks/'.$artwork->id.'/original.jpg';
        Storage::disk('public')->put($path, 'original-image');

        $photo = ArtworkPhoto::create([
            'artwork_id' => $artwork->id,
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'is_primary' => true,
            'uploaded_at' => now()->subDay(),
        ]);

        $this->setDemoMode(DemoMode::UPLOAD_BEHAVIOR_DISCARD);

        $response = $this->put('/artworks/'.$artwork->id, [
            'title' => 'Updated Title',
            'completed_work' => 0,
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
        ]);

        $response->assertRedirect('/artworks/'.$artwork->id);
        $response->assertSessionHas('info', DemoMode::MESSAGE_UPLOAD_DISCARDED);

        $artwork->refresh();
        $this->assertSame('Updated Title', $artwork->title);
        $this->assertSame(1, ArtworkPhoto::query()->where('artwork_id', $artwork->id)->count());
        $this->assertTrue($photo->fresh()->is_primary);
        Storage::disk('public')->assertExists($path);
    }

    public function test_demo_mode_with_upload_behavior_discard_shows_upload_control_and_notice(): void
    {
        $this->signIn();
        $this->setDemoMode(DemoMode::UPLOAD_BEHAVIOR_DISCARD);

        $response = $this->get('/artworks/create');

        $response->assertOk();
        $response->assertSee('type="file" name="photo"', false);
        $response->assertSee(DemoMode::MESSAGE_UPLOAD_DISCARD_NOTICE, false);
    }

    public function test_demo_mode_with_upload_behavior_enabled_stores_uploads(): void
    {
        $this->signIn();
        $this->setDemoMode(DemoMode::UPLOAD_BEHAVIOR_ENABLED);

        $response = $this->post('/artworks', [
            'title' => 'Stored Upload',
            'start_date' => now()->format('Y-m-d'),
            'photo' => UploadedFile::fake()->image('stored.jpg'),
        ]);

        $response->assertRedirect('/artworks');
        $response->assertSessionMissing('info');
        $this->assertSame(1, ArtworkPhoto::query()->count());
    }

    public function test_demo_mode_disabled_preserves_normal_upload_behavior(): void
    {
        $this->signIn();
        $this->setDemoMode(enabled: false, uploadBehavior: DemoMode::UPLOAD_BEHAVIOR_DISABLED);

        $response = $this->post('/artworks', [
            'title' => 'Normal Upload',
            'start_date' => now()->format('Y-m-d'),
            'photo' => UploadedFile::fake()->image('normal.jpg'),
        ]);

        $response->assertRedirect('/artworks');
        $this->assertSame(1, ArtworkPhoto::query()->count());
    }

    public function test_demo_mode_blocks_csv_import_when_imports_not_allowed(): void
    {
        $this->signIn();
        $this->setDemoMode(DemoMode::UPLOAD_BEHAVIOR_DISCARD, overrides: ['allow_imports' => false]);

        $csv = "title\nDemo Row\n";

        $response = $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function setDemoMode(
        string $uploadBehavior = DemoMode::UPLOAD_BEHAVIOR_ENABLED,
        bool $enabled = true,
        array $overrides = [],
    ): void {
        config([
            'easelogs.demo_mode' => $enabled,
            'easelogs.demo' => array_merge([
                'upload_behavior' => $uploadBehavior,
                'allow_imports' => false,
                'allow_account_changes' => true,
                'allow_deletes' => true,
                'allow_registration' => false,
                'allow_password_reset' => false,
                'allow_email_sending' => false,
                'allow_external_webhooks' => false,
                'show_public_notice' => false,
            ], $overrides),
        ]);
    }
}
