<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoResetCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * migrate:fresh cannot run inside a database transaction (SQLite VACUUM).
     *
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [];

    public function test_demo_reset_recreates_demo_user_and_sample_data(): void
    {
        config([
            'easelogs.demo_mode' => true,
            'easelogs.demo' => [
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
            ],
        ]);

        Artisan::call('easelogs:demo-reset', ['--force' => true]);

        $user = User::query()->where('email', 'demo@easelogs.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Demo Visitor', $user->name);
        $this->assertGreaterThanOrEqual(3, Artwork::query()->where('user_id', $user->id)->count());

        $user->update(['name' => 'Stale Demo Name']);
        Artwork::query()->where('user_id', $user->id)->delete();

        Artisan::call('easelogs:demo-reset', ['--force' => true]);

        $user = User::query()->where('email', 'demo@easelogs.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Demo Visitor', $user->fresh()->name);
        $this->assertGreaterThanOrEqual(3, Artwork::query()->where('user_id', $user->id)->count());
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }
}
