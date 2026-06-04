<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_setup_when_no_users(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('setup.create'));
    }

    public function test_artworks_requires_setup_or_auth(): void
    {
        $response = $this->get(route('artworks.index'));

        $response->assertRedirect(route('setup.create'));
    }
}
