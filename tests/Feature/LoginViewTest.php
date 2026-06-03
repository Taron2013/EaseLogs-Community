<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_remember_me_uses_compact_inline_field_styles(): void
    {
        User::factory()->create();

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Remember me', false);
        $response->assertSee('class="field field-inline"', false);
        $response->assertSee('.field-inline input[type="checkbox"]', false);
        $response->assertSee('width: auto', false);
        $response->assertSee('id="remember"', false);
    }
}
