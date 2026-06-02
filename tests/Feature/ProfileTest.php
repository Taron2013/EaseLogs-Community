<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_profile_routes(): void
    {
        User::factory()->create();

        $this->get(route('profile.show'))->assertRedirect(route('login'));
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->get(route('profile.password.edit'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'))->assertRedirect(route('login'));
        $this->patch(route('profile.password.update'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Studio Owner',
            'email' => 'owner@studio.test',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('Studio Owner', false);
        $response->assertSee('owner@studio.test', false);
        $response->assertSee(route('profile.edit'), false);
        $response->assertSee(route('profile.password.edit'), false);
        $response->assertDontSee('$2y$', false);
    }

    public function test_authenticated_user_can_open_edit_profile_page(): void
    {
        $user = User::factory()->create(['name' => 'Edit Me', 'email' => 'edit@studio.test']);
        $this->actingAs($user);

        $response = $this->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('name="name"', false);
        $response->assertSee('value="Edit Me"', false);
        $response->assertSee('value="edit@studio.test"', false);
        $response->assertSee(route('profile.show'), false);
    }

    public function test_user_can_update_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'owner@studio.test']);
        $this->actingAs($user);

        $response = $this->patch(route('profile.update'), [
            'name' => 'New Name',
            'email' => 'owner@studio.test',
        ]);

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('success', 'Profile updated.');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_user_can_update_email(): void
    {
        $user = User::factory()->create(['name' => 'Owner', 'email' => 'old@studio.test']);
        $this->actingAs($user);

        $response = $this->patch(route('profile.update'), [
            'name' => 'Owner',
            'email' => 'new@studio.test',
        ]);

        $response->assertRedirect(route('profile.show'));
        $this->assertSame('new@studio.test', $user->fresh()->email);
    }

    public function test_email_must_be_unique_except_for_current_user(): void
    {
        $user = User::factory()->create(['email' => 'me@studio.test']);
        $this->actingAs($user);
        User::factory()->create(['email' => 'taken@studio.test']);

        $response = $this->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'taken@studio.test',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('email');
        $this->assertSame('me@studio.test', $user->fresh()->email);
    }

    public function test_user_can_open_change_password_page(): void
    {
        $this->signIn();

        $response = $this->get(route('profile.password.edit'));

        $response->assertOk();
        $response->assertSee('name="current_password"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee(route('profile.show'), false);
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-secret-1'),
        ]);
        $this->actingAs($user);

        $response = $this->from(route('profile.password.edit'))
            ->patch(route('profile.password.update'), [
                'current_password' => 'wrong-secret',
                'password' => 'new-secret-9',
                'password_confirmation' => 'new-secret-9',
            ]);

        $response->assertRedirect(route('profile.password.edit'));
        $response->assertSessionHasErrors('current_password');
    }

    public function test_password_confirmation_is_required(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-secret-1'),
        ]);
        $this->actingAs($user);

        $response = $this->from(route('profile.password.edit'))
            ->patch(route('profile.password.update'), [
                'current_password' => 'current-secret-1',
                'password' => 'new-secret-9',
                'password_confirmation' => 'different-secret',
            ]);

        $response->assertRedirect(route('profile.password.edit'));
        $response->assertSessionHasErrors('password');
    }

    public function test_password_updates_successfully_and_changes_login_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@studio.test',
            'password' => Hash::make('current-secret-1'),
        ]);
        $this->actingAs($user);

        $update = $this->patch(route('profile.password.update'), [
            'current_password' => 'current-secret-1',
            'password' => 'new-secret-9',
            'password_confirmation' => 'new-secret-9',
        ]);

        $update->assertRedirect(route('profile.show'));
        $update->assertSessionHas('success', 'Password updated.');

        $this->assertTrue(Hash::check('new-secret-9', $user->fresh()->password));
        $this->assertFalse(Hash::check('current-secret-1', $user->fresh()->password));

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => 'owner@studio.test',
            'password' => 'current-secret-1',
        ])->assertSessionHasErrors('email');

        $login = $this->post(route('login.store'), [
            'email' => 'owner@studio.test',
            'password' => 'new-secret-9',
        ]);

        $login->assertRedirect(route('artworks.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_header_includes_profile_link_separate_from_workflow_nav(): void
    {
        $this->signIn();

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('app-nav-account', false);
        $response->assertSee('app-nav-primary', false);
        $response->assertSee(route('profile.show'), false);
        $response->assertSee('>Profile</a>', false);
        $response->assertSee('Sign out', false);
    }

}
