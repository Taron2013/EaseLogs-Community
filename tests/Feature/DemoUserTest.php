<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use App\Support\DemoMode;
use App\Support\DemoUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_or_updates_demo_user(): void
    {
        $this->configureDemoUser();

        $this->seed(\Database\Seeders\DemoSeeder::class);

        $user = User::query()->where('email', 'demo@easelogs.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Demo Visitor', $user->name);
        $this->assertTrue(DemoUser::isDemoUser($user));

        $this->seed(\Database\Seeders\DemoSeeder::class);

        $user->update(['name' => 'Tampered Name']);

        $this->seed(\Database\Seeders\DemoSeeder::class);

        $this->assertSame('Demo Visitor', $user->fresh()->name);
    }

    public function test_demo_seeder_creates_sample_artworks(): void
    {
        $this->configureDemoUser();

        $this->seed(\Database\Seeders\DemoSeeder::class);

        $user = User::query()->where('email', 'demo@easelogs.test')->first();

        $this->assertNotNull($user);
        $this->assertGreaterThanOrEqual(3, $user->artworks()->count());
        $this->assertTrue($user->artworks()->where('title', 'Morning Light')->exists());
    }

    public function test_login_hint_visible_when_enabled(): void
    {
        User::factory()->create();
        $this->configureDemoUser(['show_login_hint' => true]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Demo account', false)
            ->assertSee('demo@easelogs.test', false)
            ->assertSee('demo-pass-123', false);
    }

    public function test_login_hint_hidden_when_disabled(): void
    {
        User::factory()->create();
        $this->configureDemoUser(userOverrides: ['show_login_hint' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Demo account', false)
            ->assertDontSee('demo-pass-123', false);
    }

    public function test_one_click_login_signs_in_demo_user(): void
    {
        $this->configureDemoUser(userOverrides: ['allow_one_click_login' => true]);
        $this->seed(\Database\Seeders\DemoSeeder::class);

        $response = $this->post(route('login.demo'));

        $response->assertRedirect(route('artworks.index'));
        $this->assertAuthenticatedAs(User::query()->where('email', 'demo@easelogs.test')->first());
    }

    public function test_one_click_login_returns_404_when_disabled(): void
    {
        $this->configureDemoUser(userOverrides: ['allow_one_click_login' => false]);
        $this->seed(\Database\Seeders\DemoSeeder::class);

        $this->post(route('login.demo'))->assertNotFound();
        $this->assertGuest();
    }

    public function test_demo_user_account_changes_blocked_when_disabled(): void
    {
        $this->configureDemoUser(demoOverrides: ['allow_account_changes' => false]);
        $user = DemoUser::ensureExists();
        $this->actingAs($user);

        $this->patch(route('profile.update'), [
            'name' => 'Changed Demo Name',
            'email' => $user->email,
        ])->assertForbidden();

        $this->patch(route('profile.password.update'), [
            'current_password' => 'demo-pass-123',
            'password' => 'new-demo-pass-9',
            'password_confirmation' => 'new-demo-pass-9',
        ])->assertForbidden();

        $this->assertSame('Demo Visitor', $user->fresh()->name);
    }

    public function test_demo_ensure_command_updates_user_without_full_reset(): void
    {
        $this->configureDemoUser();

        Artisan::call('easelogs:demo-ensure');

        $user = User::query()->where('email', 'demo@easelogs.test')->first();

        $this->assertNotNull($user);
        $this->assertGreaterThanOrEqual(3, $user->artworks()->count());
    }

    public function test_standard_login_works_with_demo_credentials(): void
    {
        $this->configureDemoUser();
        DemoUser::ensureExists();

        $this->post(route('login.store'), [
            'email' => 'demo@easelogs.test',
            'password' => 'demo-pass-123',
        ])->assertRedirect(route('artworks.index'));

        $this->assertAuthenticated();
    }

    /**
     * @param  array<string, mixed>  $demoOverrides
     * @param  array<string, mixed>  $userOverrides
     */
    private function configureDemoUser(array $demoOverrides = [], array $userOverrides = []): void
    {
        $demo = array_merge([
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
                'show_login_hint' => true,
                'allow_one_click_login' => true,
            ],
        ], $demoOverrides);

        $demo['user'] = array_merge($demo['user'], $userOverrides);

        config([
            'easelogs.demo_mode' => true,
            'easelogs.demo' => $demo,
        ]);
    }
}
