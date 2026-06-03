<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtworkCompletionFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_create_and_edit_forms_show_completed_work_checkbox(): void
    {
        $this->signIn();

        $this->get('/artworks/create')
            ->assertOk()
            ->assertSee('id="completed_work"', false)
            ->assertSee('Completed work');

        $artwork = Artwork::factory()->create(['completed_date' => null]);

        $this->get(route('artworks.edit', $artwork))
            ->assertOk()
            ->assertSee('id="completed_work"', false)
            ->assertSee('Completed work');
    }

    public function test_completed_artwork_edit_page_checkbox_is_checked(): void
    {
        $this->signIn();

        $artwork = Artwork::factory()->create([
            'completed_date' => '2026-02-15',
        ]);

        $response = $this->get(route('artworks.edit', $artwork));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/id="completed_work"[^>]*checked/s',
            $response->getContent()
        );
    }

    public function test_completed_date_field_hidden_when_incomplete_on_edit(): void
    {
        $this->signIn();

        $artwork = Artwork::factory()->create(['completed_date' => null]);

        $response = $this->get(route('artworks.edit', $artwork));

        $response->assertOk();
        $response->assertSee('id="completed-date-field"', false);
        $this->assertStringContainsString(
            'id="completed-date-field" class="field" style="display:none;"',
            $response->getContent()
        );
    }

    public function test_completed_date_field_visible_when_complete_on_edit(): void
    {
        $this->signIn();

        $artwork = Artwork::factory()->create([
            'completed_date' => '2026-02-15',
        ]);

        $response = $this->get(route('artworks.edit', $artwork));

        $response->assertOk();
        $this->assertStringContainsString(
            'id="completed-date-field" class="field" style=""',
            $response->getContent()
        );
    }

    public function test_create_page_hides_completed_date_when_incomplete(): void
    {
        $this->signIn();

        $response = $this->get('/artworks/create');

        $response->assertOk();
        $this->assertStringContainsString(
            'id="completed-date-field" class="field" style="display:none;"',
            $response->getContent()
        );
    }

    public function test_forms_include_script_to_toggle_completed_date_visibility(): void
    {
        $this->signIn();

        $this->get('/artworks/create')
            ->assertSee('updateCompletedDateVisibility', false);

        $artwork = Artwork::factory()->create();

        $this->get(route('artworks.edit', $artwork))
            ->assertSee('updateCompletedDateVisibility', false);
    }

    public function test_unchecking_completed_work_clears_completed_date_on_update(): void
    {
        $this->signIn();

        $artwork = Artwork::factory()->create([
            'completed_date' => '2026-02-15',
        ]);

        $response = $this->put('/artworks/'.$artwork->id, [
            'title' => $artwork->title,
            'completed_work' => 0,
        ]);

        $response->assertRedirect('/artworks/'.$artwork->id);

        $this->assertNull($artwork->fresh()->completed_date);
    }

    public function test_completed_work_without_date_sets_completed_date_on_store(): void
    {
        $this->signIn();

        $response = $this->post('/artworks', [
            'title' => 'Finished Piece',
            'completed_work' => 1,
        ]);

        $response->assertRedirect('/artworks');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertNotNull($artwork->completed_date);
    }

    public function test_completed_date_rejected_when_completed_work_unchecked(): void
    {
        $this->signIn();

        $response = $this->from('/artworks/create')->post('/artworks', [
            'title' => 'In Progress',
            'completed_work' => 0,
            'completed_date' => '2026-02-01',
        ]);

        $response->assertRedirect('/artworks/create');
        $response->assertSessionHasErrors('completed_date');
        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_completed_date_cleared_when_completed_work_unchecked_and_date_empty(): void
    {
        $this->signIn();

        $response = $this->post('/artworks', [
            'title' => 'In Progress',
            'completed_work' => 0,
        ]);

        $response->assertRedirect('/artworks');

        $this->assertNull(Artwork::query()->first()?->completed_date);
    }

    public function test_completed_artwork_upload_blocked_without_confirmation(): void
    {
        $this->signIn();

        $artwork = Artwork::factory()->create([
            'completed_date' => '2026-02-15',
        ]);

        $response = $this->from(route('artworks.edit', $artwork))->put('/artworks/'.$artwork->id, [
            'title' => $artwork->title,
            'completed_work' => 1,
            'completed_date' => '2026-02-15',
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
        ]);

        $response->assertRedirect(route('artworks.edit', $artwork));
        $response->assertSessionHasErrors('confirm_completed_photo_upload');
    }

    public function test_completed_artwork_upload_allowed_with_confirmation(): void
    {
        $this->signIn();

        $artwork = Artwork::factory()->create([
            'completed_date' => '2026-02-15',
        ]);

        $response = $this->put('/artworks/'.$artwork->id, [
            'title' => $artwork->title,
            'completed_work' => 1,
            'completed_date' => '2026-02-15',
            'confirm_completed_photo_upload' => 1,
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
        ]);

        $response->assertRedirect('/artworks/'.$artwork->id);
        $this->assertNotNull($artwork->fresh()->latestPhoto);
    }

    public function test_incomplete_artwork_upload_does_not_require_confirmation(): void
    {
        $this->signIn();

        $artwork = Artwork::factory()->create([
            'completed_date' => null,
        ]);

        $response = $this->put('/artworks/'.$artwork->id, [
            'title' => $artwork->title,
            'completed_work' => 0,
            'photo' => UploadedFile::fake()->image('first.jpg'),
        ]);

        $response->assertRedirect('/artworks/'.$artwork->id);
        $response->assertSessionDoesntHaveErrors('confirm_completed_photo_upload');
        $this->assertNotNull($artwork->fresh()->latestPhoto);
    }

    public function test_create_completed_artwork_with_photo_requires_confirmation(): void
    {
        $this->signIn();

        $response = $this->from('/artworks/create')->post('/artworks', [
            'title' => 'New Complete',
            'completed_work' => 1,
            'photo' => UploadedFile::fake()->image('new.jpg'),
        ]);

        $response->assertRedirect('/artworks/create');
        $response->assertSessionHasErrors('confirm_completed_photo_upload');
        $this->assertDatabaseCount('artworks', 0);
    }
}
