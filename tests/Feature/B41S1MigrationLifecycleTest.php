<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use App\Support\Storage\InstallationStorageIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class B41S1MigrationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SQLite DDL commits implicitly. A wrapping RefreshDatabase transaction
     * can hide historical inserts across rollback/reapply. This class is run
     * in a dedicated PHPUnit process.
     *
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [];

    private const S1_PATH = 'database/migrations/2026_09_27_000001_add_b41_s1_schema_and_installation_identity.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetIsolatedTestDatabase();
    }

    protected function tearDown(): void
    {
        try {
            $this->assertDisposableSqliteTestDatabase();
        } finally {
            parent::tearDown();
        }
    }

    public function test_historical_uuid_backfill_preserves_legacy_file_path(): void
    {
        $this->assertDisposableSqliteTestDatabase();

        $userId = User::factory()->create()->id;

        $this->rollbackS1();
        $this->assertFalse(Schema::hasColumn('artworks', 'uuid'));
        $this->assertFalse(Schema::hasColumn('artwork_photos', 'storage_key'));
        $this->assertTrue(Schema::hasTable('artworks'));
        $this->assertTrue(Schema::hasTable('artwork_photos'));
        $this->assertTrue(Schema::hasTable('app_settings'));

        $artworkId = DB::table('artworks')->insertGetId([
            'user_id' => $userId,
            'title' => 'Historical',
            'dimension_unit' => 'in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $path = 'artworks/'.$artworkId.'/old.jpg';
        $photoId = DB::table('artwork_photos')->insertGetId([
            'artwork_id' => $artworkId,
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotNull(DB::table('artworks')->where('id', $artworkId)->first());
        $this->assertNotNull(DB::table('artwork_photos')->where('id', $photoId)->first());

        $this->reapplyS1();

        $artwork = DB::table('artworks')->where('id', $artworkId)->first();
        $photo = DB::table('artwork_photos')->where('id', $photoId)->first();

        $this->assertNotNull($artwork);
        $this->assertNotNull($photo);
        $this->assertNotNull($artwork->uuid);
        $this->assertNotNull($photo->uuid);
        $this->assertNotSame($artwork->uuid, $photo->uuid);
        $this->assertSame($path, $photo->file_path);
        $this->assertNull($photo->storage_key);
        $this->assertNull($photo->storage_config_target);
    }

    public function test_installation_identity_survives_rollback_and_reapply(): void
    {
        $this->assertDisposableSqliteTestDatabase();

        $before = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');
        $this->assertIsString($before);

        $this->rollbackS1();

        $this->assertSame($before, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
        $this->assertTrue(Schema::hasTable('app_settings'));
        $this->assertTrue(Schema::hasTable('users'));

        $this->reapplyS1();

        $this->assertSame($before, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    public function test_rollback_refuses_when_file_path_is_null(): void
    {
        $this->assertDisposableSqliteTestDatabase();

        $identity = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');

        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create();
        $artwork->photos()->create([
            'file_path' => null,
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $this->assertRollbackRefused('NULL file_path');

        $this->assertS1SchemaPresent();
        $this->assertSame($identity, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    public function test_rollback_refuses_when_storage_key_is_empty_string(): void
    {
        $this->assertDisposableSqliteTestDatabase();

        $identity = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');

        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create();
        $artwork->photos()->create([
            'file_path' => 'artworks/'.$artwork->id.'/bound.jpg',
            'storage_key' => '',
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $this->assertRollbackRefused('private storage binding evidence');

        $this->assertS1SchemaPresent();
        $this->assertSame($identity, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    public function test_rollback_refuses_when_storage_config_target_is_set(): void
    {
        $this->assertDisposableSqliteTestDatabase();

        $identity = DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value');

        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create();
        $artwork->photos()->create([
            'file_path' => 'artworks/'.$artwork->id.'/bound.jpg',
            'storage_config_target' => 'local_persistent',
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $this->assertRollbackRefused('private storage binding evidence');

        $this->assertS1SchemaPresent();
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertSame($identity, DB::table('app_settings')
            ->where('setting_key', InstallationStorageIdentity::SETTING_KEY)
            ->value('setting_value'));
    }

    private function resetIsolatedTestDatabase(): void
    {
        $this->assertDisposableSqliteTestDatabase();
        $this->artisan('migrate:fresh', $this->migrateFreshUsing())->assertSuccessful();
        $this->assertDisposableSqliteTestDatabase();
        $this->ensureS1Schema();
    }

    private function rollbackS1(): void
    {
        $this->assertDisposableSqliteTestDatabase();
        $this->artisan('migrate:rollback', [
            '--path' => self::S1_PATH,
        ])->assertSuccessful();
    }

    private function reapplyS1(): void
    {
        $this->assertDisposableSqliteTestDatabase();
        $this->artisan('migrate', [
            '--path' => self::S1_PATH,
        ])->assertSuccessful();
    }

    private function ensureS1Schema(): void
    {
        if (! Schema::hasColumn('artworks', 'uuid')
            || ! Schema::hasColumn('artwork_photos', 'storage_key')
            || ! Schema::hasColumn('artwork_photos', 'storage_config_target')) {
            $this->reapplyS1();
        }
    }

    private function assertS1SchemaPresent(): void
    {
        $this->assertTrue(Schema::hasColumn('artworks', 'uuid'));
        $this->assertTrue(Schema::hasColumn('artwork_photos', 'uuid'));
        $this->assertTrue(Schema::hasColumn('artwork_photos', 'storage_key'));
        $this->assertTrue(Schema::hasColumn('artwork_photos', 'storage_config_target'));
    }

    /**
     * Laravel 13 PendingCommand::run() does not catch RuntimeException from
     * Kernel::call(), so a refused rollback throws through artisan()->run().
     * Unrelated exceptions are not swallowed.
     */
    private function assertRollbackRefused(string $messageFragment): void
    {
        try {
            $this->artisan('migrate:rollback', [
                '--path' => self::S1_PATH,
            ])->run();
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), $messageFragment)) {
                throw $e;
            }

            $this->assertS1SchemaPresent();

            return;
        }

        $this->fail('Expected S1 rollback to refuse.');
    }
}
