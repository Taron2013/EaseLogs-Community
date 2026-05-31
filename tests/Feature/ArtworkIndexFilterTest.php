<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Support\ArtworkIndexFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_listing_shows_all_artworks(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'In Progress One', 'completed_date' => null]);
        Artwork::factory()->for($user)->create(['title' => 'Completed One', 'completed_date' => '2026-01-15']);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('In Progress One', false)
            ->assertSee('Completed One', false);
    }

    public function test_in_progress_filter_shows_only_incomplete_artworks(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Still Working', 'completed_date' => null]);
        Artwork::factory()->for($user)->create(['title' => 'Already Done', 'completed_date' => '2026-03-01']);

        $this->get(route('artworks.index', ['filter' => 'in_progress']))
            ->assertOk()
            ->assertSee('Still Working', false)
            ->assertDontSee('Already Done', false);
    }

    public function test_completed_filter_shows_only_completed_artworks(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Still Working', 'completed_date' => null]);
        Artwork::factory()->for($user)->create(['title' => 'Already Done', 'completed_date' => '2026-03-01']);

        $this->get(route('artworks.index', ['filter' => 'completed']))
            ->assertOk()
            ->assertSee('Already Done', false)
            ->assertDontSee('Still Working', false);
    }

    public function test_untitled_filter_includes_blank_and_whitespace_only_titles(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => '']);
        Artwork::factory()->for($user)->create(['title' => '   ']);
        Artwork::factory()->for($user)->create(['title' => 'Named Work']);

        $this->get(route('artworks.index', ['filter' => 'untitled']))
            ->assertOk()
            ->assertSee('Untitled', false)
            ->assertDontSee('Named Work', false);
    }

    public function test_missing_photo_filter_excludes_artworks_with_photos(): void
    {
        $user = $this->signIn();

        $withoutPhoto = Artwork::factory()->for($user)->create(['title' => 'No Photo Yet']);
        $withPhoto = Artwork::factory()->for($user)->create(['title' => 'Has Photo']);

        ArtworkPhoto::create([
            'artwork_id' => $withPhoto->id,
            'file_path' => 'artworks/'.$withPhoto->id.'/thumb.jpg',
            'mime_type' => 'image/jpeg',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $this->get(route('artworks.index', ['filter' => 'missing_photo']))
            ->assertOk()
            ->assertSee('No Photo Yet', false)
            ->assertDontSee('Has Photo', false);
    }

    public function test_missing_dimensions_filter_includes_partial_dimensions(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Fully Sized',
            'width' => 12,
            'height' => 18,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Width Only',
            'width' => 12,
            'height' => null,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Height Only',
            'width' => null,
            'height' => 18,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'No Dimensions',
            'width' => null,
            'height' => null,
        ]);

        $this->get(route('artworks.index', ['filter' => 'missing_dimensions']))
            ->assertOk()
            ->assertSee('Width Only', false)
            ->assertSee('Height Only', false)
            ->assertSee('No Dimensions', false)
            ->assertDontSee('Fully Sized', false);
    }

    public function test_artwork_type_filter_matches_exact_value(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Oil Piece', 'artwork_type' => 'Painting']);
        Artwork::factory()->for($user)->create(['title' => 'Clay Piece', 'artwork_type' => 'Sculpture']);

        $this->get(route('artworks.index', ['artwork_type' => 'Painting']))
            ->assertOk()
            ->assertSee('Oil Piece', false)
            ->assertDontSee('Clay Piece', false);
    }

    public function test_medium_filter_matches_exact_value(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Ink Work', 'medium' => 'Ink']);
        Artwork::factory()->for($user)->create(['title' => 'Oil Work', 'medium' => 'Oil on linen']);

        $this->get(route('artworks.index', ['medium' => 'Ink']))
            ->assertOk()
            ->assertSee('Ink Work', false)
            ->assertDontSee('Oil Work', false);
    }

    public function test_unknown_field_filter_values_return_no_results_without_error(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Existing Work', 'medium' => 'Watercolor']);

        $this->get(route('artworks.index', ['medium' => 'Not In Catalog']))
            ->assertOk()
            ->assertSee('No artworks match these filters', false)
            ->assertDontSee('Existing Work', false);
    }

    public function test_filter_and_sort_work_together(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Zebra Done', 'completed_date' => '2026-01-01']);
        Artwork::factory()->for($user)->create(['title' => 'Apple Done', 'completed_date' => '2026-02-01']);
        Artwork::factory()->for($user)->create(['title' => 'Still Working', 'completed_date' => null]);

        $this->get(route('artworks.index', [
            'filter' => 'completed',
            'sort' => 'title',
            'direction' => 'asc',
        ]))
            ->assertOk()
            ->assertSeeInOrder(['Apple Done', 'Zebra Done'], false)
            ->assertDontSee('Still Working', false);
    }

    public function test_filter_and_pagination_preserve_query_string(): void
    {
        $user = $this->signIn();

        Artwork::factory()->count(21)->for($user)->sequence(
            fn ($sequence) => ['title' => 'In Progress '.$sequence->index, 'completed_date' => null],
        )->create();

        Artwork::factory()->for($user)->create([
            'title' => 'Completed Outlier',
            'completed_date' => '2026-01-01',
        ]);

        $page1 = $this->get(route('artworks.index', ['filter' => 'in_progress']));

        $page1->assertOk();
        $page1->assertSee('filter=in_progress', false);
        $page1->assertSee('page=2', false);

        $page2 = $this->get(route('artworks.index', ['filter' => 'in_progress', 'page' => 2]));

        $page2->assertOk();
        $page2->assertSee('In Progress', false);
        $page2->assertDontSee('Completed Outlier', false);
    }

    public function test_invalid_quick_filter_falls_back_to_showing_all_artworks(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Open Work', 'completed_date' => null]);
        Artwork::factory()->for($user)->create(['title' => 'Closed Work', 'completed_date' => '2026-04-01']);

        $this->get(route('artworks.index', ['filter' => 'not_a_real_filter']))
            ->assertOk()
            ->assertSee('Open Work', false)
            ->assertSee('Closed Work', false);
    }

    public function test_clear_filters_link_appears_when_filters_are_active(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Filtered', 'completed_date' => null]);

        $this->get(route('artworks.index', ['filter' => 'in_progress']))
            ->assertOk()
            ->assertSee('Clear filters', false);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertDontSee('Clear filters', false);
    }

    public function test_filters_apply_before_default_sort_order(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Older Incomplete',
            'completed_date' => null,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Newer Incomplete',
            'completed_date' => null,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Recent Completed',
            'completed_date' => '2026-06-15',
        ]);

        $this->get(route('artworks.index', ['filter' => 'in_progress']))
            ->assertOk()
            ->assertSeeInOrder(['Older Incomplete', 'Newer Incomplete'], false)
            ->assertDontSee('Recent Completed', false);
    }

    public function test_filter_class_normalizes_invalid_quick_filter_to_all(): void
    {
        $filters = new ArtworkIndexFilters('bogus', null, null);

        $this->assertSame(ArtworkIndexFilters::QUICK_ALL, $filters->quickFilter());
        $this->assertFalse($filters->hasActiveFilters());
    }
}
