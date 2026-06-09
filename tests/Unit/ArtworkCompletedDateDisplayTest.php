<?php

namespace Tests\Unit;

use App\Models\Artwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkCompletedDateDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_formatted_display_completed_date_shows_in_progress_when_null(): void
    {
        $artwork = Artwork::factory()->create([
            'completed_date' => null,
        ]);

        $this->assertNull($artwork->completed_date);
        $this->assertSame('In Progress', $artwork->formattedDisplayCompletedDate());
    }

    public function test_formatted_display_completed_date_shows_y_m_d_when_set(): void
    {
        $artwork = Artwork::factory()->create([
            'completed_date' => '2026-03-15',
        ]);

        $this->assertSame('2026-03-15', $artwork->formattedDisplayCompletedDate());
    }
}
