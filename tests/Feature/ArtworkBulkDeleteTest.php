<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtworkBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_index_renders_selection_checkboxes_and_select_all(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Selectable Work']);

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('artwork-select-all', false);
        $response->assertSee('artwork-row-select', false);
        $response->assertSee('>Select</span>', false);
        $response->assertSee('bulk-delete-form', false);
        $response->assertSee(route('artworks.bulk-delete'), false);
    }

    public function test_bulk_delete_deletes_selected_artworks_and_preserves_others(): void
    {
        $user = $this->signIn();

        $deleteA = Artwork::factory()->for($user)->create(['title' => 'Delete A']);
        $deleteB = Artwork::factory()->for($user)->create(['title' => 'Delete B']);
        $keep = Artwork::factory()->for($user)->create(['title' => 'Keep Me']);

        $response = $this->delete(route('artworks.bulk-delete'), [
            'ids' => [$deleteA->id, $deleteB->id],
        ]);

        $response->assertRedirect(route('artworks.index'));
        $response->assertSessionHas('success', '2 artworks deleted.');

        $this->assertDatabaseMissing('artworks', ['id' => $deleteA->id]);
        $this->assertDatabaseMissing('artworks', ['id' => $deleteB->id]);
        $this->assertDatabaseHas('artworks', ['id' => $keep->id, 'title' => 'Keep Me']);
    }

    public function test_bulk_delete_removes_associated_photo_files(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create(['title' => 'With Photo']);
        $path = 'artworks/'.$artwork->id.'/bulk.jpg';
        Storage::disk('public')->put($path, 'image-bytes');

        ArtworkPhoto::create([
            'artwork_id' => $artwork->id,
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $this->delete(route('artworks.bulk-delete'), ['ids' => [$artwork->id]])
            ->assertRedirect(route('artworks.index'));

        $this->assertDatabaseCount('artworks', 0);
        $this->assertDatabaseCount('artwork_photos', 0);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_empty_submission_is_rejected_safely(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Still Here']);

        $response = $this->from(route('artworks.index'))
            ->delete(route('artworks.bulk-delete'), ['ids' => []]);

        $response->assertRedirect(route('artworks.index'));
        $response->assertSessionHasErrors('ids');
        $this->assertDatabaseCount('artworks', 1);
    }

    public function test_invalid_ids_are_rejected_safely(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create(['title' => 'Valid Row']);

        $response = $this->from(route('artworks.index'))
            ->delete(route('artworks.bulk-delete'), [
                'ids' => [$artwork->id, 99999],
            ]);

        $response->assertRedirect(route('artworks.index'));
        $response->assertSessionHasErrors('ids.1');
        $this->assertDatabaseHas('artworks', ['id' => $artwork->id]);
    }

    public function test_bulk_delete_while_filtered_only_affects_submitted_ids(): void
    {
        $user = $this->signIn();

        $inProgress = Artwork::factory()->for($user)->create([
            'title' => 'In Progress Target',
            'completed_date' => null,
        ]);

        Artwork::factory()->for($user)->create([
            'title' => 'Completed Other',
            'completed_date' => '2026-01-01',
        ]);

        $response = $this->delete(route('artworks.bulk-delete', [
            'filter' => 'in_progress',
            'sort' => 'title',
            'direction' => 'asc',
        ]), [
            'ids' => [$inProgress->id],
            'filter' => 'in_progress',
            'sort' => 'title',
            'direction' => 'asc',
        ]);

        $response->assertRedirect(route('artworks.index', [
            'filter' => 'in_progress',
            'sort' => 'title',
            'direction' => 'asc',
        ]));
        $response->assertSessionHas('success', '1 artwork deleted.');
        $this->assertDatabaseMissing('artworks', ['id' => $inProgress->id]);
        $this->assertDatabaseHas('artworks', ['title' => 'Completed Other']);
    }

    public function test_bulk_delete_redirect_preserves_sort_and_page_query_string(): void
    {
        $user = $this->signIn();

        Artwork::factory()->count(21)->for($user)->sequence(
            fn ($sequence) => [
                'title' => 'Paged Delete '.$sequence->index,
                'completed_date' => null,
            ],
        )->create();

        $target = Artwork::query()->where('title', 'Paged Delete 0')->first();
        $this->assertNotNull($target);

        $response = $this->delete(route('artworks.bulk-delete'), [
            'ids' => [$target->id],
            'sort' => 'title',
            'direction' => 'asc',
            'page' => '2',
        ]);

        $response->assertRedirect(route('artworks.index', [
            'sort' => 'title',
            'direction' => 'asc',
            'page' => '2',
        ]));
    }

    public function test_bulk_delete_requires_authentication(): void
    {
        $artwork = Artwork::factory()->create();

        $this->delete(route('artworks.bulk-delete'), ['ids' => [$artwork->id]])
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('artworks', ['id' => $artwork->id]);
    }
}
