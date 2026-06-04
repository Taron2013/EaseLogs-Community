<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RebootsApplicationEnv;
use Tests\TestCase;

class UrlPrefixTest extends TestCase
{
    use RefreshDatabase;
    use RebootsApplicationEnv;

    protected function tearDown(): void
    {
        $this->rebootApplicationEnv('EASELOGS_URL_PREFIX', null);

        parent::tearDown();
    }

    public function test_routes_work_without_url_prefix(): void
    {
        $this->rebootApplicationEnv('EASELOGS_URL_PREFIX', null);

        $this->assertSame('', config('easelogs.url_prefix'));
        $this->assertSame('/login', route('login', [], false));
        $this->assertSame('/artworks', route('artworks.index', [], false));
        $this->assertSame('/profile', route('profile.show', [], false));
        $this->assertSame('/setup', route('setup.create', [], false));

        $this->get(route('home'))
            ->assertRedirect(route('setup.create'));

        User::factory()->create();

        $this->get(route('home'))
            ->assertRedirect(route('login'));

        $this->get(route('login'))->assertOk();
    }

    public function test_routes_work_with_community_url_prefix(): void
    {
        $this->rebootApplicationEnv('EASELOGS_URL_PREFIX', 'community');

        $this->assertSame('community', config('easelogs.url_prefix'));
        $this->assertSame('/community', route('home', [], false));
        $this->assertSame('/community/login', route('login', [], false));
        $this->assertSame('/community/artworks', route('artworks.index', [], false));
        $this->assertSame('/community/profile', route('profile.show', [], false));
        $this->assertSame('/community/setup', route('setup.create', [], false));

        $this->get(route('home'))
            ->assertRedirect(route('setup.create'));

        User::factory()->create();

        $this->get(route('home'))
            ->assertRedirect(route('login'));

        $this->get(route('login'))->assertOk();
        $this->get('/community/login')->assertOk();
        $this->get('/login')->assertNotFound();
    }
}
