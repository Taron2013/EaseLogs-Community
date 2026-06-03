<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Support\ArtworkIndexSort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkIndexSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_listing_orders_by_updated_at_descending(): void
    {
        $user = $this->signIn();

        $oldest = Artwork::factory()->for($user)->create([
            'title' => 'Oldest Touch',
            'completed_date' => '2025-12-01',
        ]);
        $oldest->forceFill(['updated_at' => now()->subDays(30)])->save();

        $newest = Artwork::factory()->for($user)->create([
            'title' => 'Newest Touch',
            'completed_date' => null,
        ]);
        $newest->forceFill(['updated_at' => now()->subHours(1)])->save();

        $middle = Artwork::factory()->for($user)->create([
            'title' => 'Middle Touch',
            'completed_date' => '2026-06-15',
        ]);
        $middle->forceFill(['updated_at' => now()->subDays(3)])->save();

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Newest Touch',
            'Middle Touch',
            'Oldest Touch',
        ], false);
    }

    public function test_recently_updated_link_uses_default_listing_without_sort_params(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Sorted Work']);

        $this->get(route('artworks.index', ['sort' => 'title', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('class="filter-pill">Recently updated', false)
            ->assertDontSee('class="filter-pill is-active">Recently updated', false);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('class="filter-pill is-active">Recently updated', false);
    }

    public function test_reset_view_link_clears_filters_sort_and_search(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Reset Me', 'completed_date' => null]);

        $this->get(route('artworks.index', [
            'filter' => 'in_progress',
            'sort' => 'title',
            'direction' => 'asc',
            'q' => 'Reset',
        ]))
            ->assertOk()
            ->assertSee('Reset view', false)
            ->assertSee(route('artworks.index'), false);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertDontSee('Reset view', false);
    }

    public function test_completed_date_descending_puts_incomplete_first_then_newest_completed(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Done Old', 'completed_date' => '2025-12-01']);
        Artwork::factory()->for($user)->create(['title' => 'Still Working', 'completed_date' => null]);
        Artwork::factory()->for($user)->create(['title' => 'Done New', 'completed_date' => '2026-06-15']);
        Artwork::factory()->for($user)->create(['title' => 'Also Working', 'completed_date' => null]);

        $this->get(route('artworks.index', ['sort' => 'completed_date', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder([
                'Still Working',
                'Also Working',
                'Done New',
                'Done Old',
            ], false);
    }

    public function test_completed_date_ascending_puts_oldest_completed_first_and_incomplete_last(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Done Old', 'completed_date' => '2025-12-01']);
        Artwork::factory()->for($user)->create(['title' => 'Still Working', 'completed_date' => null]);
        Artwork::factory()->for($user)->create(['title' => 'Done New', 'completed_date' => '2026-06-15']);
        Artwork::factory()->for($user)->create(['title' => 'Also Working', 'completed_date' => null]);

        $this->get(route('artworks.index', ['sort' => 'completed_date', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder([
                'Done Old',
                'Done New',
                'Still Working',
                'Also Working',
            ], false);
    }

    public function test_title_descending_puts_untitled_first_then_z_to_a(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Zebra']);
        Artwork::factory()->for($user)->create(['title' => '']);
        Artwork::factory()->for($user)->create(['title' => 'Apple']);
        Artwork::factory()->for($user)->create(['title' => '   ']);

        $this->get(route('artworks.index', ['sort' => 'title', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Untitled', 'Untitled', 'Zebra', 'Apple'], false);
    }

    public function test_title_ascending_puts_a_to_z_then_untitled_last(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Zebra']);
        Artwork::factory()->for($user)->create(['title' => '']);
        Artwork::factory()->for($user)->create(['title' => 'Apple']);
        Artwork::factory()->for($user)->create(['title' => '   ']);

        $this->get(route('artworks.index', ['sort' => 'title', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Apple', 'Zebra', 'Untitled', 'Untitled'], false);
    }

    public function test_dimensions_descending_puts_undimensioned_first_then_largest_area(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Large',
            'width' => 48,
            'height' => 60,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'No Size',
            'width' => null,
            'height' => 10,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Small',
            'width' => 18,
            'height' => 24,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Also No Size',
            'width' => 12,
            'height' => null,
        ]);

        $this->get(route('artworks.index', ['sort' => 'dimensions', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder([
                'No Size',
                'Also No Size',
                'Large',
                'Small',
            ], false);
    }

    public function test_dimensions_ascending_puts_smallest_first_then_undimensioned_last(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Large',
            'width' => 48,
            'height' => 60,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'No Size',
            'width' => null,
            'height' => 10,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Small',
            'width' => 18,
            'height' => 24,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Also No Size',
            'width' => 12,
            'height' => null,
        ]);

        $this->get(route('artworks.index', ['sort' => 'dimensions', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder([
                'Small',
                'Large',
                'No Size',
                'Also No Size',
            ], false);
    }

    public function test_start_date_ascending_and_descending_sort(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Later Start', 'start_date' => '2026-06-01']);
        Artwork::factory()->for($user)->create(['title' => 'Earlier Start', 'start_date' => '2026-01-01']);

        $this->get(route('artworks.index', ['sort' => 'start_date', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Earlier Start', 'Later Start'], false);

        $this->get(route('artworks.index', ['sort' => 'start_date', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Later Start', 'Earlier Start'], false);
    }

    public function test_updated_at_ascending_and_descending_sort(): void
    {
        $user = $this->signIn();

        $older = Artwork::factory()->for($user)->create(['title' => 'Stale']);
        $older->forceFill(['updated_at' => now()->subDays(10)])->save();

        $newer = Artwork::factory()->for($user)->create(['title' => 'Fresh']);
        $newer->forceFill(['updated_at' => now()->subHours(2)])->save();

        $this->get(route('artworks.index', ['sort' => 'updated_at', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Stale', 'Fresh'], false);

        $this->get(route('artworks.index', ['sort' => 'updated_at', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Fresh', 'Stale'], false);
    }

    public function test_photo_column_is_not_sortable(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Has Photo Column']);

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('<th>Photo</th>', false);
        $response->assertDontSee('sort=photo', false);
    }

    public function test_invalid_sort_falls_back_to_default_listing(): void
    {
        $user = $this->signIn();

        $older = Artwork::factory()->for($user)->create([
            'title' => 'Older Fallback',
            'completed_date' => '2026-01-01',
        ]);
        $older->forceFill(['updated_at' => now()->subDays(5)])->save();

        $newer = Artwork::factory()->for($user)->create([
            'title' => 'Newer Fallback',
            'completed_date' => null,
        ]);
        $newer->forceFill(['updated_at' => now()->subHours(1)])->save();

        $response = $this->get(route('artworks.index', ['sort' => 'photo', 'direction' => 'asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Newer Fallback', 'Older Fallback'], false);
        $response->assertDontSee('sort=photo', false);
    }

    public function test_invalid_direction_falls_back_to_ascending_for_explicit_sort(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Alpha']);
        Artwork::factory()->for($user)->create(['title' => 'Zebra']);

        $response = $this->get(route('artworks.index', ['sort' => 'title', 'direction' => 'sideways']));

        $response->assertOk();
        $response->assertSeeInOrder(['Alpha', 'Zebra'], false);
    }

    public function test_sort_class_uses_default_listing_when_sort_missing(): void
    {
        $sort = new ArtworkIndexSort(null, null);

        $this->assertTrue($sort->usesDefaultListing());
        $this->assertSame(ArtworkIndexSort::DEFAULT_SORT, $sort->column());
    }

    public function test_sort_class_normalizes_header_keys(): void
    {
        $sort = new ArtworkIndexSort('TITLE', 'DESC');

        $this->assertFalse($sort->usesDefaultListing());
        $this->assertSame('title', $sort->column());
        $this->assertSame('desc', $sort->direction());
        $this->assertSame('asc', $sort->nextDirectionFor('title'));
    }
}
