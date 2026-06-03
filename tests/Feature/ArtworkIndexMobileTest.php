<?php

namespace Tests\Feature;

use App\Models\Artwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkIndexMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_includes_mobile_card_layout_and_desktop_table(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Mobile Card Work',
            'artwork_type' => 'Painting',
            'medium' => 'Oil',
        ]);

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('artwork-mobile-list', false);
        $response->assertSee('artwork-mobile-card', false);
        $response->assertSee('artwork-index-table', false);
        $response->assertSee('Mobile Card Work', false);
        $response->assertSee('artwork-mobile-card-meta', false);
    }

    public function test_mobile_cards_include_actions_and_bulk_selection(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create(['title' => 'Action Card Work']);

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('artwork-mobile-card-actions', false);
        $response->assertSee('artwork-select-all-mobile', false);
        $response->assertSee(route('artworks.show', $artwork), false);
        $response->assertSee(route('artworks.edit', $artwork), false);
        $response->assertSee('>Delete</button>', false);
        $response->assertSee('artwork-row-select', false);
        $response->assertSee('bulk-delete-form', false);
    }

    public function test_index_includes_mobile_sort_controls(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Sortable Mobile Work']);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('artwork-sort-fields-mobile', false)
            ->assertSee('mobile_sort_column', false)
            ->assertSee('mobile_sort_direction', false)
            ->assertSee('Apply sort', false);
    }
}
