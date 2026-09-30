<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use App\Support\PersistentFileStorage\StorageDomain;
use App\Support\PersistentFileStorage\StorageKeyFactory;
use App\Support\Storage\InstallationStorageIdentity;
use App\Support\Storage\InstallationStorageIdentityMissingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class B41S2InstallationTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_matches_ensure_uuid_and_studios_prefix(): void
    {
        $identity = new InstallationStorageIdentity;
        $uuid = $identity->ensure();
        $scope = $identity->tenantScope();

        $this->assertSame($uuid, $scope->uuid);
        $this->assertSame('studios/'.$uuid, $scope->keyPrefix());
    }

    public function test_make_original_builds_artwork_photo_key_from_installation_scope(): void
    {
        $identity = new InstallationStorageIdentity;
        $installUuid = $identity->ensure();
        $recordUuid = '22222222-2222-4222-8222-222222222222';
        $fileUuid = '33333333-3333-4333-8333-333333333333';

        $key = (new StorageKeyFactory)->makeOriginal(
            $identity->tenantScope(),
            StorageDomain::ArtworkPhotos,
            $recordUuid,
            $fileUuid,
            'JPG',
        );

        $this->assertSame(
            'studios/'.$installUuid.'/artwork_photos/'.$recordUuid.'/'.$fileUuid.'/original.jpg',
            $key->toString(),
        );
    }

    public function test_repeated_tenant_scope_calls_are_stable_without_duplicate_settings(): void
    {
        $identity = new InstallationStorageIdentity;
        $first = $identity->tenantScope();
        $second = $identity->tenantScope();

        $this->assertSame($first->uuid, $second->uuid);
        $this->assertSame($first->keyPrefix(), $second->keyPrefix());

        $this->assertSame(1, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->count());
    }

    public function test_missing_identity_with_private_bindings_fails_closed_via_tenant_scope(): void
    {
        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create();
        $artwork->photos()->create([
            'file_path' => null,
            'storage_key' => 'studios/example/artwork_photos/a/b/original.jpg',
            'storage_config_target' => 'local_persistent',
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->delete();

        $this->expectException(InstallationStorageIdentityMissingException::class);
        (new InstallationStorageIdentity)->tenantScope();
    }
}
