<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use App\Support\DemoMode;
use App\Support\DemoOutbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoModeSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_public_demo_banner_appears_on_authenticated_pages(): void
    {
        $this->signIn();
        $this->configurePublicDemo();

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee(DemoMode::PUBLIC_BANNER_MESSAGE, false);
    }

    public function test_public_demo_banner_appears_on_login_page(): void
    {
        User::factory()->create();
        $this->configurePublicDemo();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(DemoMode::PUBLIC_BANNER_MESSAGE, false);
    }

    public function test_profile_and_password_updates_are_blocked(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-secret-1'),
        ]);
        $this->actingAs($user);
        $this->configurePublicDemo();

        $this->patch(route('profile.update'), [
            'name' => 'Changed Name',
            'email' => $user->email,
        ])->assertForbidden();

        $this->patch(route('profile.password.update'), [
            'current_password' => 'current-secret-1',
            'password' => 'new-password-9',
            'password_confirmation' => 'new-password-9',
        ])->assertForbidden();

        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_profile_edit_links_hidden_when_account_changes_blocked(): void
    {
        $this->signIn();
        $this->configurePublicDemo();

        $this->get(route('profile.show'))
            ->assertOk()
            ->assertSee(DemoMode::MESSAGE_ACCOUNT_CHANGES, false)
            ->assertDontSee(route('profile.edit'), false)
            ->assertDontSee(route('profile.password.edit'), false);
    }

    public function test_deletes_and_bulk_delete_are_blocked(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();
        $this->configurePublicDemo();

        $this->delete(route('artworks.destroy', $artwork))->assertForbidden();
        $this->assertModelExists($artwork);

        $other = Artwork::factory()->for($user)->create();

        $this->delete(route('artworks.bulk-delete'), [
            'ids' => [$artwork->id, $other->id],
        ])->assertForbidden();

        $this->assertSame(2, Artwork::query()->count());
    }

    public function test_csv_import_is_blocked(): void
    {
        $this->signIn();
        $this->configurePublicDemo();

        $response = $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', "title\nRow\n"),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Artwork::query()->count());
    }

    public function test_registration_setup_is_blocked_when_no_users_exist(): void
    {
        $this->configurePublicDemo();

        $this->post(route('setup.store'), [
            'name' => 'Demo Owner',
            'email' => 'owner@demo.test',
            'password' => 'password-1',
            'password_confirmation' => 'password-1',
        ])->assertForbidden();

        $this->assertSame(0, User::query()->count());
    }

    public function test_outbound_email_is_not_sent_in_demo_mode(): void
    {
        Mail::fake();
        $this->configurePublicDemo(['allow_email_sending' => false]);

        Mail::raw('Demo test message', function ($message): void {
            $message->to('visitor@demo.test')->subject('EaseLogs demo');
        });

        Mail::assertNothingSent();
    }

    public function test_demo_mode_forces_array_mail_driver_when_email_blocked(): void
    {
        $this->configurePublicDemo(['allow_email_sending' => false]);

        $this->assertTrue(DemoMode::blocks('email_sending'));
        $this->assertSame('array', config('mail.default'));
    }

    public function test_external_webhook_guard_returns_403(): void
    {
        $this->configurePublicDemo();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        try {
            DemoOutbound::ensureWebhookAllowed();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(DemoMode::MESSAGE_EXTERNAL_WEBHOOKS, $exception->getMessage());

            throw $exception;
        }
    }

    public function test_payment_action_guard_returns_403_in_demo_mode(): void
    {
        $this->configurePublicDemo();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        try {
            DemoOutbound::ensurePaymentActionAllowed();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_allowed_flags_restore_actions_when_demo_mode_enabled(): void
    {
        $user = $this->signIn();
        $this->configurePublicDemo([
            'allow_account_changes' => true,
            'allow_deletes' => true,
            'allow_imports' => true,
            'upload_behavior' => DemoMode::UPLOAD_BEHAVIOR_ENABLED,
        ]);

        $this->patch(route('profile.update'), [
            'name' => 'Allowed Name',
            'email' => $user->email,
        ])->assertRedirect(route('profile.show'));

        $this->assertSame('Allowed Name', $user->fresh()->name);
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
                'show_public_notice' => true,
            ], $overrides),
        ]);

        if (config('easelogs.demo_mode') && ! config('easelogs.demo.allow_email_sending')) {
            config(['mail.default' => 'array']);
        }
    }
}
