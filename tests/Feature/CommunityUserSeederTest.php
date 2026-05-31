<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CommunityUser;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_default_community_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'name' => CommunityUser::NAME,
            'email' => CommunityUser::EMAIL,
        ]);
    }

    public function test_database_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('email', CommunityUser::EMAIL)->count());
    }

    public function test_artworks_index_shows_setup_message_when_no_users(): void
    {
        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('php artisan db:seed', false);
        $response->assertSee('admin@easelogs.local', false);
    }

    public function test_artworks_index_hides_setup_message_when_user_exists(): void
    {
        User::factory()->create();

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertDontSee('php artisan db:seed', false);
    }

    public function test_layout_shows_default_credentials_warning_for_community_user(): void
    {
        CommunityUser::seedIfMissing();

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('Default Community account is active', false);
        $response->assertSee('admin@easelogs.local', false);
        $response->assertSee('password', false);
    }

    public function test_layout_hides_default_credentials_warning_for_other_users(): void
    {
        User::factory()->create(['email' => 'studio@example.com']);

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertDontSee('Default Community account is active', false);
    }
}
