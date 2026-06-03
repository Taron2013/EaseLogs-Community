<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstRunSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_users_redirects_artworks_to_setup(): void
    {
        $response = $this->get(route('artworks.index'));

        $response->assertRedirect(route('setup.create'));
    }

    public function test_home_redirects_to_setup_when_no_users(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('setup.create'));
    }

    public function test_setup_form_creates_first_user_and_logs_in(): void
    {
        $response = $this->post(route('setup.store'), [
            'name' => 'Studio Owner',
            'email' => 'owner@studio.test',
            'password' => 'secure-pass-1',
            'password_confirmation' => 'secure-pass-1',
        ]);

        $response->assertRedirect(route('artworks.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Studio Owner',
            'email' => 'owner@studio.test',
        ]);
        $this->assertAuthenticatedAs(User::query()->first());
    }

    public function test_setup_is_inaccessible_after_first_user_exists(): void
    {
        $this->signIn();

        $this->get(route('setup.create'))->assertNotFound();
        $this->post(route('setup.store'), [
            'name' => 'Second User',
            'email' => 'second@studio.test',
            'password' => 'another-pass-1',
            'password_confirmation' => 'another-pass-1',
        ])->assertNotFound();

        $this->assertSame(1, User::query()->count());
    }

    public function test_artworks_require_login_after_setup(): void
    {
        User::factory()->create();

        $this->get(route('artworks.index'))->assertRedirect(route('login'));
        $this->get(route('artworks.create'))->assertRedirect(route('login'));
        $this->get(route('artworks.import-export'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_artworks(): void
    {
        $this->signIn();

        $this->get(route('artworks.index'))->assertOk();
    }

    public function test_login_allows_access_to_artworks(): void
    {
        User::factory()->create([
            'email' => 'owner@studio.test',
            'password' => 'secure-pass-1',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'owner@studio.test',
            'password' => 'secure-pass-1',
        ]);

        $response->assertRedirect(route('artworks.index'));
        $this->assertAuthenticated();
        $this->get(route('artworks.index'))->assertOk();
    }

    public function test_database_seeder_does_not_create_default_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::query()->count());
    }

    public function test_csv_import_without_users_redirects_to_setup(): void
    {
        $response = $this->post(route('artworks.import.csv'), []);

        $response->assertRedirect(route('setup.create'));
    }
}
