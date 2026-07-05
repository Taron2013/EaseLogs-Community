<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Models\ArtworkTag;
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
            ->assertSee('No artworks match this view', false)
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

        $older = Artwork::factory()->for($user)->create([
            'title' => 'Older Incomplete',
            'completed_date' => null,
        ]);
        $older->forceFill(['updated_at' => now()->subDays(10)])->save();

        $newer = Artwork::factory()->for($user)->create([
            'title' => 'Newer Incomplete',
            'completed_date' => null,
        ]);
        $newer->forceFill(['updated_at' => now()->subHours(2)])->save();

        Artwork::factory()->for($user)->create([
            'title' => 'Recent Completed',
            'completed_date' => '2026-06-15',
        ]);

        $this->get(route('artworks.index', ['filter' => 'in_progress']))
            ->assertOk()
            ->assertSeeInOrder(['Newer Incomplete', 'Older Incomplete'], false)
            ->assertDontSee('Recent Completed', false);
    }

    public function test_clear_filters_preserves_search_and_sort(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Find Me', 'completed_date' => null]);

        $this->get(route('artworks.index', [
            'filter' => 'in_progress',
            'q' => 'Find',
            'sort' => 'title',
            'direction' => 'asc',
        ]))
            ->assertOk()
            ->assertSee('Clear filters', false);

        $clearUrl = route('artworks.index', [
            'q' => 'Find',
            'sort' => 'title',
            'direction' => 'asc',
        ]);

        $this->get($clearUrl)
            ->assertOk()
            ->assertSee('Find Me', false)
            ->assertSee('q=Find', false)
            ->assertSee('sort=title', false);
    }

    public function test_pagination_preserves_filter_search_and_sort(): void
    {
        $user = $this->signIn();

        Artwork::factory()->count(21)->for($user)->sequence(
            fn ($sequence) => [
                'title' => 'Searchable '.$sequence->index,
                'completed_date' => null,
                'notes' => 'batch notes',
            ],
        )->create();

        $page1 = $this->get(route('artworks.index', [
            'filter' => 'in_progress',
            'q' => 'Searchable',
            'sort' => 'title',
            'direction' => 'asc',
        ]));

        $page1->assertOk();
        $page1->assertSee('filter=in_progress', false);
        $page1->assertSee('q=Searchable', false);
        $page1->assertSee('sort=title', false);
        $page1->assertSee('direction=asc', false);
        $page1->assertSee('page=2', false);
    }

    public function test_has_dimensions_filter_requires_width_and_height(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Sized', 'width' => 10, 'height' => 12, 'depth' => null]);
        Artwork::factory()->for($user)->create(['title' => 'Partial', 'width' => 10, 'height' => null]);

        $this->get(route('artworks.index', ['filter' => 'has_dimensions']))
            ->assertOk()
            ->assertSee('Sized', false)
            ->assertDontSee('Partial', false);
    }

    public function test_width_and_height_range_filters_apply(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Small', 'width' => 8, 'height' => 10]);
        Artwork::factory()->for($user)->create(['title' => 'Large', 'width' => 24, 'height' => 36]);

        $this->get(route('artworks.index', ['width_min' => 20, 'height_min' => 30]))
            ->assertOk()
            ->assertSee('Large', false)
            ->assertDontSee('Small', false);
    }

    public function test_tag_filter_matches_artwork_with_tag(): void
    {
        $user = $this->signIn();
        $tagged = Artwork::factory()->for($user)->create(['title' => 'Tagged Piece']);
        Artwork::factory()->for($user)->create(['title' => 'Plain Piece']);

        $tag = ArtworkTag::query()->create([
            'user_id' => $user->id,
            'name' => 'Landscape',
            'normalized_name' => 'landscape',
        ]);
        $tagged->tags()->attach($tag);

        $this->get(route('artworks.index', ['tag' => 'Landscape']))
            ->assertOk()
            ->assertSee('Tagged Piece', false)
            ->assertDontSee('Plain Piece', false);
    }

    public function test_filter_class_normalizes_invalid_quick_filter_to_all(): void
    {
        $filters = new ArtworkIndexFilters('bogus', null, null, null, null, null, null, null);

        $this->assertSame(ArtworkIndexFilters::QUICK_ALL, $filters->quickFilter());
        $this->assertFalse($filters->hasActiveFilters());
    }
}
