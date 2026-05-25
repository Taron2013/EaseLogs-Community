<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_artworks(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/artworks');
    }

    public function test_artworks_page_returns_successfully(): void
    {
        $response = $this->get('/artworks');

        $response->assertStatus(200);
    }
}