<?php

namespace Tests\Feature;

use App\Models\Artwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ArtworkStartDateDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08 14:30:00');
    }

    public function test_create_form_shows_todays_date_in_start_date_field(): void
    {
        $this->signIn();

        $this->get('/artworks/create')
            ->assertOk()
            ->assertSee('id="start_date"', false)
            ->assertSee('value="2026-06-08"', false);
    }

    public function test_edit_form_shows_stored_start_date(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create([
            'start_date' => '2024-01-15',
        ]);

        $this->get(route('artworks.edit', $artwork))
            ->assertOk()
            ->assertSee('value="2024-01-15"', false);
    }

    public function test_edit_form_with_null_start_date_shows_completed_date(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create([
            'start_date' => null,
            'completed_date' => '2024-08-12',
        ]);

        $this->get(route('artworks.edit', $artwork))
            ->assertOk()
            ->assertSee('value="2024-08-12"', false);

        $this->assertNull($artwork->fresh()->start_date);
    }

    public function test_edit_form_with_null_start_date_shows_created_at_date(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create([
            'start_date' => null,
            'completed_date' => null,
        ]);
        $artwork->forceFill(['created_at' => '2023-04-10 09:00:00'])->save();

        $this->get(route('artworks.edit', $artwork))
            ->assertOk()
            ->assertSee('value="2023-04-10"', false);

        $this->assertNull($artwork->fresh()->start_date);
    }

    public function test_edit_form_validation_failure_preserves_old_start_date(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create([
            'start_date' => null,
            'completed_date' => '2024-08-12',
        ]);

        $this->from(route('artworks.edit', $artwork))->put(route('artworks.update', $artwork), [
            'title' => $artwork->title,
            'start_date' => '2024-06-01',
            'completed_work' => '1',
            'completed_date' => '2024-01-01',
        ])->assertRedirect(route('artworks.edit', $artwork))
            ->assertSessionHasErrors(['completed_date']);

        $this->get(route('artworks.edit', $artwork))
            ->assertOk()
            ->assertSee('value="2024-06-01"', false);
    }
}
