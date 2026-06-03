<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Support\ArtworkIndexSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkIndexSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_title(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Sunset Harbor', 'notes' => null]);
        Artwork::factory()->for($user)->create(['title' => 'Mountain Path', 'notes' => null]);

        $this->get(route('artworks.index', ['q' => 'Harbor']))
            ->assertOk()
            ->assertSee('Sunset Harbor', false)
            ->assertDontSee('Mountain Path', false);
    }

    public function test_search_matches_notes(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Untitled Study',
            'notes' => 'palette knife texture study',
        ]);
        Artwork::factory()->for($user)->create([
            'title' => 'Other Work',
            'notes' => 'brush only',
        ]);

        $this->get(route('artworks.index', ['q' => 'knife']))
            ->assertOk()
            ->assertSee('Untitled Study', false)
            ->assertDontSee('Other Work', false);
    }

    public function test_empty_search_behaves_like_no_search(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Visible One']);
        Artwork::factory()->for($user)->create(['title' => 'Visible Two']);

        $this->get(route('artworks.index', ['q' => '']))
            ->assertOk()
            ->assertSee('Visible One', false)
            ->assertSee('Visible Two', false)
            ->assertDontSee('q=', false);
    }

    public function test_search_class_ignores_blank_term(): void
    {
        $search = ArtworkIndexSearch::fromRequest(request()->merge(['q' => '   ']));

        $this->assertFalse($search->hasTerm());
        $this->assertSame([], $search->queryParams());
    }
}
