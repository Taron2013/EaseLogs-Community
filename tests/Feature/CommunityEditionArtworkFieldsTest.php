<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityEditionArtworkFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_edit_forms_share_community_edition_fields(): void
    {
        $this->signIn();
        $artwork = Artwork::factory()->create();

        $create = $this->get('/artworks/create');
        $create->assertOk();
        $create->assertSee('name="title"', false);
        $create->assertSee('name="start_date"', false);
        $create->assertSee('name="completed_work"', false);
        $create->assertSee('name="completed_date"', false);
        $create->assertSee('name="medium"', false);
        $create->assertSee('name="height"', false);
        $create->assertSee('name="width"', false);
        $create->assertSee('name="depth"', false);
        $create->assertSee('name="dimension_unit"', false);
        $create->assertSee('name="photo"', false);
        $create->assertSee('name="notes"', false);
        $create->assertDontSee('name="inventory_code"', false);
        $create->assertDontSee('name="sku"', false);
        $create->assertDontSee('name="status"', false);
        $create->assertDontSee('estimated_value', false);

        $edit = $this->get(route('artworks.edit', $artwork));
        $edit->assertOk();
        $edit->assertSee('name="title"', false);
        $edit->assertSee('name="start_date"', false);
        $edit->assertSee('name="completed_work"', false);
        $edit->assertSee('name="completed_date"', false);
        $edit->assertDontSee('name="artwork_type"', false);
        $edit->assertSee('name="notes"', false);
        $edit->assertDontSee('name="inventory_code"', false);
        $edit->assertDontSee('professional_art_reproduction_photo', false);
    }

    public function test_store_persists_community_edition_columns(): void
    {
        $this->signIn();

        $response = $this->post('/artworks', [
            'title' => 'Studio Piece',
            'start_date' => '2026-01-10',
            'completed_work' => 1,
            'completed_date' => '2026-02-20',
            'medium' => 'Oil on linen',
            'height' => 24,
            'width' => 18,
            'depth' => 1.5,
            'dimension_unit' => 'in',
            'notes' => 'Framed',
        ]);

        $response->assertRedirect('/artworks');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('Studio Piece', $artwork->title);
        $this->assertSame('2026-01-10', $artwork->start_date?->format('Y-m-d'));
        $this->assertSame('2026-02-20', $artwork->completed_date?->format('Y-m-d'));
        $this->assertSame('Oil on linen', $artwork->medium);
        $this->assertSame('Framed', $artwork->notes);
    }
}
