<?php

namespace Tests\Feature;

use App\Models\Artwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkCompletedDateDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_in_progress_when_completed_date_is_null(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Open Studio Piece',
            'completed_date' => null,
        ]);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('Open Studio Piece', false)
            ->assertSee('In Progress', false);
    }

    public function test_index_displays_completed_date_when_set(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Finished Studio Piece',
            'completed_date' => '2026-03-15',
        ]);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('Finished Studio Piece', false)
            ->assertSee('2026-03-15', false);
    }

    public function test_show_displays_in_progress_when_completed_date_is_null(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create([
            'title' => 'Open Detail Piece',
            'completed_date' => null,
        ]);

        $this->get(route('artworks.show', $artwork))
            ->assertOk()
            ->assertSee('In Progress', false);
    }

    public function test_show_displays_completed_date_when_set(): void
    {
        $user = $this->signIn();

        $artwork = Artwork::factory()->for($user)->create([
            'title' => 'Finished Detail Piece',
            'completed_date' => '2026-04-20',
        ]);

        $this->get(route('artworks.show', $artwork))
            ->assertOk()
            ->assertSee('2026-04-20', false);
    }

    public function test_mobile_card_displays_in_progress_when_completed_date_is_null(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Open Mobile Piece',
            'completed_date' => null,
        ]);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('artwork-mobile-card', false)
            ->assertSee('Open Mobile Piece', false)
            ->assertSee('In Progress', false);
    }

    public function test_mobile_card_displays_completed_date_when_set(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create([
            'title' => 'Finished Mobile Piece',
            'completed_date' => '2026-05-01',
        ]);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('artwork-mobile-card', false)
            ->assertSee('Finished Mobile Piece', false)
            ->assertSee('2026-05-01', false);
    }
}
