<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use App\Services\ArtworkCsvService;
use App\Services\ArtworkPhotoService;
use App\Support\DemoMode;
use App\Support\DemoOutbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoModeWritePathAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_artwork_metadata_create_is_allowed_in_public_demo(): void
    {
        $this->signIn();
        $this->configurePublicDemo();

        $this->post(route('artworks.store'), [
            'title' => 'Demo Created Piece',
            'dimension_unit' => 'in',
        ])->assertRedirect(route('artworks.index'));

        $this->assertDatabaseHas('artworks', ['title' => 'Demo Created Piece']);
    }

    public function test_artwork_metadata_update_is_allowed_in_public_demo(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Before']);
        $this->configurePublicDemo();

        $this->put(route('artworks.update', $artwork), [
            'title' => 'After Demo Edit',
            'dimension_unit' => 'in',
        ])->assertRedirect(route('artworks.show', $artwork));

        $this->assertSame('After Demo Edit', $artwork->fresh()->title);
    }

    public function test_artwork_single_delete_route_returns_403_in_public_demo(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();
        $this->configurePublicDemo();

        $this->delete(route('artworks.destroy', $artwork))->assertForbidden();
        $this->assertModelExists($artwork);
    }

    public function test_artwork_bulk_delete_route_returns_403_in_public_demo(): void
    {
        $user = $this->signIn();
        $first = Artwork::factory()->for($user)->create();
        $second = Artwork::factory()->for($user)->create();
        $this->configurePublicDemo();

        $this->delete(route('artworks.bulk-delete'), [
            'ids' => [$first->id, $second->id],
        ])->assertForbidden();

        $this->assertSame(2, Artwork::query()->count());
    }

    public function test_csv_import_route_returns_403_in_public_demo(): void
    {
        $this->signIn();
        $this->configurePublicDemo();

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', "title\nImported\n"),
        ])->assertForbidden();
    }

    public function test_csv_import_service_blocks_without_route_middleware(): void
    {
        $user = $this->signIn();
        $this->configurePublicDemo();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ArtworkCsvService::class)->import(
            UploadedFile::fake()->createWithContent('import.csv', "title\nImported\n"),
            $user,
        );
    }

    public function test_photo_upload_route_returns_403_when_uploads_disabled(): void
    {
        $this->signIn();
        $this->configurePublicDemo(['upload_behavior' => DemoMode::UPLOAD_BEHAVIOR_DISABLED]);

        $this->post(route('artworks.store'), [
            'title' => 'With Photo',
            'dimension_unit' => 'in',
            'photo' => UploadedFile::fake()->image('piece.jpg'),
        ])->assertForbidden();
    }

    public function test_photo_service_blocks_storage_when_uploads_disabled(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();
        $this->configurePublicDemo(['upload_behavior' => DemoMode::UPLOAD_BEHAVIOR_DISABLED]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ArtworkPhotoService::class)->store(
            $artwork,
            UploadedFile::fake()->image('direct.jpg'),
        );
    }

    public function test_photo_service_blocks_storage_when_uploads_discarded(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();
        $this->configurePublicDemo(['upload_behavior' => DemoMode::UPLOAD_BEHAVIOR_DISCARD]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ArtworkPhotoService::class)->store(
            $artwork,
            UploadedFile::fake()->image('direct.jpg'),
        );
    }

    public function test_photo_delete_via_artwork_destroy_is_blocked_in_public_demo(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();
        $photo = app(ArtworkPhotoService::class)->store(
            $artwork,
            UploadedFile::fake()->image('existing.jpg'),
        );
        $this->configurePublicDemo();

        $this->delete(route('artworks.destroy', $artwork))->assertForbidden();
        $this->assertModelExists($artwork);
        $this->assertModelExists($photo);
    }

    public function test_photo_service_delete_blocks_when_deletes_disabled(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();
        app(ArtworkPhotoService::class)->store(
            $artwork,
            UploadedFile::fake()->image('existing.jpg'),
        );
        $this->configurePublicDemo();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ArtworkPhotoService::class)->deletePhotosForArtwork($artwork);
    }

    public function test_profile_and_password_routes_return_403_in_public_demo(): void
    {
        $user = $this->signIn();
        $this->configurePublicDemo();

        $this->patch(route('profile.update'), [
            'name' => 'Blocked',
            'email' => $user->email,
        ])->assertForbidden();

        $this->patch(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-9',
            'password_confirmation' => 'new-password-9',
        ])->assertForbidden();
    }

    public function test_setup_registration_route_returns_403_in_public_demo(): void
    {
        $this->configurePublicDemo();

        $this->post(route('setup.store'), [
            'name' => 'Blocked Owner',
            'email' => 'owner@blocked.test',
            'password' => 'password-1',
            'password_confirmation' => 'password-1',
        ])->assertForbidden();

        $this->assertSame(0, User::query()->count());
    }

    public function test_password_reset_guard_returns_403_in_public_demo(): void
    {
        $this->configurePublicDemo();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        try {
            DemoOutbound::ensurePasswordResetAllowed();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(DemoMode::MESSAGE_PASSWORD_RESET, $exception->getMessage());

            throw $exception;
        }
    }

    public function test_pro_file_write_and_delete_guards_return_403_in_public_demo(): void
    {
        $this->configurePublicDemo(['upload_behavior' => DemoMode::UPLOAD_BEHAVIOR_DISABLED]);

        try {
            DemoOutbound::ensureProFileWriteAllowed();
            $this->fail('Expected Pro file write guard to throw.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            DemoOutbound::ensureProFileDeleteAllowed();
            $this->fail('Expected Pro file delete guard to throw.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(DemoMode::MESSAGE_DELETES, $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configurePublicDemo(array $overrides = []): void
    {
        config([
            'easelogs.demo_mode' => true,
            'easelogs.demo' => array_merge([
                'upload_behavior' => DemoMode::UPLOAD_BEHAVIOR_DISCARD,
                'allow_imports' => false,
                'allow_deletes' => false,
                'allow_account_changes' => false,
                'allow_registration' => false,
                'allow_password_reset' => false,
                'allow_email_sending' => false,
                'allow_external_webhooks' => false,
                'show_public_notice' => false,
                'user' => [
                    'name' => 'Demo Visitor',
                    'email' => 'demo@easelogs.test',
                    'password' => 'demo-pass-123',
                    'show_login_hint' => false,
                    'allow_one_click_login' => false,
                ],
            ], $overrides),
        ]);
    }
}
