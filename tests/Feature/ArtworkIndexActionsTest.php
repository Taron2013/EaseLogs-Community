<?php

namespace Tests\Feature;

use App\Models\Artwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkIndexActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_actions_column_with_view_edit_and_delete_per_row(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create(['title' => 'Indexed Piece']);

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('<th>Actions</th>', false);
        $response->assertSee('artwork-actions-stack', false);
        $response->assertSee(route('artworks.show', $artwork), false);
        $response->assertSee(route('artworks.edit', $artwork), false);
        $response->assertSee('>View</a>', false);
        $response->assertSee('>Edit</a>', false);
        $response->assertSee('>Delete</button>', false);
    }

    public function test_delete_form_targets_destroy_route_with_method_spoofing_and_csrf(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create(['title' => 'To Delete From Index']);

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee(route('artworks.destroy', $artwork), false);
        $response->assertSee('method="POST"', false);
        $response->assertSee('name="_method" value="DELETE"', false);
        $response->assertSee('name="_token"', false);
        $response->assertSee("return confirm('Delete this artwork? This cannot be undone.');", false);
    }

    public function test_actions_column_is_not_sortable(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Action Column Row']);

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('<th>Actions</th>', false);
        $response->assertDontSee('sort=actions', false);
    }

    public function test_index_table_is_horizontally_scrollable_on_narrow_viewports(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Scrollable Row']);

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('class="artwork-table-wrap"', false);
    }
}
