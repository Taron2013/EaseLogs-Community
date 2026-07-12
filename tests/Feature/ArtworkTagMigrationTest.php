<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkTag;
use App\Support\ArtworkTagType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArtworkTagMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_migration_backfills_existing_flat_tags_as_general(): void
    {
        $this->artisan('migrate:rollback', [
            '--path' => 'database/migrations/2026_07_05_000001_add_type_to_artwork_tags_table.php',
        ])->assertSuccessful();

        $user = \App\Models\User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Legacy Tagged']);

        $tagId = DB::table('artwork_tags')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Legacy Flat Tag',
            'normalized_name' => 'legacy flat tag',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('artwork_tag')->insert([
            'artwork_id' => $artwork->id,
            'artwork_tag_id' => $tagId,
        ]);

        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_07_05_000001_add_type_to_artwork_tags_table.php',
        ])->assertSuccessful();

        $this->assertDatabaseHas('artwork_tags', [
            'id' => $tagId,
            'name' => 'Legacy Flat Tag',
            'normalized_name' => 'legacy flat tag',
            'type' => ArtworkTagType::GENERAL,
        ]);

        $this->assertDatabaseHas('artwork_tag', [
            'artwork_id' => $artwork->id,
            'artwork_tag_id' => $tagId,
        ]);
    }
}
