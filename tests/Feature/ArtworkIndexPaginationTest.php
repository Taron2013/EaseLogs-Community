<?php

namespace Tests\Feature;

use App\Models\Artwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkIndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_artworks_index_renders_ok_with_paginated_results(): void
    {
        $user = $this->signIn();

        Artwork::factory()->count(21)->for($user)->sequence(
            fn ($sequence) => [
                'title' => 'Listed Item '.$sequence->index,
                'completed_date' => null,
            ],
        )->create();

        $page1 = $this->get(route('artworks.index'));

        $page1->assertOk();
        $page1->assertSee('Listed Item', false);
        $page1->assertSee('page=2', false);
        $page1->assertSee('easelogs-pagination', false);

        $this->get(route('artworks.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('easelogs-pagination-current', false);
    }

    public function test_index_uses_compact_text_pagination_without_svg_chevrons(): void
    {
        $user = $this->signIn();

        Artwork::factory()->count(21)->for($user)->sequence(
            fn ($sequence) => ['title' => 'Paged Item '.$sequence->index],
        )->create();

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertViewIs('artworks.index');
        $this->assertFileExists(resource_path('views/vendor/pagination/easelogs.blade.php'));
        $response->assertSee('easelogs-pagination', false);
        $response->assertSee('Previous', false);
        $response->assertSee('Next', false);
        $response->assertSee('page=2', false);
        $response->assertDontSee('<svg', false);
    }

    public function test_pagination_preserves_filter_and_sort_query_strings(): void
    {
        $user = $this->signIn();

        Artwork::factory()->count(21)->for($user)->create([
            'completed_date' => null,
            'title' => 'Filtered Page Item',
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Completed Other',
            'completed_date' => '2026-01-01',
        ]);

        $page1 = $this->get(route('artworks.index', [
            'filter' => 'in_progress',
            'sort' => 'title',
            'direction' => 'asc',
        ]));

        $page1->assertOk();
        $page1->assertSee('filter=in_progress', false);
        $page1->assertSee('sort=title', false);
        $page1->assertSee('direction=asc', false);

        $page2 = $this->get(route('artworks.index', [
            'filter' => 'in_progress',
            'sort' => 'title',
            'direction' => 'asc',
            'page' => 2,
        ]));

        $page2->assertOk();
        $page2->assertSee('Filtered Page Item', false);
        $page2->assertDontSee('Completed Other', false);
    }
}
