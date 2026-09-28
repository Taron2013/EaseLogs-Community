<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Models\User;
use App\Support\Storage\InstallationStorageIdentity;
use App\Support\Storage\InstallationStorageIdentityMissingException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class B41S1SchemaAndInstallationIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_adds_uuids_nullable_bindings_and_nullable_file_path(): void
    {
        $this->assertTrue(Schema::hasColumn('artworks', 'uuid'));
        $this->assertTrue(Schema::hasColumn('artwork_photos', 'uuid'));
        $this->assertTrue(Schema::hasColumn('artwork_photos', 'storage_key'));
        $this->assertTrue(Schema::hasColumn('artwork_photos', 'storage_config_target'));

        $columns = collect(Schema::getColumns('artwork_photos'))->keyBy('name');

        $this->assertTrue($columns['file_path']['nullable']);
        $this->assertTrue($columns['storage_key']['nullable']);
        $this->assertTrue($columns['storage_config_target']['nullable']);
    }

    public function test_app_settings_setting_value_is_nullable(): void
    {
        $columns = collect(Schema::getColumns('app_settings'))->keyBy('name');

        $this->assertTrue($columns['setting_value']['nullable']);
    }

    public function test_migration_inserts_a_stable_installation_uuid(): void
    {
        $value = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');

        $this->assertIsString($value);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $value,
        );

        $service = new InstallationStorageIdentity;
        $this->assertSame($value, $service->ensure());
        $this->assertSame($value, $service->ensure());
    }

    public function test_existing_photo_rows_remain_legacy_after_s1(): void
    {
        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Keep Path']);
        $path = 'artworks/'.$artwork->id.'/keep.jpg';

        $photo = $artwork->photos()->create([
            'file_path' => $path,
            'original_filename' => 'keep.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 9,
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $fresh = $photo->fresh();
        $this->assertSame($path, $fresh->file_path);
        $this->assertNull($fresh->storage_key);
        $this->assertNull($fresh->storage_config_target);
        $this->assertNotEmpty($fresh->uuid);
        $this->assertNotEmpty($artwork->fresh()->uuid);
    }

    public function test_artwork_and_photo_uuids_are_immutable(): void
    {
        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create();
        $photo = $artwork->photos()->create([
            'file_path' => 'artworks/'.$artwork->id.'/u.jpg',
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $artworkUuid = $artwork->uuid;
        $photoUuid = $photo->uuid;

        $artwork->update(['title' => 'Renamed', 'uuid' => '11111111-1111-4111-8111-111111111111']);
        $photo->update(['caption' => 'x', 'uuid' => '22222222-2222-4222-8222-222222222222']);

        $this->assertSame($artworkUuid, $artwork->fresh()->uuid);
        $this->assertSame($photoUuid, $photo->fresh()->uuid);
        $this->assertSame('Renamed', $artwork->fresh()->title);
    }

    public function test_database_rejects_duplicate_artwork_uuid(): void
    {
        $user = User::factory()->create();
        $first = Artwork::factory()->for($user)->create();
        $second = Artwork::factory()->for($user)->create();

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('artworks')->where('id', $second->id)->update(['uuid' => $first->uuid]);
    }

    public function test_database_rejects_duplicate_artwork_photo_uuid(): void
    {
        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create();
        $first = $artwork->photos()->create([
            'file_path' => 'artworks/'.$artwork->id.'/a.jpg',
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);
        $second = $artwork->photos()->create([
            'file_path' => 'artworks/'.$artwork->id.'/b.jpg',
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => false,
            'uploaded_at' => now(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('artwork_photos')->where('id', $second->id)->update(['uuid' => $first->uuid]);
    }

    public function test_null_file_path_is_not_treated_as_present_on_public_disk(): void
    {
        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create();
        $photo = $artwork->photos()->create([
            'file_path' => null,
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $this->assertNull($photo->fresh()->file_path);
        $this->assertFalse($photo->fresh()->existsOnDisk());
    }

    public function test_missing_identity_without_private_bindings_recovers(): void
    {
        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->delete();

        $uuid = (new InstallationStorageIdentity)->ensure();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
        );
        $this->assertSame($uuid, (new InstallationStorageIdentity)->ensure());
    }

    public function test_invalid_identity_without_private_bindings_recovers(): void
    {
        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->update(['setting_value' => 'not-a-uuid']);

        $uuid = (new InstallationStorageIdentity)->ensure();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
        );
        $this->assertSame($uuid, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    public function test_missing_identity_with_empty_storage_key_fails_closed(): void
    {
        $this->bindPrivatePhoto(storageKey: '', storageConfigTarget: null);

        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->delete();

        $this->expectException(InstallationStorageIdentityMissingException::class);
        (new InstallationStorageIdentity)->ensure();
    }

    public function test_missing_identity_with_only_storage_config_target_fails_closed(): void
    {
        $this->bindPrivatePhoto(storageKey: null, storageConfigTarget: 'local_persistent');

        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->delete();

        $this->expectException(InstallationStorageIdentityMissingException::class);
        (new InstallationStorageIdentity)->ensure();
    }

    public function test_invalid_identity_with_empty_storage_config_target_fails_closed(): void
    {
        $this->bindPrivatePhoto(storageKey: null, storageConfigTarget: '');

        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->update(['setting_value' => 'not-a-uuid']);

        $before = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');

        try {
            (new InstallationStorageIdentity)->ensure();
            $this->fail('Expected InstallationStorageIdentityMissingException');
        } catch (InstallationStorageIdentityMissingException) {
            $this->assertSame($before, DB::table('app_settings')
                ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
                ->value('setting_value'));
        }
    }

    public function test_invalid_identity_with_non_null_storage_key_fails_closed(): void
    {
        $this->bindPrivatePhoto(
            storageKey: 'tenants/example/artwork_photos/a/b/original.jpg',
            storageConfigTarget: 'local_persistent',
        );

        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->update(['setting_value' => 'not-a-uuid']);

        $before = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');

        try {
            (new InstallationStorageIdentity)->ensure();
            $this->fail('Expected InstallationStorageIdentityMissingException');
        } catch (InstallationStorageIdentityMissingException) {
            $this->assertSame($before, DB::table('app_settings')
                ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
                ->value('setting_value'));
        }
    }

    public function test_compare_and_swap_updates_when_expected_string_matches(): void
    {
        $current = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');
        $next = '11111111-1111-4111-8111-111111111111';

        $this->assertSame(1, $this->swapper()->swap($current, $next));
        $this->assertSame($next, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    public function test_compare_and_swap_updates_when_expected_value_is_sql_null(): void
    {
        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->update(['setting_value' => null]);

        $next = '11111111-1111-4111-8111-111111111111';

        $this->assertSame(1, $this->swapper()->swap(null, $next));
        $this->assertSame($next, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    public function test_compare_and_swap_returns_zero_when_expected_string_does_not_match(): void
    {
        $current = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');

        $this->assertSame(0, $this->swapper()->swap('not-the-value', '22222222-2222-4222-8222-222222222222'));
        $this->assertSame($current, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    public function test_compare_and_swap_returns_zero_when_expected_null_but_value_is_set(): void
    {
        $current = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');

        $this->assertSame(0, $this->swapper()->swap(null, '22222222-2222-4222-8222-222222222222'));
        $this->assertSame($current, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    public function test_second_raw_insert_of_installation_key_violates_unique_constraint(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('app_settings')->insert([
            'setting_key' => InstallationStorageIdentity::SETTING_KEY,
            'setting_value' => '22222222-2222-4222-8222-222222222222',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Deterministic hook interleaving — not simultaneous-request proof.
     */
    public function test_missing_row_unique_constraint_recovery_returns_the_competing_identity(): void
    {
        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->delete();

        $winner = '11111111-1111-4111-8111-111111111111';
        $service = new class($winner) extends InstallationStorageIdentity
        {
            public function __construct(private readonly string $establishedWinner) {}

            protected function insertIdentity(string $uuid): void
            {
                DB::table('app_settings')->insert([
                    'setting_key' => self::SETTING_KEY,
                    'setting_value' => $this->establishedWinner,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                parent::insertIdentity($uuid);
            }
        };

        $this->assertSame($winner, $service->ensure());
        $this->assertSame($winner, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    /**
     * Deterministic hook interleaving — not simultaneous-request proof.
     */
    public function test_interleaved_repair_cannot_overwrite_a_competing_identity(): void
    {
        DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->update(['setting_value' => 'not-a-uuid']);

        $winner = '11111111-1111-4111-8111-111111111111';
        $service = new class($winner) extends InstallationStorageIdentity
        {
            public function __construct(private readonly string $establishedWinner) {}

            protected function compareAndSwapSettingValue(mixed $expectedRaw, string $newUuid): int
            {
                DB::table('app_settings')
                    ->where('setting_key', self::SETTING_KEY)
                    ->update([
                        'setting_value' => $this->establishedWinner,
                        'updated_at' => now(),
                    ]);

                return parent::compareAndSwapSettingValue($expectedRaw, $newUuid);
            }
        };

        $this->assertSame($winner, $service->ensure());
        $this->assertSame($winner, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    private function swapper(): InstallationStorageIdentity
    {
        return new class extends InstallationStorageIdentity
        {
            public function swap(mixed $expectedRaw, string $newUuid): int
            {
                return $this->compareAndSwapSettingValue($expectedRaw, $newUuid);
            }
        };
    }

    private function bindPrivatePhoto(?string $storageKey, ?string $storageConfigTarget): ArtworkPhoto
    {
        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create();

        return $artwork->photos()->create([
            'file_path' => null,
            'storage_key' => $storageKey,
            'storage_config_target' => $storageConfigTarget,
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);
    }
}
